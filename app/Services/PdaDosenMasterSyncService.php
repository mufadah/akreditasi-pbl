<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\Pendidikan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PdaDosenMasterSyncService
{
    private const DIRECTORY_URL = 'https://pda.polines.ac.id/internal/dosen/list';

    private const DETAIL_URL = 'https://pda.polines.ac.id/dosen';

    private const REQUEST_HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0 Safari/537.36',
        'Referer' => 'https://pda.polines.ac.id/dosen?mode=tetap',
    ];

    /**
     * @return array{
     *     semester: ?string,
     *     d4_trk: int,
     *     d3_ti: int,
     *     mku: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     serdos_available: int,
     *     jabatan_distribution: array<string, int>
     * }
     */
    public function sync(): array
    {
        $lecturers = $this->fetchLecturers();
        $profiles = $this->fetchProfiles($lecturers);
        $semester = $this->latestSemester($profiles);
        $targets = [];

        foreach ($lecturers as $lecturer) {
            $homebase = $this->homebase($lecturer);
            $slug = trim((string) ($lecturer['slug'] ?? ''));
            $profile = $profiles[$slug] ?? [];

            if ($this->isD4Trk($homebase)) {
                $targets[] = ['lecturer' => $lecturer, 'profile' => $profile, 'status' => 'DTPS', 'category' => 'd4_trk'];

                continue;
            }

            if ($this->isD3Ti($homebase)) {
                $targets[] = ['lecturer' => $lecturer, 'profile' => $profile, 'status' => 'DTPS', 'category' => 'd3_ti'];

                continue;
            }

            if ($this->teachesMku($profile, $semester)) {
                $targets[] = ['lecturer' => $lecturer, 'profile' => $profile, 'status' => 'DTT', 'category' => 'mku'];
            }
        }

        return DB::transaction(function () use ($targets, $semester): array {
            $result = [
                'semester' => $semester,
                'd4_trk' => 0,
                'd3_ti' => 0,
                'mku' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'serdos_available' => 0,
                'jabatan_distribution' => [],
            ];

            foreach ($targets as $target) {
                $lecturer = $target['lecturer'];
                $profile = $target['profile'];
                $identity = $this->identity($lecturer);

                if ($identity === null) {
                    $result['skipped']++;

                    continue;
                }

                $existing = $this->findExistingDosen($identity['nidn'], $identity['nip']);
                $education = $this->resolveEducation(
                    $this->educationDetails($lecturer, $profile),
                    $existing
                );

                if ($education === null && $existing === null) {
                    $result['skipped']++;

                    continue;
                }

                $jobTitle = $this->jobTitle($lecturer, $profile);
                $job = $this->resolveJabatan($jobTitle);
                $name = $this->fullName($lecturer);
                if ($name === '') {
                    $result['skipped']++;

                    continue;
                }

                $email = $this->email($lecturer, $existing, $identity['slug'], $name);
                $data = [
                    'nama_dosen' => $name,
                    'nidn' => $identity['nidn'],
                    'nuptk' => $identity['nip'] !== '' ? $identity['nip'] : null,
                    'email' => $email,
                    'id_jabatan' => $job?->id_jabatan ?? $existing?->id_jabatan,
                    'id_pendidikan' => $education?->id_pendidikan ?? $existing?->id_pendidikan,
                    'status_dosen' => $target['status'],
                ];

                $serdos = $this->sertifikasiValue($lecturer, $profile);
                if ($serdos['available']) {
                    $result['serdos_available']++;
                    $data['sertifikasi'] = $serdos['value'];
                }

                if ($data['id_jabatan'] === null || $data['id_pendidikan'] === null) {
                    $result['skipped']++;

                    continue;
                }

                if ($existing === null) {
                    $dosen = Dosen::query()->create($data);
                    $dosen->recordHistory('PDA');
                    $result['created']++;
                } else {
                    $wasTrashed = $existing->trashed();
                    if ($wasTrashed) {
                        $existing->restore();
                    }

                    $existing->update($data);
                    if ($wasTrashed || $existing->wasChanged([
                        'nama_dosen',
                        'nidn',
                        'nuptk',
                        'email',
                        'id_jabatan',
                        'id_pendidikan',
                        'sertifikasi',
                        'status_dosen',
                    ])) {
                        $existing->recordHistory('PDA');
                    }
                    $result['updated']++;
                }

                $result[$target['category']]++;
                $result['jabatan_distribution'][$job?->nama_jabatan ?? 'Tenaga Pengajar']
                    = ($result['jabatan_distribution'][$job?->nama_jabatan ?? 'Tenaga Pengajar'] ?? 0) + 1;
            }

            ksort($result['jabatan_distribution']);

            return $result;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLecturers(): array
    {
        $lecturers = [];
        $page = 1;
        $lastPage = 1;

        do {
            $response = Http::withHeaders(self::REQUEST_HEADERS)
                ->acceptJson()
                ->timeout(30)
                ->get(self::DIRECTORY_URL, ['limit' => 500, 'page' => $page]);

            if (! $response->successful()) {
                throw new RuntimeException("PDA mengembalikan HTTP {$response->status()} saat mengambil direktori dosen.");
            }

            $payload = $response->json();
            if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
                throw new RuntimeException('Format direktori dosen PDA tidak berisi daftar data yang dikenali.');
            }

            foreach ($payload['data'] as $lecturer) {
                if (is_array($lecturer)) {
                    $lecturers[] = $lecturer;
                }
            }

            $lastPage = max(1, (int) ($payload['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage);

        return $lecturers;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lecturers
     * @return array<string, array<string, mixed>>
     */
    private function fetchProfiles(array $lecturers): array
    {
        $slugs = array_values(array_unique(array_filter(array_map(
            fn (array $lecturer): string => trim((string) ($lecturer['slug'] ?? '')),
            $lecturers
        ))));
        $profiles = [];
        $errors = [];

        foreach (array_chunk($slugs, 20) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk): array {
                return array_map(
                    fn (string $slug) => $pool->as($slug)
                        ->withHeaders(self::REQUEST_HEADERS)
                        ->timeout(25)
                        ->get(self::DETAIL_URL.'/'.rawurlencode($slug)),
                    $chunk
                );
            });

            foreach ($chunk as $slug) {
                $response = $responses[$slug] ?? null;
                if (! $response instanceof Response || ! $response->successful()) {
                    $status = $response instanceof Response ? "HTTP {$response->status()}" : 'respons tidak valid';
                    $errors[] = "{$slug} ({$status})";

                    continue;
                }

                $props = $response->json('props');
                if (! is_array($props)) {
                    $props = $this->inertiaProps($response->body());
                }

                if ($props === null) {
                    $errors[] = "{$slug} (data profil tidak dikenali)";

                    continue;
                }

                $profiles[$slug] = $props;
            }
        }

        if ($errors !== []) {
            $sampleErrors = implode(', ', array_slice($errors, 0, 8));
            $remaining = count($errors) > 8 ? ' dan '.(count($errors) - 8).' profil lainnya' : '';

            throw new RuntimeException('Gagal mengambil profil publik PDA: '.$sampleErrors.$remaining.'. Sinkronisasi dibatalkan.');
        }

        return $profiles;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function inertiaProps(string $html): ?array
    {
        if (! preg_match('/data-page="([^"]+)"/', $html, $matches)) {
            return null;
        }

        $page = json_decode(
            html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            true
        );

        return is_array($page) && is_array($page['props'] ?? null) ? $page['props'] : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $profiles
     */
    private function latestSemester(array $profiles): ?string
    {
        $semesters = [];

        foreach ($profiles as $profile) {
            $name = trim((string) ($profile['namaSemester'] ?? ''));
            if ($name !== '') {
                $semesters[] = $name;
            }

            foreach (($profile['pengajaranTerbaru'] ?? []) as $teaching) {
                if (is_array($teaching)) {
                    $semester = trim((string) ($teaching['semester'] ?? ''));
                    if ($semester !== '') {
                        $semesters[] = $semester;
                    }
                }
            }
        }

        $ranked = [];
        foreach (array_unique($semesters) as $semester) {
            if (preg_match('/((?:19|20)\d{2})\s*\/\s*(?:19|20)\d{2}\s*(Ganjil|Genap)/i', $semester, $matches)) {
                $rank = ((int) $matches[1] * 2) + (strtolower($matches[2]) === 'genap' ? 1 : 0);
                $ranked[$rank] = $semester;
            }
        }

        if ($ranked === []) {
            return null;
        }

        ksort($ranked);

        return end($ranked) ?: null;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function teachesMku(array $profile, ?string $semester): bool
    {
        if ($semester === null || ! is_array($profile['pengajaranTerbaru'] ?? null)) {
            return false;
        }

        foreach ($profile['pengajaranTerbaru'] as $teaching) {
            if (! is_array($teaching)
                || mb_strtolower(trim((string) ($teaching['semester'] ?? ''))) !== mb_strtolower($semester)) {
                continue;
            }

            $course = mb_strtolower(trim((string) ($teaching['mata_kuliah'] ?? '')));
            $class = trim((string) ($teaching['kelas'] ?? ''));
            $isMku = collect([
                'pancasila',
                'pendidikan kewarganegaraan',
                'kewarganegaraan',
                'pkn',
                'bahasa indonesia',
                'bhs indonesia',
                'bahasa inggris',
                'bhs inggris',
                'pendidikan agama',
                'agama islam',
                'agama kristen',
                'agama katolik',
                'agama hindu',
                'agama buddha',
            ])->contains(fn (string $keyword): bool => str_contains($course, $keyword));

            if ($isMku && preg_match('/^(?:TI|IK)\s*-/i', $class) === 1) {
                return true;
            }
        }

        return false;
    }

    private function homebase(array $lecturer): string
    {
        foreach ([
            'effective_homebase_prodi',
            'effective_homebase_prodi_nama',
            'homebase_prodi',
            'homebase_prodi_nama',
            'Homebase_Prodi',
        ] as $key) {
            $value = trim((string) ($lecturer[$key] ?? ''));
            if ($value !== '') {
                return mb_strtolower($value);
            }
        }

        return '';
    }

    private function isD4Trk(string $homebase): bool
    {
        return str_contains($homebase, 'teknologi rekayasa komputer');
    }

    private function isD3Ti(string $homebase): bool
    {
        return str_contains($homebase, 'teknik informatika');
    }

    /**
     * @return array{nidn: string, nip: string, slug: string}|null
     */
    private function identity(array $lecturer): ?array
    {
        $nidn = trim((string) ($lecturer['nidn'] ?? $lecturer['NIDN'] ?? ''));
        $nip = trim((string) ($lecturer['nip'] ?? $lecturer['NIP'] ?? ''));

        if ($nidn === '' && $nip === '') {
            return null;
        }

        return [
            'nidn' => $nidn !== '' ? $nidn : 'NIP_'.$nip,
            'nip' => $nip,
            'slug' => trim((string) ($lecturer['slug'] ?? '')),
        ];
    }

    private function findExistingDosen(string $nidn, string $nip): ?Dosen
    {
        return Dosen::withTrashed()
            ->where(function (Builder $query) use ($nidn, $nip): void {
                $query->where('nidn', $nidn);
                if ($nip !== '') {
                    $query->orWhere('nuptk', $nip);
                }
            })
            ->first();
    }

    private function fullName(array $lecturer): string
    {
        $parts = array_filter([
            trim((string) ($lecturer['gelar_depan'] ?? '')),
            trim((string) ($lecturer['nama'] ?? $lecturer['Nama_dosen'] ?? '')),
            trim((string) ($lecturer['gelar_belakang'] ?? '')),
        ]);
        $name = preg_replace('/\s+/', ' ', implode(' ', $parts));

        return trim((string) preg_replace('/\s+,\s+/', ', ', (string) $name));
    }

    private function jobTitle(array $lecturer, array $profile): string
    {
        foreach ([$profile['jabatan'] ?? null, $lecturer['jabatan_fungsional'] ?? null, $lecturer['jabatan_akademik'] ?? null] as $title) {
            if (is_scalar($title) && trim((string) $title) !== '') {
                return trim((string) $title);
            }
        }

        return 'Tenaga Pengajar';
    }

    private function resolveJabatan(string $title): ?JabatanAkademik
    {
        $normalized = mb_strtolower(trim($title));
        $aliases = match ($normalized) {
            'profesor' => ['profesor', 'guru besar'],
            'guru besar' => ['guru besar', 'profesor'],
            default => [$normalized],
        };
        $placeholders = implode(', ', array_fill(0, count($aliases), '?'));

        return JabatanAkademik::query()
            ->whereRaw("LOWER(TRIM(nama_jabatan)) IN ({$placeholders})", $aliases)
            ->first()
            ?? JabatanAkademik::query()->create(['nama_jabatan' => $title]);
    }

    /**
     * @return array{jenjang: string, universitas: string, program_studi: string, tahun_lulus: int}|null
     */
    private function educationDetails(array $lecturer, array $profile): ?array
    {
        $formal = $profile['pendidikanFormals'] ?? [];
        $candidates = [];
        if (is_array($formal)) {
            foreach ($formal as $education) {
                if (! is_array($education)) {
                    continue;
                }

                $level = $this->normalizeEducationLevel($education['jenjang'] ?? null);
                if ($level === null) {
                    continue;
                }

                $candidates[] = [
                    'jenjang' => $level,
                    'universitas' => trim((string) ($education['nama_perguruan_tinggi'] ?? $education['universitas'] ?? '')),
                    'program_studi' => trim((string) ($education['bidang_studi'] ?? $education['program_studi'] ?? '')),
                    'tahun_lulus' => (int) ($education['tahun_lulus'] ?? 0),
                ];
            }
        }

        usort(
            $candidates,
            fn (array $left, array $right): int => $this->educationRank($right['jenjang']) <=> $this->educationRank($left['jenjang'])
        );

        if ($candidates !== []) {
            return $candidates[0];
        }

        $level = $this->normalizeEducationLevel($lecturer['pendidikan_terakhir'] ?? null);

        return $level === null ? null : [
            'jenjang' => $level,
            'universitas' => '',
            'program_studi' => '',
            'tahun_lulus' => 0,
        ];
    }

    private function resolveEducation(?array $details, ?Dosen $existing): ?Pendidikan
    {
        if ($details === null) {
            return $existing?->pendidikan;
        }

        $level = $details['jenjang'];
        if ($details['universitas'] !== ''
            && $details['program_studi'] !== ''
            && $details['tahun_lulus'] > 0) {
            return Pendidikan::query()->firstOrCreate([
                'jenjang' => $level,
                'universitas' => $details['universitas'],
                'program_studi' => $details['program_studi'],
                'tahun_lulus' => $details['tahun_lulus'],
            ]);
        }

        $existingLevel = Pendidikan::query()
            ->whereRaw('LOWER(TRIM(jenjang)) = ?', [mb_strtolower($level)])
            ->orderByDesc('tahun_lulus')
            ->first();

        if ($existingLevel !== null) {
            return $existingLevel;
        }

        if ($existing !== null && mb_strtolower(trim((string) $existing->pendidikan?->jenjang)) === mb_strtolower($level)) {
            return $existing->pendidikan;
        }

        return null;
    }

    private function normalizeEducationLevel(mixed $level): ?string
    {
        if (! is_scalar($level)) {
            return null;
        }

        $normalized = mb_strtoupper(trim((string) $level));
        if (preg_match('/\bS\s*3\b|\bDOKTOR\b|\bDOKTORAL\b/', $normalized) === 1) {
            return 'S3';
        }
        if (preg_match('/\bS\s*2\b|\bMAGISTER\b/', $normalized) === 1) {
            return 'S2';
        }
        if (preg_match('/\bD\s*4\b|\bSARJANA TERAPAN\b/', $normalized) === 1) {
            return 'D4/S1';
        }
        if (preg_match('/\bS\s*1\b|\bSARJANA\b/', $normalized) === 1) {
            return 'S1';
        }
        if (preg_match('/\bD\s*3\b|\bDIPLOMA III\b/', $normalized) === 1) {
            return 'D3';
        }

        return null;
    }

    private function educationRank(string $level): int
    {
        return match ($level) {
            'S3' => 5,
            'S2' => 4,
            'S1', 'D4/S1' => 3,
            'D3' => 2,
            default => 0,
        };
    }

    /**
     * @return array{available: bool, value: ?string}
     */
    private function sertifikasiValue(array $lecturer, array $profile): array
    {
        foreach (['sertifikasi_dosen', 'status_serdos', 'serdos', 'sertifikasi'] as $key) {
            $value = $profile[$key] ?? $lecturer[$key] ?? null;
            if (is_bool($value)) {
                return ['available' => true, 'value' => $value ? 'Tersertifikasi' : 'Belum Tersertifikasi'];
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                return ['available' => true, 'value' => trim((string) $value)];
            }
        }

        return ['available' => false, 'value' => null];
    }

    private function email(array $lecturer, ?Dosen $existing, string $slug, string $name): string
    {
        $sourceEmail = trim((string) ($lecturer['email_polines'] ?? $lecturer['email'] ?? ''));

        return $sourceEmail !== ''
            ? $sourceEmail
            : ($existing?->email ?? (($slug !== '' ? $slug : 'dosen_'.substr(md5($name), 0, 8)).'@polines.ac.id'));
    }
}
