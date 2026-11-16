<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\JenisLuaran;
use App\Models\Luaran;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PdaPublikasiSyncService
{
    private const API_URL = 'https://pda.polines.ac.id/internal/publikasi/list';

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
            $titles = Luaran::query()->whereNotNull($column)->pluck($column)->all();
            foreach ($titles as $t) {
                $norm = mb_strtolower(trim((string) $t));
                if ($norm !== '') {
                    $this->cachedTitles[$norm] = true;
                }
            }
        }
    }

    /**
     * @param  (callable(array{status: string, dosen: Dosen, type: array{kind: string, name: string, index: ?string, classification: string}, year: ?int, title: string, page: int}): void)|null  $onItem
     * @return array{pages: int, created: int, already_exists: int, skipped: int}
     *
     * @throws ConnectionException
     */
    public function sync(int $maxPages, ?string $year = null, ?callable $onItem = null): array
    {
        $this->loadCaches();

        $result = [
            'pages' => 0,
            'created' => 0,
            'already_exists' => 0,
            'skipped' => 0,
        ];

        $consecutiveFailures = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            if ($page > 1) {
                usleep(300000);
            }

            $response = null;
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    $response = Http::withOptions(['version' => 2.0])
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                            'Referer' => 'https://pda.polines.ac.id/publikasi',
                            'X-Requested-With' => 'XMLHttpRequest',
                            'Accept' => 'application/json',
                        ])
                        ->timeout(30)
                        ->get(self::API_URL, [
                            'page' => $page,
                            'limit' => 50,
                            'sort' => 'tanggal',
                            'direction' => 'desc',
                        ]);

                    if ($response->successful()) {
                        break;
                    }
                } catch (\Throwable $e) {
                    // Retry on connection timeout / exception
                }

                if ($attempt < 3) {
                    sleep(1);
                }
            }

            if (! $response || ! $response->successful()) {
                $status = $response ? $response->status() : 'timeout';
                throw new RuntimeException("PDA mengembalikan HTTP {$status} pada halaman {$page}.");
            }

            $items = $this->publicationRows($response->json());
            $result['pages']++;

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                if (! is_array($item) || ! $this->matchesYear($item, $year)) {
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
                $jenisLuaran = $this->cachedJenisLuaran[$cacheKey] ??= JenisLuaran::firstOrCreate([
                    'nama_jenis_luaran' => $type['name'],
                    'indeks' => $type['index'],
                ]);

                $doi = $this->stringValue($this->firstValue($item, ['doi', 'DOI']));
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
                $url = $this->stringValue($this->firstValue($item, $urlFields));
                $data = [
                    'id_dosen' => $dosen->id_dosen,
                    'id_jenis_luaran' => $jenisLuaran->id_jenis_luaran,
                    'tahun_terbit' => $publicationYear,
                ];

                if ($type['kind'] === 'book') {
                    $data += [
                        'judul_buku' => $title,
                        'isbn' => $this->nullableString($this->firstValue($item, ['isbn', 'ISBN'])),
                        'penerbit_buku' => $this->nullableString($this->firstValue($item, ['penerbit', 'publisher'])),
                        'jenis_buku' => str_contains(strtolower($type['classification']), 'monograf') ? 'Buku Monograf' : 'Buku Ajar',
                        'tanggal_terbit' => $date,
                        'tautan_buku' => $url !== '' ? $url : null,
                    ];
                } elseif ($type['kind'] === 'proceedings') {
                    $data += [
                        'nama_seminar' => $this->nullableString($this->firstValue($item, ['nama_seminar', 'seminar'])) ?? $title,
                        'nama_prosiding' => $title,
                        'tanggal_seminar' => $date,
                        'doi_prosiding' => $doi !== '' ? $doi : null,
                        'tautan_seminar' => $url !== '' ? $url : null,
                    ];
                } else {
                    $data += [
                        'nama_jurnal' => $title,
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
        }

        return $result;
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

        throw new RuntimeException('Respons publikasi PDA tidak berisi daftar data yang dikenali.');
    }

    /**
     * @param  array<string, mixed>  $publication
     */
    private function matchesYear(array $publication, ?string $year): bool
    {
        if ($year === null) {
            return true;
        }

        return $this->publicationYear($publication, $this->publicationDate($publication)) === (int) $year;
    }

    /**
     * @param  array<string, mixed>  $publication
     */
    private function findDosen(array $publication): ?Dosen
    {
        if ($this->cachedDosens === null) {
            $this->loadCaches();
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
                $name = trim($name);

                if ($name !== '') {
                    $dosen = $this->cachedDosens->first(function (Dosen $d) use ($name) {
                        return str_contains(mb_strtolower($d->nama_dosen), mb_strtolower($name));
                    });

                    if ($dosen) {
                        return $dosen;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $publication
     * @return array<int, array{nip: string, nidn: string, names: array<int, string>}>
     */
    private function authors(array $publication): array
    {
        $rawAuthors = [];

        if (! empty($publication['dosen']) && is_array($publication['dosen'])) {
            $rawAuthors[] = $publication['dosen'];
        }

        $otherAuthors = $this->firstValue($publication, [
            'authors',
            'author',
            'penulis',
            'daftar_penulis',
            'nama_penulis',
        ]);

        if ($otherAuthors !== null && $otherAuthors !== '') {
            if (is_array($otherAuthors) && is_array($otherAuthors['data'] ?? null) && array_is_list($otherAuthors['data'])) {
                $otherAuthors = $otherAuthors['data'];
            }

            if (! is_array($otherAuthors)) {
                $rawAuthors[] = $otherAuthors;
            } elseif (array_is_list($otherAuthors)) {
                $rawAuthors = array_merge($rawAuthors, $otherAuthors);
            } else {
                $rawAuthors[] = $otherAuthors;
            }
        }

        if (empty($rawAuthors)) {
            $fallback = [
                'nip' => $this->firstValue($publication, ['authorNip', 'author_nip', 'nip_author', 'nip_penulis', 'nuptk']),
                'nidn' => $this->firstValue($publication, ['authorNidn', 'author_nidn', 'nidn_author', 'nidn_penulis']),
                'nama' => $this->firstValue($publication, ['authorNama', 'author_name', 'nama_author', 'nama_dosen']),
            ];
            if (! empty($fallback['nip']) || ! empty($fallback['nidn']) || ! empty($fallback['nama'])) {
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
                        'nip',
                        'NIP',
                        'authorNip',
                        'author_nip',
                        'nip_author',
                        'nip_penulis',
                        'nuptk',
                        'NUPTK',
                    ])),
                    'nidn' => $this->stringValue($this->firstValue($author, [
                        'nidn',
                        'NIDN',
                        'authorNidn',
                        'author_nidn',
                        'nidn_author',
                        'nidn_penulis',
                    ])),
                    'names' => $names ?: [],
                ];

                continue;
            }

            $name = $this->stringValue($author);
            $names = preg_split('/\s*(?:;|,|\bdan\b|&)\s*/iu', $name, -1, PREG_SPLIT_NO_EMPTY);
            $authors[] = ['nip' => '', 'nidn' => '', 'names' => $names ?: []];
        }

        return $authors;
    }

    /**
     * @param  array<string, mixed>  $publication
     * @return array{kind: string, name: string, index: ?string, classification: string}|null
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
            $isInternasional = str_contains($combined, 'internasional');

            return [
                'kind' => 'proceedings',
                'name' => $isInternasional ? 'Prosiding Seminar Internasional' : 'Prosiding Seminar',
                'index' => $isInternasional ? 'IEEE/Scopus' : null,
                'classification' => $classification ?: ($isInternasional ? 'Internasional' : 'Nasional'),
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

        if (str_contains($combined, 'scopus') || str_contains($combined, 'internasional') || preg_match('/\bq[1-4]\b/i', $combined)) {
            return [
                'kind' => 'journal',
                'name' => 'Jurnal Internasional Bereputasi',
                'index' => 'Scopus',
                'classification' => $classification ?: 'Scopus',
            ];
        }

        if (str_contains($combined, 'terakreditasi') || str_contains($combined, 'sinta') || str_contains($combined, 'nasional')) {
            return [
                'kind' => 'journal',
                'name' => 'Jurnal Nasional Terakreditasi',
                'index' => 'SINTA',
                'classification' => $classification ?: 'SINTA',
            ];
        }

        if (str_contains($kind, 'jurnal') || str_contains($kind, 'tulisan') || str_contains($kind, 'makalah')) {
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
        $normalizedTitle = mb_strtolower(trim($title));

        return isset($this->cachedTitles[$normalizedTitle]);
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

            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
        }

        if (preg_match('~^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$~', $rawDate, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];

            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
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

        $rawDate = $this->stringValue($this->firstValue($publication, [
            'tanggal_terbit',
            'tanggal',
            'published_at',
            'publication_date',
            'date',
        ]));
        if (preg_match('/\b(19|20)\d{2}\b/', $rawDate, $matches)) {
            return (int) $matches[0];
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
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
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
