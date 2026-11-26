<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\Pendidikan;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\ConnectionException;
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
     *     updated_jabatan: int,
     *     created_trk: int,
     *     created_ti: int,
     *     created_mku: int,
     *     unmatched: int,
     *     protected: int,
     *     jabatan_distribution: array<string, int>,
     *     d4_trk: int,
     *     d3_ti: int,
     *     mku: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     serdos_available: int
     * }
     */
    public function sync(): array
    {
        $lecturers = $this->fetchLecturers();
        $existingDosen = Dosen::withTrashed()->with('jabatanAkademik')->get();
        $indexes = $this->buildIndexes($existingDosen);
        $updates = [];
        $newTrk = [];
        $newTi = [];
        $outsideCandidates = [];
        $unmatched = 0;
        $protected = 0;

        foreach ($lecturers as $lecturer) {
            $match = $this->findExistingDosen($lecturer, $indexes);
            if ($match['ambiguous']) {
                if ($this->isTargetHomebase($lecturer)) {
                    $unmatched++;
                }

                continue;
            }

            if ($match['dosen'] !== null) {
                $attributes = $this->missingHomebaseAttributes($lecturer, $match['dosen']);
                $positionName = $this->normalizePosition(
                    $lecturer['jabatan_fungsional'] ?? $lecturer['jabatan_akademik'] ?? null
                );
                $position = $positionName !== null ? $this->findPosition($positionName) : null;
                $currentName = trim((string) $match['dosen']->jabatanAkademik?->nama_jabatan);
                $needsEnrichment = $currentName === ''
                    || mb_strtolower($currentName) === 'tenaga pengajar';

                if ($needsEnrichment && $position !== null && $position->id_jabatan !== $match['dosen']->id_jabatan) {
                    $attributes['id_jabatan'] = $position->id_jabatan;
                } elseif ($needsEnrichment && $positionName !== null && $position === null) {
                    $unmatched++;
                } elseif (! $needsEnrichment) {
                    $protected++;
                }

                if ($attributes !== []) {
                    $updates[] = [
                        'dosen' => $match['dosen'],
                        'attributes' => $attributes,
                        'position' => $position,
                    ];
                }

                continue;
            }

            $identity = $this->identity($lecturer);
            if ($identity === null) {
                if ($this->isTargetHomebase($lecturer)) {
                    $unmatched++;
                }

                continue;
            }

            if ($this->isD4Trk($lecturer)) {
                $newTrk[] = $lecturer;
            } elseif ($this->isD3Ti($lecturer)) {
                $newTi[] = $lecturer;
            } else {
                $outsideCandidates[] = $lecturer;
            }
        }

        $profileCandidates = $outsideCandidates;
        if (isset($lecturers[0]['slug'])) {
            $profileCandidates[] = $lecturers[0];
        }

        [$profiles] = $this->fetchProfiles($profileCandidates);
        $semester = $this->latestSemester($profiles);
        $newMku = [];

        foreach ($outsideCandidates as $lecturer) {
            $slug = trim((string) ($lecturer['slug'] ?? ''));
            if (! isset($profiles[$slug])) {
                $unmatched++;

                continue;
            }

            if ($this->teachesMku($profiles[$slug], $semester)) {
                $newMku[] = $lecturer;
            }
        }

        return DB::transaction(function () use ($updates, $newTrk, $newTi, $newMku, $indexes, $semester, $unmatched, $protected): array {
            $result = [
                'semester' => $semester,
                'updated_jabatan' => 0,
                'created_trk' => 0,
                'created_ti' => 0,
                'created_mku' => 0,
                'unmatched' => $unmatched,
                'protected' => $protected,
                'updated_homebase' => 0,
                'jabatan_distribution' => [],
            ];

            foreach ($updates as $update) {
                $dosen = $update['dosen'];
                $dosen->update($update['attributes']);

                if ($dosen->wasChanged('id_jabatan')) {
                    $dosen->unsetRelation('jabatanAkademik');
                    $dosen->recordHistory('PDA');
                    $result['updated_jabatan']++;
                    $this->addPositionCount($result['jabatan_distribution'], $update['position']->nama_jabatan);
                }

                if ($dosen->wasChanged(['jurusan', 'prodi'])) {
                    $result['updated_homebase']++;
                }
            }

            foreach ([
                ['lecturers' => $newTrk, 'category' => 'created_trk', 'status' => 'DTPS'],
                ['lecturers' => $newTi, 'category' => 'created_ti', 'status' => 'DTPS'],
                ['lecturers' => $newMku, 'category' => 'created_mku', 'status' => 'DTT'],
            ] as $group) {
                foreach ($group['lecturers'] as $lecturer) {
                    if ($this->createDosen($lecturer, $group['status'], $indexes, $result['jabatan_distribution'])) {
                        $result[$group['category']]++;
                    } else {
                        $result['unmatched']++;
                    }
                }
            }

            ksort($result['jabatan_distribution']);

            $result += [
                'd4_trk' => $result['created_trk'],
                'd3_ti' => $result['created_ti'],
                'mku' => $result['created_mku'],
                'created' => $result['created_trk'] + $result['created_ti'] + $result['created_mku'],
                'updated' => $result['updated_jabatan'] + $result['updated_homebase'],
                'skipped' => $result['unmatched'],
                'serdos_available' => 0,
            ];

            return $result;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLecturers(): array
    {
        $response = Http::withHeaders(self::REQUEST_HEADERS)
            ->acceptJson()
            ->timeout(30)
            ->get(self::DIRECTORY_URL, ['limit' => 500]);

        if (! $response->successful()) {
            throw new RuntimeException("PDA mengembalikan HTTP {$response->status()} saat mengambil direktori dosen.");
        }

        $payload = $response->json();
        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            throw new RuntimeException('Format direktori dosen PDA tidak berisi daftar data yang dikenali.');
        }

        return array_values(array_filter($payload['data'], 'is_array'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lecturers
     * @return array{0: array<string, array<string, mixed>>, 1: array<int, string>}
     */
    private function fetchProfiles(array $lecturers): array
    {
        $profiles = [];
        $failures = [];
        $seenSlugs = [];

        foreach ($lecturers as $lecturer) {
            $slug = trim((string) ($lecturer['slug'] ?? ''));
            if ($slug === '') {
                $failures[] = 'slug profil kosong';

                continue;
            }
            if (isset($seenSlugs[$slug])) {
                continue;
            }
            $seenSlugs[$slug] = true;

            usleep(300000);

            try {
                $response = Http::withHeaders(self::REQUEST_HEADERS)
                    ->timeout(20)
                    ->get(self::DETAIL_URL.'/'.rawurlencode($slug));
            } catch (ConnectionException $exception) {
                $failures[] = "{$slug} ({$exception->getMessage()})";

                continue;
            }

            if (! $response->successful()) {
                $failures[] = "{$slug} (HTTP {$response->status()})";

                continue;
            }

            $props = $response->json('props');
            if (! is_array($props)) {
                if (! preg_match('/data-page="([^"]+)"/', $response->body(), $matches)) {
                    $failures[] = "{$slug} (data profil tidak dikenali)";

                    continue;
                }

                $page = json_decode(
                    html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    true
                );
                $props = is_array($page) ? ($page['props'] ?? null) : null;
            }

            if (! is_array($props)) {
                $failures[] = "{$slug} (data profil tidak dikenali)";

                continue;
            }

            $profiles[$slug] = $props;
        }

        if ($failures !== []) {
            logger()->warning('Sebagian profil dosen PDA tidak dapat dibaca.', [
                'count' => count($failures),
                'samples' => array_slice($failures, 0, 10),
            ]);
        }

        return [$profiles, $failures];
    }

    /**
     * @param  EloquentCollection<int, Dosen>  $dosen
     * @return array{nip: array<string, array<int, Dosen>>, nidn: array<string, array<int, Dosen>>, name: array<string, array<int, Dosen>>}
     */
    private function buildIndexes(EloquentCollection $dosen): array
    {
        $indexes = ['nip' => [], 'nidn' => [], 'name' => []];

        foreach ($dosen as $lecturer) {
            $this->indexDosen($lecturer, $indexes);
        }

        return $indexes;
    }

    /**
     * @param  array{nip: array<string, array<int, Dosen>>, nidn: array<string, array<int, Dosen>>, name: array<string, array<int, Dosen>>}  $indexes
     */
    private function indexDosen(Dosen $dosen, array &$indexes): void
    {
        $nip = $this->normalizeIdentifier($dosen->nuptk);
        if ($nip !== '') {
            $indexes['nip'][$nip][] = $dosen;
        }

        $nidn = $this->normalizeIdentifier($dosen->nidn);
        if ($nidn !== '' && ! str_starts_with($nidn, 'nip_') && ! str_starts_with($nidn, 'pda_')) {
            $indexes['nidn'][$nidn][] = $dosen;
        }

        $name = $this->normalizeName($dosen->nama_dosen);
        if ($name !== '') {
            $indexes['name'][$name][] = $dosen;
        }
    }

    /**
     * @param  array{nip: array<string, array<int, Dosen>>, nidn: array<string, array<int, Dosen>>, name: array<string, array<int, Dosen>>}  $indexes
     * @return array{dosen: ?Dosen, ambiguous: bool}
     */
    private function findExistingDosen(array $lecturer, array $indexes): array
    {
        $identity = $this->identity($lecturer);
        $checks = [
            ['index' => 'nip', 'value' => $this->normalizeIdentifier($identity['nip'] ?? '')],
            ['index' => 'nidn', 'value' => $this->normalizeIdentifier($identity['nidn'] ?? '')],
            ['index' => 'name', 'value' => $this->normalizeName($lecturer['nama'] ?? $lecturer['Nama_dosen'] ?? '')],
        ];

        foreach ($checks as $check) {
            if ($check['value'] === '' || ! isset($indexes[$check['index']][$check['value']])) {
                continue;
            }

            $matches = $indexes[$check['index']][$check['value']];
            if (count($matches) !== 1) {
                return ['dosen' => null, 'ambiguous' => true];
            }

            return ['dosen' => $matches[0], 'ambiguous' => false];
        }

        return ['dosen' => null, 'ambiguous' => false];
    }

    /**
     * @param  array<string, mixed>  $lecturer
     * @return array{nip: string, nidn: string, name: string}|null
     */
    private function identity(array $lecturer): ?array
    {
        $nip = trim((string) ($lecturer['nip'] ?? $lecturer['NIP'] ?? ''));
        $nidn = trim((string) ($lecturer['nidn'] ?? $lecturer['NIDN'] ?? ''));
        $name = trim((string) ($lecturer['nama'] ?? $lecturer['Nama_dosen'] ?? ''));

        return $nip === '' && $nidn === '' && $name === ''
            ? null
            : ['nip' => $nip, 'nidn' => $nidn, 'name' => $name];
    }

    private function normalizeIdentifier(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function normalizeName(mixed $value): string
    {
        $name = mb_strtolower(trim((string) $value));
        $name = preg_replace(
            '/\b(?:prof(?:esor)?|drs?|dra|ir|ph\.?\s*d|eng|s\.?\s*(?:kom|ti|t|si|pd|e|h|s|ag)|m\.?\s*(?:kom|ti|t|si|pd|sc|eng|ag))\.?\b/iu',
            ' ',
            $name
        );
        $name = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', (string) $name);

        return trim(preg_replace('/\s+/', ' ', (string) $name));
    }

    /**
     * @param  array<string, mixed>  $lecturer
     */
    private function isTargetHomebase(array $lecturer): bool
    {
        return $this->isD4Trk($lecturer) || $this->isD3Ti($lecturer);
    }

    private function isD4Trk(array $lecturer): bool
    {
        return str_contains($this->homebase($lecturer), 'teknologi rekayasa komputer');
    }

    private function isD3Ti(array $lecturer): bool
    {
        return str_contains($this->homebase($lecturer), 'teknik informatika');
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

    private function normalizePosition(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim((string) $value));
        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        return match ($normalized) {
            'asisten ahli' => 'Asisten Ahli',
            'lektor' => 'Lektor',
            'lektor kepala' => 'Lektor Kepala',
            'guru besar', 'profesor' => 'Guru Besar',
            'tenaga pengajar' => 'Tenaga Pengajar',
            default => null,
        };
    }

    private function findPosition(string $positionName): ?JabatanAkademik
    {
        $aliases = $positionName === 'Guru Besar'
            ? ['guru besar', 'profesor']
            : [mb_strtolower($positionName)];
        $placeholders = implode(', ', array_fill(0, count($aliases), '?'));

        return JabatanAkademik::query()
            ->whereRaw("LOWER(TRIM(nama_jabatan)) IN ({$placeholders})", $aliases)
            ->orderByRaw('CASE WHEN LOWER(TRIM(nama_jabatan)) = ? THEN 0 ELSE 1 END', [mb_strtolower($positionName)])
            ->first();
    }

    /**
     * @param  array<string, array<int, Dosen>>  $indexes
     * @param  array<string, int>  $distribution
     */
    private function createDosen(array $lecturer, string $status, array &$indexes, array &$distribution): bool
    {
        $identity = $this->identity($lecturer);
        $name = $this->fullName($lecturer);
        $positionName = $this->normalizePosition($lecturer['jabatan_fungsional'] ?? $lecturer['jabatan_akademik'] ?? null);
        $position = $positionName !== null ? $this->findPosition($positionName) : null;
        $educationId = $this->educationId($lecturer['pendidikan_terakhir'] ?? null);

        if ($identity === null
            || ($identity['nip'] === '' && $identity['nidn'] === '')
            || $name === ''
            || $position === null
            || $educationId === null) {
            return false;
        }

        $nidn = trim($identity['nidn']);
        if ($nidn === '') {
            $nip = trim($identity['nip']);
            $nidn = 'NIP_'.$nip;
        }

        $email = $this->newEmail($lecturer, $name);
        $dosen = Dosen::query()->firstOrCreate(
            ['nidn' => $nidn],
            [
                'nuptk' => trim($identity['nip']) !== '' ? trim($identity['nip']) : null,
                'nama_dosen' => $name,
                'email' => $email,
                'id_jabatan' => $position->id_jabatan,
                'id_pendidikan' => $educationId,
                'status_dosen' => $status,
                ...$this->homebaseAttributes($lecturer),
            ]
        );

        if (! $dosen->wasRecentlyCreated) {
            $this->indexDosen($dosen, $indexes);

            return false;
        }

        $this->indexDosen($dosen, $indexes);
        $dosen->recordHistory('PDA');
        $this->addPositionCount($distribution, $position->nama_jabatan);

        return true;
    }

    private function educationId(mixed $level): ?int
    {
        $normalized = mb_strtolower(trim((string) $level));
        if ($normalized === '') {
            return null;
        }

        $aliases = match ($normalized) {
            's3', 'doktor' => ['s3'],
            's2', 'magister' => ['s2'],
            's1', 'sarjana', 'd4', 'sarjana terapan' => ['s1', 'd4/s1', 'd4', 'sarjana terapan'],
            'd3', 'diploma iii' => ['d3', 'diploma iii'],
            default => [$normalized],
        };
        $placeholders = implode(', ', array_fill(0, count($aliases), '?'));

        $education = Pendidikan::query()
            ->whereRaw("LOWER(TRIM(jenjang)) IN ({$placeholders})", $aliases)
            ->orderByDesc('tahun_lulus')
            ->first();

        if ($education !== null) {
            return $education->id_pendidikan;
        }

        if (! in_array($normalized, ['s1', 'sarjana', 'd4', 'sarjana terapan', 'd3', 'diploma iii', 's2', 'magister', 's3', 'doktor'], true)) {
            return null;
        }

        return Pendidikan::query()->firstOrCreate([
            'jenjang' => $aliases[0],
            'universitas' => 'Tidak diketahui',
            'program_studi' => 'Tidak diketahui',
            'tahun_lulus' => 0,
        ])->id_pendidikan;
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

    /**
     * @return array{jurusan?: string, prodi?: string}
     */
    private function missingHomebaseAttributes(array $lecturer, Dosen $dosen): array
    {
        $attributes = $this->homebaseAttributes($lecturer);

        foreach (['jurusan', 'prodi'] as $field) {
            if (trim((string) $dosen->getAttribute($field)) !== '' || ! isset($attributes[$field])) {
                unset($attributes[$field]);
            }
        }

        return $attributes;
    }

    /**
     * @return array{jurusan?: string, prodi?: string}
     */
    private function homebaseAttributes(array $lecturer): array
    {
        $attributes = [];
        $jurusan = trim((string) (
            $lecturer['effective_homebase_jurusan']
            ?? $lecturer['homebase_jurusan']
            ?? $lecturer['Jurusan']
            ?? $lecturer['jurusan']
            ?? ''
        ));

        if ($jurusan !== '') {
            $attributes['jurusan'] = $jurusan;
        }

        $prodi = trim((string) (
            $lecturer['effective_homebase_prodi_nama']
            ?? $lecturer['homebase_prodi_nama']
            ?? $lecturer['effective_homebase_prodi']
            ?? $lecturer['homebase_prodi']
            ?? $lecturer['Homebase_Prodi']
            ?? ''
        ));

        if ($prodi === '') {
            return $attributes;
        }

        $level = trim((string) ($lecturer['jenjang'] ?? ''));
        if (preg_match('/^(D[1-4]|S[1-3])\s*[-–]?\s*(.+)$/iu', $prodi, $matches)) {
            $level = $level !== '' ? $level : mb_strtoupper($matches[1]);
            $prodi = trim($matches[2]);
        }

        $attributes['prodi'] = $level !== ''
            ? mb_strtoupper($level).' - '.$prodi
            : $prodi;

        return $attributes;
    }

    private function newEmail(array $lecturer, string $name): string
    {
        $email = trim((string) ($lecturer['email_polines'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false && ! Dosen::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->exists()) {
            return $email;
        }

        $slug = trim((string) ($lecturer['slug'] ?? ''));
        $base = ($slug !== '' ? $slug : 'dosen-'.substr(sha1($this->normalizeName($name)), 0, 12)).'@pda.polines.ac.id';
        $email = $base;
        $suffix = 1;

        while (Dosen::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->exists()) {
            $email = str_replace('@pda.polines.ac.id', "-{$suffix}@pda.polines.ac.id", $base);
            $suffix++;
        }

        return $email;
    }

    /**
     * @param  array<int, array<string, mixed>>  $profiles
     */
    private function latestSemester(array $profiles): ?string
    {
        $semesters = [];
        foreach ($profiles as $profile) {
            $semester = trim((string) ($profile['namaSemester'] ?? ''));
            if ($semester !== '') {
                $semesters[] = $semester;
            }

            foreach (($profile['pengajaranTerbaru'] ?? []) as $teaching) {
                if (is_array($teaching) && trim((string) ($teaching['semester'] ?? '')) !== '') {
                    $semesters[] = trim((string) $teaching['semester']);
                }
            }
        }

        $ranked = [];
        foreach (array_unique($semesters) as $semester) {
            if (preg_match('/((?:19|20)\d{2})\s*\/\s*(?:19|20)\d{2}\s*(Ganjil|Genap)/i', $semester, $matches)) {
                $rank = (int) $matches[1] * 2 + (strtolower($matches[2]) === 'genap' ? 1 : 0);
                $ranked[$rank] = $semester;
            }
        }

        if ($ranked === []) {
            return null;
        }

        ksort($ranked);

        return end($ranked) ?: null;
    }

    private function teachesMku(array $profile, ?string $semester): bool
    {
        if ($semester === null || ! is_array($profile['pengajaranTerbaru'] ?? null)) {
            return false;
        }

        $keywords = [
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
        ];

        foreach ($profile['pengajaranTerbaru'] as $teaching) {
            if (! is_array($teaching)
                || mb_strtolower(trim((string) ($teaching['semester'] ?? ''))) !== mb_strtolower($semester)
                || preg_match('/^(?:TI|IK)\s*-/i', trim((string) ($teaching['kelas'] ?? ''))) !== 1) {
                continue;
            }

            $course = mb_strtolower(trim((string) ($teaching['mata_kuliah'] ?? '')));
            foreach ($keywords as $keyword) {
                if (str_contains($course, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $distribution
     */
    private function addPositionCount(array &$distribution, string $position): void
    {
        $distribution[$position] = ($distribution[$position] ?? 0) + 1;
    }
}
