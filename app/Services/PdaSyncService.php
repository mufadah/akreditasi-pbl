<?php

namespace App\Services;

use App\Models\AnggotaKegiatan;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\JenisKegiatan;
use App\Models\JenisLuaran;
use App\Models\Kegiatan;
use App\Models\Luaran;
use App\Models\Pendidikan;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PdaSyncService
{
    protected string $pdaBaseUrl = 'https://pda.polines.ac.id/dosen';

    protected array $pdaHeaders = [
        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => '84dd22c9ccf465fddc4ae4edcf19656a',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    /**
     * Cache in-memory mapping slug dosen.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $slugMapping = null;

    /**
     * Ambil detail data portofolio dosen langsung dari PDA Polines via Inertia JSON endpoint atau HTML data-page.
     */
    public function fetchLecturerFromPda(string $slug): ?array
    {
        try {
            $url = "{$this->pdaBaseUrl}/{$slug}";
            $response = Http::withOptions([
                'version' => 2.0,
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'id,en-US;q=0.7,en;q=0.3',
            ])->timeout(20)->get($url);

            if (! $response->successful()) {
                return null;
            }

            // Coba ambil dari JSON langsung jika Inertia request
            $props = $response->json('props');

            // Fallback: ekstrak data-page dari HTML jika menerima full HTML
            if (! is_array($props)) {
                if (preg_match('/data-page="([^"]+)"/', $response->body(), $matches)) {
                    $pageData = json_decode(html_entity_decode($matches[1]), true);
                    $props = $pageData['props'] ?? [];
                }
            }

            if (! is_array($props)) {
                return null;
            }

            return [
                'slug' => $slug,
                'fullName' => $props['fullName'] ?? null,
                'jabatan' => $props['jabatan'] ?? null,
                'pendidikan' => $props['pendidikanFormals'] ?? [],
                'penelitian' => $props['allPenelitian'] ?? [],
                'pengabdian' => $props['allPengabdians'] ?? [],
                'publikasi' => $props['allPublikasis'] ?? [],
                'hki' => $props['allHakis'] ?? [],
                'sintaScore' => $props['sintaScore'] ?? null,
                'scopusH' => $props['scopusH'] ?? null,
                'scholarH' => $props['scholarH'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning("Gagal fetch data PDA untuk slug [{$slug}]: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Impor atau sinkronisasi daftar master dosen dari data PDA Polines ke tabel dosen.
     *
     * @return array{total_dataset: int, created: int, updated: int, skipped: int}
     */
    public function importDosenMasterList(?string $prodi = null, bool $includePengajarLuar = true, bool $liveScan = false): array
    {
        if ($prodi !== null && $prodi !== '') {
            $list = $this->fetchLecturersListFromPda($prodi);
            foreach ($list as &$item) {
                $item['status_dosen'] = 'DTPS';
            }
            unset($item);

            $isTrk = str_contains(strtolower($prodi), 'rekayasa komputer');
            if ($isTrk && $includePengajarLuar) {
                $pengajarLuar = $this->fetchOutsideTrkTeachingLecturers($liveScan);
                $list = array_merge($list, $pengajarLuar);
            }
        } else {
            $this->ensureSlugMappingLoaded();
            $list = $this->slugMapping['list'] ?? [];

            if (empty($list)) {
                $list = $this->fetchLecturersListFromPda();
            }

            foreach ($list as &$item) {
                $item['status_dosen'] = 'DTPS';
            }
            unset($item);
        }

        if (empty($list)) {
            return [
                'total_dataset' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];
        }

        // Ambil default jabatan dan pendidikan
        $defaultJabatan = JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Tenaga Pengajar']);
        $defaultPendidikanS2 = Pendidikan::firstOrCreate(
            ['jenjang' => 'S2', 'program_studi' => 'Magister Terapan'],
            ['universitas' => 'Politeknik Negeri Semarang', 'tahun_lulus' => 2020]
        );
        $pendidikanS3 = Pendidikan::where('jenjang', 'S3')->first() ?? $defaultPendidikanS2;

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($list as $item) {
            $rawNama = trim((string) ($item['Nama_dosen'] ?? ''));
            if ($rawNama === '') {
                $skipped++;

                continue;
            }

            $gelarDepan = trim((string) ($item['gelar_depan'] ?? ''));
            $gelarBelakang = trim((string) ($item['gelar_belakang'] ?? ''));

            $fullNameParts = array_filter([$gelarDepan, $rawNama, $gelarBelakang]);
            $formattedName = implode(' ', $fullNameParts);
            $formattedName = preg_replace('/\s+,\s+/', ', ', $formattedName);
            $formattedName = preg_replace('/\s+/', ' ', trim($formattedName));

            $rawNidn = trim((string) ($item['NIDN'] ?? ''));
            $rawNip = trim((string) ($item['NIP'] ?? ''));
            $slug = trim((string) ($item['slug'] ?? ''));

            $nidn = $rawNidn !== '' ? $rawNidn : ($rawNip !== '' ? 'NIP_'.$rawNip : 'PDA_'.substr(md5($formattedName), 0, 10));
            $email = ($slug !== '' ? $slug : 'dosen_'.substr(md5($formattedName), 0, 8)).'@polines.ac.id';

            $isS3 = (bool) preg_match('/\b(dr|ph\.d|doktor|prof)\b/i', $formattedName);
            $idPendidikan = $isS3 ? $pendidikanS3->id_pendidikan : $defaultPendidikanS2->id_pendidikan;

            $existing = Dosen::withTrashed()
                ->where('nidn', $nidn)
                ->orWhere('email', $email)
                ->when($rawNip !== '', fn ($q) => $q->orWhere('nuptk', $rawNip))
                ->first();

            $targetStatus = $item['status_dosen'] ?? ($existing ? $existing->status_dosen : 'DTPS');

            $data = [
                'nama_dosen' => $formattedName,
                'nidn' => $nidn,
                'nuptk' => $rawNip !== '' ? $rawNip : null,
                'email' => $existing ? $existing->email : $email,
                'id_jabatan' => $existing ? $existing->id_jabatan : $defaultJabatan->id_jabatan,
                'id_pendidikan' => $existing ? $existing->id_pendidikan : $idPendidikan,
                'status_dosen' => $targetStatus,
            ];

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update($data);
                $updated++;
            } else {
                Dosen::create($data);
                $created++;
            }
        }

        return [
            'total_dataset' => count($list),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Cari slug dosen di PDA Polines berdasarkan NIDN atau Nama Dosen.
     */
    public function resolvePdaSlug(Dosen $dosen): ?string
    {
        $this->ensureSlugMappingLoaded();

        $nidn = trim((string) ($dosen->nidn ?? ''));
        $nama = strtolower(trim((string) ($dosen->nama_dosen ?? '')));

        // 1. Cek langsung via NIDN
        if ($nidn !== '' && isset($this->slugMapping['by_nidn'][$nidn])) {
            return $this->slugMapping['by_nidn'][$nidn];
        }

        // 2. Bersihkan gelar nama (dr, ir, s.t., m.t., dsb)
        $cleanName = preg_replace('/\b(dr|ir|prof|s\.t|m\.t|s\.pd|m\.pd|s\.kom|m\.kom|s\.st|m\.eng|m\.sc|s\.si|m\.s\.i|ph\.d)\b/i', '', $nama);
        $cleanName = trim(preg_replace('/[.,]/', ' ', (string) $cleanName));
        $cleanName = preg_replace('/\s+/', ' ', (string) $cleanName);

        if ($cleanName !== '' && isset($this->slugMapping['by_name'][$cleanName])) {
            return $this->slugMapping['by_name'][$cleanName];
        }

        // 3. Fuzzy search substring pada list nama
        if (! empty($this->slugMapping['list'])) {
            foreach ($this->slugMapping['list'] as $item) {
                $pdaNama = strtolower(trim($item['Nama_dosen'] ?? ''));
                if ($cleanName !== '' && (str_contains($pdaNama, (string) $cleanName) || str_contains((string) $cleanName, $pdaNama))) {
                    return $item['slug'] ?? null;
                }
            }
        }

        // 4. Fallback slugify nama
        $fallbackSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $cleanName));
        $fallbackSlug = trim((string) $fallbackSlug, '-');

        return ! empty($fallbackSlug) ? $fallbackSlug : null;
    }

    /**
     * Ambil seluruh direktori dosen langsung dari PDA secara live (opsional filter prodi).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchLecturersListFromPda(?string $prodi = null): array
    {
        try {
            $firstPage = $this->fetchLecturerPage(1, $prodi);
            if (! $firstPage) {
                return [];
            }

            $lastPage = (int) ($firstPage['last_page'] ?? 1);
            $allDosen = $firstPage['data'] ?? [];

            for ($page = 2; $page <= $lastPage; $page++) {
                $p = $this->fetchLecturerPage($page, $prodi);
                if ($p && ! empty($p['data'])) {
                    $allDosen = array_merge($allDosen, $p['data']);
                }
            }

            return $allDosen;
        } catch (\Throwable $e) {
            Log::warning('Gagal fetch daftar dosen live dari PDA: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Ambil daftar dosen dari luar prodi TRK yang memiliki penugasan mengajar di kelas TRK (kode kelas TI-).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchOutsideTrkTeachingLecturers(bool $liveScan = false): array
    {
        // ponytail: daftar slug terverifikasi pengajar kelas TRK (TI-) dari prodi/jurusan lain
        $fallbackSlugs = [
            'afandi-nur-aziz-thohari',
            'aisyatul-karima',
            'amran-yobioktabera',
            'arif-fitra-setyawan',
            'filda-hulwani-dewi',
            'idhawati-hestiningsih',
            'iswanti',
            'muttabik-fathul-lathief',
            'netty-nurdiyani',
            'nur-hidayati',
            'sirli-fahriah',
            'slamet-handoko',
            'sukamto',
        ];

        try {
            $response = Http::withOptions(['version' => 2.0])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Referer' => 'https://pda.polines.ac.id/dosen',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])->timeout(10)->get('https://pda.polines.ac.id/internal/dosen/list?limit=500');

            if (! $response->successful()) {
                return $this->formatLecturersFromSlugs($fallbackSlugs);
            }

            $allDosen = $response->json('data') ?? [];
            $dosenBySlug = [];
            $candidates = [];

            foreach ($allDosen as $d) {
                $slug = (string) ($d['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $dosenBySlug[$slug] = $d;

                $prodi = (string) ($d['effective_homebase_prodi_nama'] ?? $d['homebase_prodi_nama'] ?? '');
                if (! str_contains(strtolower($prodi), 'rekayasa komputer')) {
                    $candidates[] = $slug;
                }
            }

            $matchedSlugs = $fallbackSlugs;

            if ($liveScan) {
                $chunks = array_chunk($candidates, 40);
                $liveMatched = [];

                foreach ($chunks as $chunk) {
                    $responses = Http::pool(fn ($pool) => array_map(fn ($s) => $pool->as($s)->withOptions(['version' => 2.0])
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'])
                        ->timeout(10)->get("{$this->pdaBaseUrl}/{$s}"), $chunk)
                    );

                    foreach ($responses as $slug => $resp) {
                        if ($resp instanceof Response && $resp->successful()) {
                            if (preg_match('/data-page="([^"]+)"/', $resp->body(), $m)) {
                                $pData = json_decode(html_entity_decode($m[1]), true);
                                $pengajaran = $pData['props']['pengajaranTerbaru'] ?? [];
                                foreach ($pengajaran as $p) {
                                    if (str_starts_with((string) ($p['kelas'] ?? ''), 'TI-')) {
                                        $liveMatched[] = $slug;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }

                if (! empty($liveMatched)) {
                    $matchedSlugs = array_values(array_unique($liveMatched));
                }
            }

            $results = [];
            foreach ($matchedSlugs as $slug) {
                $raw = $dosenBySlug[$slug] ?? null;
                if ($raw) {
                    $results[] = [
                        'Nama_dosen' => $raw['nama'] ?? '',
                        'gelar_depan' => $raw['gelar_depan'] ?? '',
                        'gelar_belakang' => $raw['gelar_belakang'] ?? '',
                        'NIDN' => $raw['nidn'] ?? '',
                        'NIP' => $raw['nip'] ?? '',
                        'slug' => $slug,
                        'status_dosen' => 'Tidak Tetap',
                    ];
                }
            }

            return ! empty($results) ? $results : $this->formatLecturersFromSlugs($fallbackSlugs);
        } catch (\Throwable $e) {
            Log::warning('Gagal memindai dosen pengajar luar TRK: '.$e->getMessage());

            return $this->formatLecturersFromSlugs($fallbackSlugs);
        }
    }

    /**
     * Format fallback daftar dosen pengajar dari slug.
     *
     * @param  array<int, string>  $slugs
     * @return array<int, array<string, mixed>>
     */
    protected function formatLecturersFromSlugs(array $slugs): array
    {
        return array_map(fn ($slug) => [
            'Nama_dosen' => ucwords(str_replace('-', ' ', $slug)),
            'gelar_depan' => '',
            'gelar_belakang' => '',
            'NIDN' => '',
            'NIP' => '',
            'slug' => $slug,
            'status_dosen' => 'Tidak Tetap',
        ], $slugs);
    }

    /**
     * Ambil data satu halaman tabel dosen PDA Polines.
     *
     * @return array{total?: int, last_page?: int, data?: array<int, array<string, mixed>>}|null
     */
    protected function fetchLecturerPage(int $page, ?string $prodi = null): ?array
    {
        try {
            $url = "{$this->pdaBaseUrl}?mode=tabel&page={$page}";
            if ($prodi !== null && $prodi !== '') {
                $url .= '&prodi='.urlencode($prodi);
            }

            $response = Http::withOptions(['version' => 2.0])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'id,en-US;q=0.7,en;q=0.3',
                ])->timeout(15)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $props = $response->json('props.listDosen');
            if (! is_array($props)) {
                if (preg_match('/data-page="([^"]+)"/', $response->body(), $m)) {
                    $pageData = json_decode(html_entity_decode($m[1]), true);
                    $props = $pageData['props']['listDosen'] ?? null;
                }
            }

            return is_array($props) ? $props : null;
        } catch (\Throwable $e) {
            Log::warning("Gagal fetch tabel dosen PDA halaman [{$page}]: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Muat dataset mapping dosen dari scraper lokal jika tersedia.
     */
    protected function ensureSlugMappingLoaded(): void
    {
        if ($this->slugMapping !== null) {
            return;
        }

        $candidates = [
            storage_path('app/dosen_polines.json'),
            base_path('dosen_polines.json'),
            '/home/fayra/Documents/pda-scraper/dosen_polines.json',
            base_path('../pda-scraper/dosen_polines.json'),
        ];

        $list = [];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $raw = file_get_contents($path);
                $list = json_decode((string) $raw, true) ?? [];
                if (! empty($list)) {
                    break;
                }
            }
        }

        $byNidn = [];
        $byName = [];

        foreach ($list as $item) {
            $slug = $item['slug'] ?? null;
            if (! $slug) {
                continue;
            }

            if (! empty($item['NIDN'])) {
                $byNidn[trim((string) $item['NIDN'])] = $slug;
            }

            $nama = strtolower(trim((string) ($item['Nama_dosen'] ?? '')));
            $clean = preg_replace('/\b(dr|ir|prof|s\.t|m\.t|s\.pd|m\.pd|s\.kom|m\.kom|s\.st|m\.eng|m\.sc|s\.si|m\.s\.i|ph\.d)\b/i', '', $nama);
            $clean = trim(preg_replace('/[.,]/', ' ', (string) $clean));
            $clean = preg_replace('/\s+/', ' ', (string) $clean);

            if ($clean !== '') {
                $byName[$clean] = $slug;
            }
        }

        $this->slugMapping = [
            'list' => $list,
            'by_nidn' => $byNidn,
            'by_name' => $byName,
        ];
    }

    /**
     * Sinkronkan portofolio Tridharma dari PDA Polines langsung ke database backend.
     *
     * @return array{success: bool, message: string, synced: array{penelitian: int, pengabdian: int, publikasi: int, hki: int}, errors: array<string>}
     */
    public function syncDosen(int|string $dosenId): array
    {
        $dosen = Dosen::find($dosenId);
        if (! $dosen) {
            return [
                'success' => false,
                'message' => "Dosen dengan ID [{$dosenId}] tidak ditemukan.",
                'synced' => ['penelitian' => 0, 'pengabdian' => 0, 'publikasi' => 0, 'hki' => 0],
                'errors' => ['Dosen tidak ditemukan'],
            ];
        }

        $slug = $this->resolvePdaSlug($dosen);
        if (! $slug) {
            return [
                'success' => false,
                'message' => "Profil PDA Polines tidak dapat ditemukan untuk dosen [{$dosen->nama_dosen}].",
                'synced' => ['penelitian' => 0, 'pengabdian' => 0, 'publikasi' => 0, 'hki' => 0],
                'errors' => ['Tidak dapat memetakan slug PDA'],
            ];
        }

        $pdaDetail = $this->fetchLecturerFromPda($slug);
        if (! $pdaDetail) {
            return [
                'success' => false,
                'message' => "Gagal mengambil data dari PDA Polines untuk profil [{$slug}].",
                'synced' => ['penelitian' => 0, 'pengabdian' => 0, 'publikasi' => 0, 'hki' => 0],
                'errors' => ['Koneksi ke pda.polines.ac.id gagal atau proteksi bot aktif'],
            ];
        }

        $synced = [
            'penelitian' => 0,
            'pengabdian' => 0,
            'publikasi' => 0,
            'hki' => 0,
        ];
        $errors = [];

        // Ambil ID jenis kegiatan & jenis luaran dinamis
        $idPenelitian = JenisKegiatan::where('nama_jenis_kegiatan', 'like', '%penelitian%')->value('id_jenis_kegiatan') ?? 1;
        $idPkm = JenisKegiatan::where('nama_jenis_kegiatan', 'like', '%pkm%')->value('id_jenis_kegiatan') ?? 2;
        $idJurnalScopus = JenisLuaran::where('nama_jenis_luaran', 'like', '%jurnal%')->where('indeks', 'Scopus')->value('id_jenis_luaran') ?? 2;
        $idJurnalSinta = JenisLuaran::where('nama_jenis_luaran', 'like', '%jurnal%')->where('indeks', 'Sinta')->value('id_jenis_luaran') ?? 3;
        $idProsiding = JenisLuaran::where('nama_jenis_luaran', 'like', '%prosiding%')->value('id_jenis_luaran') ?? 5;
        $idHki = JenisLuaran::where('nama_jenis_luaran', 'like', '%hki%')->value('id_jenis_luaran') ?? 7;

        // Kumpulan judul yang sudah tersimpan di database untuk menghindari duplikasi
        $existingKegiatan = Kegiatan::whereHas('anggotaKegiatan', function ($q) use ($dosen) {
            $q->where('id_dosen', $dosen->id_dosen);
        })->pluck('judul_kegiatan')
            ->map(fn ($j) => strtolower(trim((string) $j)))
            ->all();

        $existingLuaran = Luaran::where('id_dosen', $dosen->id_dosen)
            ->get()
            ->map(fn ($l) => strtolower(trim((string) ($l->nama_jurnal ?? ($l->nama_seminar ?? ($l->judul_hak_cipta ?? ($l->judul_buku ?? '')))))))
            ->filter()
            ->all();

        // 1. Sinkronisasi PENELITIAN
        foreach ($pdaDetail['penelitian'] as $item) {
            $judul = trim((string) ($item['judul'] ?? ''));
            if ($judul === '' || in_array(strtolower($judul), $existingKegiatan, true)) {
                continue;
            }

            $tahun = (int) ($item['tahun_pelaksanaan'] ?? ($item['tahun_kegiatan'] ?? ($item['tahun_usulan'] ?? date('Y'))));
            if ($tahun < 1900 || $tahun > 2100) {
                $tahun = (int) date('Y');
            }

            try {
                DB::transaction(function () use ($dosen, $idPenelitian, $judul, $tahun, $item, &$synced, &$existingKegiatan) {
                    $kegiatan = Kegiatan::create([
                        'id_jenis_kegiatan' => $idPenelitian,
                        'judul_kegiatan' => $judul,
                        'tahun_usulan' => (int) ($item['tahun_usulan'] ?? $tahun),
                        'tahun_kegiatan' => (int) ($item['tahun_kegiatan'] ?? $tahun),
                        'tahun_pelaksanaan' => $tahun,
                        'status_kegiatan' => 'Selesai',
                    ]);

                    AnggotaKegiatan::create([
                        'id_kegiatan' => $kegiatan->id_kegiatan,
                        'jenis_anggota' => 'dosen',
                        'id_dosen' => $dosen->id_dosen,
                        'peran' => 'Ketua',
                    ]);

                    $synced['penelitian']++;
                    $existingKegiatan[] = strtolower($judul);
                });
            } catch (\Throwable $e) {
                $errors[] = "Gagal simpan penelitian [{$judul}]: ".$e->getMessage();
            }
        }

        // 2. Sinkronisasi PENGABDIAN (PkM)
        foreach ($pdaDetail['pengabdian'] as $item) {
            $judul = trim((string) ($item['judul'] ?? ''));
            if ($judul === '' || in_array(strtolower($judul), $existingKegiatan, true)) {
                continue;
            }

            $tahun = (int) ($item['tahun'] ?? date('Y'));
            if ($tahun < 1900 || $tahun > 2100) {
                $tahun = (int) date('Y');
            }

            try {
                DB::transaction(function () use ($dosen, $idPkm, $judul, $tahun, $item, &$synced, &$existingKegiatan) {
                    $kegiatan = Kegiatan::create([
                        'id_jenis_kegiatan' => $idPkm,
                        'judul_kegiatan' => $judul,
                        'lokasi_kegiatan' => $item['lokasi'] ?? 'Semarang',
                        'tahun_usulan' => $tahun,
                        'tahun_kegiatan' => $tahun,
                        'tahun_pelaksanaan' => $tahun,
                        'status_kegiatan' => 'Selesai',
                    ]);

                    AnggotaKegiatan::create([
                        'id_kegiatan' => $kegiatan->id_kegiatan,
                        'jenis_anggota' => 'dosen',
                        'id_dosen' => $dosen->id_dosen,
                        'peran' => 'Ketua',
                    ]);

                    $synced['pengabdian']++;
                    $existingKegiatan[] = strtolower($judul);
                });
            } catch (\Throwable $e) {
                $errors[] = "Gagal simpan pengabdian [{$judul}]: ".$e->getMessage();
            }
        }

        // 3. Sinkronisasi PUBLIKASI
        foreach ($pdaDetail['publikasi'] as $item) {
            $judul = trim((string) ($item['judul'] ?? ''));
            if ($judul === '' || in_array(strtolower($judul), $existingLuaran, true)) {
                continue;
            }

            $jenisPublikasi = strtolower(trim((string) ($item['jenis_publikasi'] ?? '')));
            $isProsiding = str_contains($jenisPublikasi, 'prosiding') || str_contains($jenisPublikasi, 'seminar');

            try {
                if ($isProsiding) {
                    Luaran::create([
                        'id_dosen' => $dosen->id_dosen,
                        'id_jenis_luaran' => $idProsiding,
                        'nama_seminar' => $judul,
                        'nama_prosiding' => ! empty($item['kategori_kegiatan']) ? substr((string) $item['kategori_kegiatan'], 0, 250) : 'Prosiding Seminar',
                    ]);
                } else {
                    $isScopus = str_contains($jenisPublikasi, 'internasional') || str_contains(strtolower((string) ($item['kategori_kegiatan'] ?? '')), 'scopus');
                    Luaran::create([
                        'id_dosen' => $dosen->id_dosen,
                        'id_jenis_luaran' => $isScopus ? $idJurnalScopus : $idJurnalSinta,
                        'nama_jurnal' => $judul,
                        'indeksasi' => $isScopus ? 'Scopus' : 'SINTA',
                    ]);
                }

                $synced['publikasi']++;
                $existingLuaran[] = strtolower($judul);
            } catch (\Throwable $e) {
                $errors[] = "Gagal simpan publikasi [{$judul}]: ".$e->getMessage();
            }
        }

        // 4. Sinkronisasi HKI
        foreach ($pdaDetail['hki'] as $item) {
            $judul = trim((string) ($item['judul'] ?? ''));
            if ($judul === '' || in_array(strtolower($judul), $existingLuaran, true)) {
                continue;
            }

            try {
                Luaran::create([
                    'id_dosen' => $dosen->id_dosen,
                    'id_jenis_luaran' => $idHki,
                    'judul_hak_cipta' => $judul,
                    'jenis_hki' => ! empty($item['jenis']) ? (string) $item['jenis'] : 'Hak Cipta',
                    'status_hki' => 'Bersertifikat',
                ]);

                $synced['hki']++;
                $existingLuaran[] = strtolower($judul);
            } catch (\Throwable $e) {
                $errors[] = "Gagal simpan HKI [{$judul}]: ".$e->getMessage();
            }
        }

        $totalSynced = array_sum($synced);

        return [
            'success' => true,
            'message' => $totalSynced > 0
                ? "Berhasil menyinkronkan {$totalSynced} portofolio dari PDA Polines untuk [{$dosen->nama_dosen}]."
                : "Data Tridharma dosen [{$dosen->nama_dosen}] sudah mutakhir.",
            'synced' => $synced,
            'errors' => $errors,
        ];
    }
}
