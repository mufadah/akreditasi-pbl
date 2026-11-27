<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\JenisLuaran;
use App\Models\Luaran;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PdaPublikasiSyncService
{
    private const API_URL = 'https://pda.polines.ac.id/internal/publikasi/list';

    private const DETAIL_URL = 'https://pda.polines.ac.id/publikasi/';

    /** @var Collection<int, Dosen>|null */
    private ?Collection $cachedDosens = null;

    /** @var array<string, bool> */
    private array $cachedTitles = [];

    /** @var array<string, JenisLuaran> */
    private array $cachedJenisLuaran = [];

    private function loadCaches(): void
    {
        $this->cachedDosens = Dosen::all();
        $this->cachedTitles = [];

        foreach ([
            'judul_buku',
            'nama_jurnal',
            'nama_prosiding',
            'nama_seminar',
            'judul_hak_cipta',
            'nama_produk',
        ] as $column) {
            foreach (Luaran::query()->whereNotNull($column)->pluck($column) as $title) {
                $normalized = mb_strtolower(trim((string) $title));

                if ($normalized !== '') {
                    $this->cachedTitles[$normalized] = true;
                }
            }
        }
    }

    /**
     * Sinkronisasi publikasi PDA.
     *
     * maxPages = 0 berarti mengambil halaman sampai data habis.
     *
     * @return array{pages:int,created:int,already_exists:int,skipped:int}
     */
    public function sync(
        int $maxPages,
        ?string $year = null,
        ?callable $onItem = null
    ): array {
        $this->loadCaches();

        $result = [
            'pages' => 0,
            'created' => 0,
            'already_exists' => 0,
            'skipped' => 0,
        ];

        for ($page = 1; $maxPages === 0 || $page <= $maxPages; $page++) {
            if ($page > 1) {
                usleep(300000);
            }

            $response = null;

            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    $response = Http::withOptions(['version' => 2.0])
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
                            'Referer' => 'https://pda.polines.ac.id/publikasi',
                            'X-Requested-With' => 'XMLHttpRequest',
                            'Accept' => 'application/json',
                        ])
                        ->timeout(30)
                        ->get(self::API_URL, [
                            'page' => $page,
                            'per_page' => 50,
                            'sort' => 'tanggal',
                            'direction' => 'desc',
                        ]);

                    if ($response->successful()) {
                        break;
                    }
                } catch (\Throwable $e) {
                    $this->writeScraperLog(
                        "Gagal mengambil daftar halaman {$page}, percobaan {$attempt}: ".$e->getMessage()
                    );
                }

                if ($attempt < 3) {
                    sleep($attempt);
                }
            }

            if (! $response || ! $response->successful()) {
                $status = $response ? $response->status() : 'timeout';

                throw new RuntimeException(
                    "PDA mengembalikan HTTP {$status} pada halaman {$page}."
                );
            }

            $payload = $response->json();
            $items = $this->publicationRows($payload);
            $result['pages']++;

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    $result['skipped']++;

                    continue;
                }

                // Hanya publikasi dari Jurusan Teknik Elektro yang diproses.
                if (! $this->matchesTargetProdi($item)) {
                    $dosen = $item['dosen'] ?? [];

                    $jurusan = is_array($dosen)
                        ? $this->stringValue(
                            $dosen['homebase_jurusan'] ?? ''
                        )
                        : '';

                    $judul = $this->stringValue($item['judul'] ?? '');

                    $this->writeScraperLog(
                        'Publikasi dilewati karena jurusan tidak cocok. '.
                        'Jurusan: '.($jurusan !== '' ? $jurusan : 'TIDAK DITEMUKAN').
                        ' | Judul: '.$judul
                    );

                    $result['skipped']++;

                    continue;
                }

                // Ambil detail jika ID publikasi tersedia.
                $publicationId = $this->stringValue(
                    $this->firstValue($item, ['id', 'uuid'])
                );

                if ($publicationId !== '') {
                    try {
                        $detail = $this->scrapeLuaranDetail($publicationId);

                        // Data detail memiliki prioritas atas data daftar.
                        $item = array_merge($item, $detail);
                    } catch (\Throwable $e) {
                        $this->writeScraperLog(
                            "Publikasi {$publicationId} dilewati: ".$e->getMessage()
                        );

                        $result['skipped']++;

                        continue;
                    }
                }

                if (! $this->matchesYear($item, $year)) {
                    $result['skipped']++;

                    continue;
                }

                $title = $this->stringValue($this->firstValue($item, [
                    'judul',
                    'title',
                    'judul_publikasi',
                    'nama_publikasi',
                    'publication_title',
                ]));

                if ($title === '') {
                    $result['skipped']++;

                    continue;
                }

                $dosen = $this->findDosen($item);
                $type = $this->publicationType($item);

                if (! $dosen || ! $type) {
                    $result['skipped']++;

                    continue;
                }

                $date = $this->publicationDate($item);
                $publicationYear = $this->publicationYear($item, $date);

                if ($this->titleExists($title)) {
                    $result['already_exists']++;

                    if ($onItem !== null) {
                        $onItem([
                            'status' => 'exists',
                            'dosen' => $dosen,
                            'type' => $type,
                            'year' => $publicationYear,
                            'title' => $title,
                            'page' => $page,
                        ]);
                    }

                    continue;
                }

                $cacheKey = "{$type['name']}|{$type['index']}";

                $jenisLuaran = $this->cachedJenisLuaran[$cacheKey]
                    ??= JenisLuaran::firstOrCreate([
                        'nama_jenis_luaran' => $type['name'],
                        'indeks' => $type['index'],
                    ]);

                $doi = $this->stringValue(
                    $this->firstValue($item, ['doi', 'DOI'])
                );

                $urlFields = [
                    'url',
                    'tautan',
                    'link',
                    'url_publikasi',
                    'tautan_publikasi',
                ];

                if ($type['kind'] === 'proceedings') {
                    $urlFields = [
                        'tautan_prosiding',
                        'url_prosiding',
                        'link_prosiding',
                        ...$urlFields,
                    ];
                }

                $url = $this->stringValue(
                    $this->firstValue($item, $urlFields)
                );

                $data = [
                    'id_dosen' => $dosen->id_dosen,
                    'id_jenis_luaran' => $jenisLuaran->id_jenis_luaran,
                    'tahun_terbit' => $publicationYear,
                ];

                if ($type['kind'] === 'book') {
                    $data += [
                        'judul_buku' => $title,
                        'isbn' => $this->nullableString(
                            $this->firstValue($item, ['isbn', 'ISBN'])
                        ),
                        'penerbit_buku' => $this->nullableString(
                            $this->firstValue($item, ['penerbit', 'publisher'])
                        ),
                        'jenis_buku' => str_contains(
                            strtolower($type['classification']),
                            'monograf'
                        ) ? 'Buku Monograf' : 'Buku Ajar',
                        'tanggal_terbit' => $date,
                        'tautan_buku' => $url !== '' ? $url : null,
                    ];
                } elseif ($type['kind'] === 'proceedings') {
                    $data += [
                        'nama_seminar' => $this->nullableString(
                            $this->firstValue($item, ['nama_seminar', 'seminar'])
                        ) ?? $title,
                        'nama_prosiding' => $title,
                        'tanggal_seminar' => $date,
                        'doi_prosiding' => $doi !== '' ? $doi : null,
                        'tautan_seminar' => $url !== '' ? $url : null,
                    ];
                } else {
                    $data += [
                        'nama_jurnal' => $this->stringValue(
                            $this->firstValue($item, ['nama_jurnal'])
                        ) ?: $title,
                        'tanggal_terbit' => $date,
                        'doi' => $doi !== '' ? $doi : null,
                        'indeksasi' => $type['classification'],
                        'tautan_jurnal' => $url !== '' ? $url : null,
                    ];
                }

                Luaran::create($data);

                $this->cachedTitles[mb_strtolower(trim($title))] = true;
                $result['created']++;

                if ($onItem !== null) {
                    $onItem([
                        'status' => 'created',
                        'dosen' => $dosen,
                        'type' => $type,
                        'year' => $publicationYear,
                        'title' => $title,
                        'page' => $page,
                    ]);
                }
            }

            // Jika mode semua halaman, berhenti setelah halaman terakhir PDA.
            if ($maxPages === 0) {
                $lastPage = (int) data_get($payload, 'last_page', 0);

                if ($lastPage > 0 && $page >= $lastPage) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Memeriksa tahun publikasi.
     *
     * @param  array<string, mixed>  $publication
     */
    private function matchesYear(array $publication, ?string $year): bool
    {
        if ($year === null || trim($year) === '') {
            return true;
        }

        return $this->publicationYear(
            $publication,
            $this->publicationDate($publication)
        ) === (int) $year;
    }

    /**
     * Scraping detail halaman publikasi PDA berbasis Inertia.
     *
     * @return array<string, mixed>
     */
    public function scrapeLuaranDetail(
        string $publicationId,
        ?string $apiToken = null,
        int $retries = 3
    ): array {
        $publicationId = trim($publicationId);

        if ($publicationId === '') {
            throw new RuntimeException('ID publikasi PDA kosong.');
        }

        $retries = max(1, $retries);
        $url = self::DETAIL_URL.rawurlencode($publicationId);

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $request = Http::withOptions(['version' => 2.0])
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
                        'Referer' => 'https://pda.polines.ac.id/publikasi',
                        'Accept' => 'text/html,application/xhtml+xml',
                    ])
                    ->timeout(30);

                if ($apiToken !== null && trim($apiToken) !== '') {
                    $request = $request->withToken($apiToken);
                }

                $response = $request->get($url);

                if ($response->status() === 404) {
                    throw new RuntimeException(
                        "Detail publikasi {$publicationId} tidak ditemukan (HTTP 404)."
                    );
                }

                if (! $response->successful()) {
                    throw new RuntimeException(
                        "PDA mengembalikan HTTP {$response->status()} untuk publikasi {$publicationId}."
                    );
                }

                if (! class_exists(\DOMDocument::class)) {
                    throw new RuntimeException(
                        'Ekstensi PHP DOM belum tersedia.'
                    );
                }

                $previous = libxml_use_internal_errors(true);

                try {
                    $document = new \DOMDocument;
                    $document->loadHTML('<?xml encoding="UTF-8">'.$response->body());

                    $xpath = new \DOMXPath($document);
                    $nodes = $xpath->query('//*[@id="app"]/@data-page');

                    if (! $nodes || $nodes->length === 0) {
                        throw new RuntimeException(
                            "Atribut data-page tidak ditemukan untuk {$publicationId}."
                        );
                    }

                    $encodedData = html_entity_decode(
                        $nodes->item(0)->nodeValue,
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    );

                    $pageData = json_decode($encodedData, true);

                    if (! is_array($pageData)) {
                        throw new RuntimeException(
                            "JSON detail publikasi {$publicationId} tidak valid."
                        );
                    }

                    $publication = $pageData['props']['publikasi'] ?? null;

                    if (! is_array($publication)) {
                        throw new RuntimeException(
                            "Data publikasi tidak ditemukan untuk {$publicationId}."
                        );
                    }

                    if (isset($publication['penulis']) && is_array($publication['penulis'])) {
                        usort(
                            $publication['penulis'],
                            fn (array $a, array $b): int => ((int) ($a['urutan'] ?? PHP_INT_MAX))
                                <=>
                                ((int) ($b['urutan'] ?? PHP_INT_MAX))
                        );
                    }

                    return $publication;
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
            } catch (\Throwable $e) {
                $this->writeScraperLog(
                    "Detail {$publicationId}, percobaan {$attempt}/{$retries}: ".$e->getMessage()
                );

                if ($attempt >= $retries) {
                    throw new RuntimeException(
                        "Gagal mengambil detail publikasi {$publicationId}: ".$e->getMessage(),
                        previous: $e
                    );
                }

                usleep(500000 * $attempt);
            }
        }

        return [];
    }

    private function writeScraperLog(string $message): void
    {
        $path = storage_path('logs/luaran_scraper.log');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $path,
            '['.now()->toDateTimeString().'] '.$message.PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publicationRows(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new RuntimeException('Format respons publikasi PDA bukan JSON object.');
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        $candidates = [
            $payload['data']['data'] ?? null,
            $payload['data']['items'] ?? null,
            $payload['data']['publikasi'] ?? null,
            $payload['data']['rows'] ?? null,
            $payload['data'] ?? null,
            $payload['items'] ?? null,
            $payload['publikasi'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && array_is_list($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Respons publikasi PDA tidak berisi daftar data yang dikenali.'
        );
    }

    /**
     * @param  array<string, mixed>  $publication
     */
    private function matchesTargetProdi(array $publication): bool
    {
        $dosen = $publication['dosen'] ?? [];

        if (! is_array($dosen)) {
            return false;
        }

        $jurusan = mb_strtolower(trim(
            $this->stringValue($dosen['homebase_jurusan'] ?? '')
        ));

        return str_contains($jurusan, 'teknik elektro');
    }

    /**
     * @param  array<string, mixed>  $publication
     * @return array<int, array{nip:string,nidn:string,names:array<int,string>}>
     */
    private function authors(array $publication): array
    {
        $rawAuthors = [];

        if (isset($publication['dosen']) && is_array($publication['dosen'])) {
            $rawAuthors[] = $publication['dosen'];
        }

        $otherAuthors = $this->firstValue($publication, [
            'authors',
            'author',
            'penulis',
            'daftar_penulis',
            'nama_penulis',
        ]);

        if (is_array($otherAuthors)) {
            if (isset($otherAuthors['data']) && is_array($otherAuthors['data'])) {
                $otherAuthors = $otherAuthors['data'];
            }

            if (array_is_list($otherAuthors)) {
                $rawAuthors = array_merge($rawAuthors, $otherAuthors);
            } else {
                $rawAuthors[] = $otherAuthors;
            }
        } elseif ($otherAuthors !== null && $otherAuthors !== '') {
            $rawAuthors[] = $otherAuthors;
        }

        if ($rawAuthors === []) {
            $fallback = [
                'nip' => $this->firstValue($publication, [
                    'authorNip', 'author_nip', 'nip_author', 'nip_penulis', 'nuptk',
                ]),
                'nidn' => $this->firstValue($publication, [
                    'authorNidn', 'author_nidn', 'nidn_author', 'nidn_penulis',
                ]),
                'nama' => $this->firstValue($publication, [
                    'authorNama', 'author_name', 'nama_author', 'nama_dosen',
                ]),
            ];

            if ($fallback['nip'] || $fallback['nidn'] || $fallback['nama']) {
                $rawAuthors[] = $fallback;
            }
        }

        $authors = [];

        foreach ($rawAuthors as $author) {
            if (is_array($author)) {
                $name = $this->stringValue($this->firstValue($author, [
                    'nama_dosen',
                    'Nama_dosen',
                    'nama',
                    'Nama',
                    'name',
                    'Name',
                    'authorNama',
                    'author_name',
                    'nama_author',
                    'nama_penulis',
                    'author',
                    'penulis',
                ]));

                $names = $name !== ''
                    ? preg_split('/\s*(?:;|,|\bdan\b|&)\s*/iu', $name, -1, PREG_SPLIT_NO_EMPTY)
                    : [];

                $authors[] = [
                    'nip' => $this->stringValue($this->firstValue($author, [
                        'nip', 'NIP', 'authorNip', 'author_nip',
                        'nip_author', 'nip_penulis', 'nuptk', 'NUPTK',
                    ])),
                    'nidn' => $this->stringValue($this->firstValue($author, [
                        'nidn', 'NIDN', 'authorNidn', 'author_nidn',
                        'nidn_author', 'nidn_penulis',
                    ])),
                    'names' => $names ?: [],
                ];

                continue;
            }

            $name = $this->stringValue($author);
            $names = preg_split('/\s*(?:;|,|\bdan\b|&)\s*/iu', $name, -1, PREG_SPLIT_NO_EMPTY);

            $authors[] = [
                'nip' => '',
                'nidn' => '',
                'names' => $names ?: [],
            ];
        }

        return $authors;
    }

    /**
     * @param  array<string, mixed>  $publication
     */
    private function findDosen(array $publication): ?Dosen
    {
        if ($this->cachedDosens === null) {
            $this->cachedDosens = Dosen::all();
        }

        foreach ($this->authors($publication) as $author) {
            if ($author['nip'] !== '') {
                $dosen = $this->cachedDosens->firstWhere('nuptk', $author['nip']);

                if ($dosen) {
                    return $dosen;
                }
            }

            if ($author['nidn'] !== '') {
                $dosen = $this->cachedDosens->firstWhere('nidn', $author['nidn']);

                if ($dosen) {
                    return $dosen;
                }
            }

            foreach ($author['names'] as $name) {
                $normalizedName = mb_strtolower(trim($name));

                if ($normalizedName === '') {
                    continue;
                }

                $dosen = $this->cachedDosens->first(
                    fn (Dosen $d): bool => str_contains(
                        mb_strtolower(trim((string) $d->nama_dosen)),
                        $normalizedName
                    )
                );

                if ($dosen) {
                    return $dosen;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $publication
     * @return array{kind:string,name:string,index:?string,classification:string}|null
     */
    private function publicationType(array $publication): ?array
    {
        $kind = strtolower($this->stringValue($this->firstValue($publication, [
            'jenis_luaran',
            'jenis',
            'jenis_publikasi',
            'kategori',
            'tipe',
            'type',
        ])));

        $classification = $this->stringValue($this->firstValue($publication, [
            'indeksasi',
            'indeks',
            'indexing',
            'index',
            'quartile',
            'kuartil',
        ]));

        $category = strtolower($this->stringValue($this->firstValue($publication, [
            'kategori_kegiatan',
            'kategori',
        ])));

        $combined = strtolower(trim("{$kind} {$classification} {$category}"));

        if (str_contains($kind, 'prosiding') || str_contains($kind, 'seminar')) {
            $international = str_contains($combined, 'internasional');

            return [
                'kind' => 'proceedings',
                'name' => $international
                    ? 'Prosiding Seminar Internasional'
                    : 'Prosiding Seminar',
                'index' => $international ? 'IEEE/Scopus' : null,
                'classification' => $classification ?: (
                    $international ? 'Internasional' : 'Nasional'
                ),
            ];
        }

        if (str_contains($kind, 'buku') || str_contains($kind, 'monograf')) {
            return [
                'kind' => 'book',
                'name' => 'Buku Ajar / Monograf',
                'index' => 'ISBN',
                'classification' => $combined,
            ];
        }

        if (
            str_contains($combined, 'scopus')
            || str_contains($combined, 'internasional')
            || preg_match('/\bq[1-4]\b/i', $combined)
        ) {
            return [
                'kind' => 'journal',
                'name' => 'Jurnal Internasional Bereputasi',
                'index' => 'Scopus',
                'classification' => $classification ?: 'Scopus',
            ];
        }

        if (
            str_contains($combined, 'terakreditasi')
            || str_contains($combined, 'sinta')
            || str_contains($combined, 'nasional')
        ) {
            return [
                'kind' => 'journal',
                'name' => 'Jurnal Nasional Terakreditasi',
                'index' => 'SINTA',
                'classification' => $classification ?: 'SINTA',
            ];
        }

        if (
            str_contains($kind, 'jurnal')
            || str_contains($kind, 'tulisan')
            || str_contains($kind, 'makalah')
        ) {
            return [
                'kind' => 'journal',
                'name' => 'Jurnal Nasional Terakreditasi',
                'index' => 'SINTA',
                'classification' => $classification ?: 'Nasional',
            ];
        }

        return null;
    }

    private function titleExists(string $title): bool
    {
        return isset($this->cachedTitles[mb_strtolower(trim($title))]);
    }

    /**
     * @param  array<string, mixed>  $publication
     */
    private function publicationDate(array $publication): ?string
    {
        $rawDate = $this->stringValue($this->firstValue($publication, [
            'tanggal_terbit',
            'tanggal',
            'published_at',
            'publication_date',
            'date',
        ]));

        if ($rawDate === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $rawDate, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            return checkdate($month, $day, $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        if (preg_match('~^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$~', $rawDate, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];

            return checkdate($month, $day, $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        $timestamp = strtotime($rawDate);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    /**
     * @param  array<string, mixed>  $publication
     */
    private function publicationYear(array $publication, ?string $date): ?int
    {
        $rawYear = $this->stringValue($this->firstValue($publication, [
            'tahun',
            'tahun_terbit',
            'year',
        ]));

        if (preg_match('/\b(19|20)\d{2}\b/', $rawYear, $matches)) {
            return (int) $matches[0];
        }

        if ($date !== null) {
            return (int) substr($date, 0, 4);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    private function firstValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (
                array_key_exists($key, $data)
                && $data[$key] !== null
                && $data[$key] !== ''
            ) {
                return $data[$key];
            }
        }

        return null;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $string = $this->stringValue($value);

        return $string !== '' ? $string : null;
    }
}
