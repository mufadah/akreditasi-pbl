<?php

use App\Models\Dosen;
use App\Models\DosenHistory;
use App\Models\JabatanAkademik;
use App\Models\Pendidikan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('pda enrichment only updates default positions and appends unmatched target lecturers', function () {
    $defaultPosition = JabatanAkademik::where('nama_jabatan', 'Tenaga Pengajar')->firstOrFail();
    $lektorPosition = JabatanAkademik::where('nama_jabatan', 'Lektor')->firstOrFail();
    $education = Pendidikan::where('jenjang', 'S2')->firstOrFail();
    $existingByNip = Dosen::create([
        'id_jabatan' => $defaultPosition->id_jabatan,
        'id_pendidikan' => $education->id_pendidikan,
        'nama_dosen' => 'Nama Lokal Dengan NIP',
        'nidn' => '1000000001',
        'nuptk' => '198001012005011001',
        'email' => 'existing-nip@example.test',
        'status_dosen' => 'DTT',
    ]);
    $existingByNidn = Dosen::create([
        'id_jabatan' => $defaultPosition->id_jabatan,
        'id_pendidikan' => $education->id_pendidikan,
        'nama_dosen' => 'Nama Lokal Dengan NIDN',
        'nidn' => '1000000002',
        'nuptk' => '198101012006011002',
        'email' => 'existing-nidn@example.test',
        'status_dosen' => 'DTT',
    ]);
    $existingByName = Dosen::create([
        'id_jabatan' => $defaultPosition->id_jabatan,
        'id_pendidikan' => $education->id_pendidikan,
        'nama_dosen' => 'Dr. Ir. Nur Contoh, S.T., M.T.',
        'nidn' => '1000000003',
        'nuptk' => '198201012007011003',
        'email' => 'existing-name@example.test',
        'status_dosen' => 'DTPS',
    ]);
    $protectedDosen = Dosen::create([
        'id_jabatan' => $lektorPosition->id_jabatan,
        'id_pendidikan' => $education->id_pendidikan,
        'nama_dosen' => 'Jabatan Sudah Spesifik',
        'nidn' => '1000000004',
        'nuptk' => '198301012008011004',
        'email' => 'protected@example.test',
        'status_dosen' => 'DTT',
    ]);

    $lecturers = [
        [
            'nama' => 'Nama PDA Berbeda',
            'nidn' => '9999999991',
            'nip' => '198001012005011001',
            'email_polines' => 'must-not-overwrite@example.test',
            'slug' => 'match-nip',
            'jabatan_fungsional' => 'Lektor',
            'homebase_prodi_nama' => 'Akuntansi',
        ],
        [
            'nama' => 'Nama PDA Berbeda',
            'nidn' => '1000000002',
            'nip' => '198101019999011002',
            'email_polines' => 'must-not-overwrite-either@example.test',
            'slug' => 'match-nidn',
            'jabatan_fungsional' => 'Asisten Ahli',
            'homebase_prodi_nama' => 'Akuntansi',
        ],
        [
            'nama' => 'NUR CONTOH',
            'nidn' => '9999999993',
            'nip' => '199901019999011003',
            'email_polines' => 'must-not-overwrite-name@example.test',
            'slug' => 'match-name',
            'jabatan_fungsional' => 'Lektor Kepala',
            'homebase_prodi_nama' => 'Akuntansi',
        ],
        [
            'nama' => 'Jabatan Sudah Spesifik',
            'nidn' => '1000000004',
            'nip' => '198301012008011004',
            'slug' => 'protected',
            'jabatan_fungsional' => 'Lektor Kepala',
            'homebase_prodi_nama' => 'Akuntansi',
        ],
        [
            'nama' => 'Dosen Baru TRK',
            'nidn' => '2000000001',
            'nip' => '199001012019031001',
            'email_polines' => 'trk-new@polines.ac.id',
            'slug' => 'new-trk',
            'jabatan_fungsional' => 'Profesor',
            'pendidikan_terakhir' => 'S3',
            'homebase_prodi_nama' => 'Teknologi Rekayasa Komputer',
        ],
        [
            'nama' => 'Dosen Baru TI',
            'nidn' => '2000000002',
            'nip' => '199101012019031002',
            'email_polines' => 'ti-new@polines.ac.id',
            'slug' => 'new-ti',
            'jabatan_fungsional' => 'Lektor',
            'pendidikan_terakhir' => 'S2',
            'homebase_prodi_nama' => 'Teknik Informatika',
        ],
        [
            'nama' => 'Dosen Pengampu Agama',
            'nidn' => '2000000003',
            'nip' => '199201012019031003',
            'email_polines' => 'mku-new@polines.ac.id',
            'slug' => 'new-mku',
            'jabatan_fungsional' => 'Asisten Ahli',
            'pendidikan_terakhir' => 'S2',
            'homebase_prodi_nama' => 'Administrasi Bisnis',
        ],
        [
            'nama' => 'Dosen Semester Lama',
            'nidn' => '2000000004',
            'nip' => '199301012019031004',
            'email_polines' => 'old-mku@polines.ac.id',
            'slug' => 'old-mku',
            'jabatan_fungsional' => 'Lektor',
            'pendidikan_terakhir' => 'S2',
            'homebase_prodi_nama' => 'Administrasi Bisnis',
        ],
    ];

    $profiles = [
        'match-nip' => [
            'namaSemester' => '2026/2027 Ganjil',
        ],
        'new-mku' => [
            'namaSemester' => '2026/2027 Ganjil',
            'pengajaranTerbaru' => [
                ['semester' => '2026/2027 Ganjil', 'kelas' => 'TI-1A', 'mata_kuliah' => 'Pendidikan Agama'],
                ['semester' => '2025/2026 Genap', 'kelas' => 'IK-1A', 'mata_kuliah' => 'Bahasa Indonesia'],
            ],
        ],
        'old-mku' => [
            'namaSemester' => '2025/2026 Genap',
            'pengajaranTerbaru' => [
                ['semester' => '2025/2026 Genap', 'kelas' => 'TI-2A', 'mata_kuliah' => 'Pendidikan Agama'],
            ],
        ],
    ];

    Http::fake([
        'https://pda.polines.ac.id/internal/dosen/list*' => Http::response([
            'data' => $lecturers,
            'last_page' => 1,
        ]),
        'https://pda.polines.ac.id/dosen/*' => function (Request $request) use ($profiles) {
            $slug = basename(parse_url($request->url(), PHP_URL_PATH));
            $html = '<div id="app" data-page="'.htmlspecialchars(
                json_encode(['props' => $profiles[$slug]], JSON_THROW_ON_ERROR),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ).'"></div>';

            return Http::response($html, 200, ['Content-Type' => 'text/html']);
        },
    ]);

    $this->artisan('pda:sync-dosen')
        ->expectsOutput('D4 Teknologi Rekayasa Komputer (DTPS): 1')
        ->expectsOutput('D3 Teknik Informatika (DTPS): 1')
        ->expectsOutput('Pengampu MKU lintas prodi (DTT): 1')
        ->expectsOutput('Ditambahkan: 3 | Diperbarui: 3 | Dilewati: 0')
        ->assertSuccessful();

    expect($existingByNip->fresh()->jabatanAkademik->nama_jabatan)->toBe('Lektor')
        ->and($existingByNip->fresh()->nidn)->toBe('1000000001')
        ->and($existingByNip->fresh()->nama_dosen)->toBe('Nama Lokal Dengan NIP')
        ->and($existingByNip->fresh()->email)->toBe('existing-nip@example.test')
        ->and($existingByNip->fresh()->status_dosen)->toBe('DTT')
        ->and($existingByNidn->fresh()->jabatanAkademik->nama_jabatan)->toBe('Asisten Ahli')
        ->and($existingByNidn->fresh()->nuptk)->toBe('198101012006011002')
        ->and($existingByNidn->fresh()->email)->toBe('existing-nidn@example.test')
        ->and($existingByName->fresh()->jabatanAkademik->nama_jabatan)->toBe('Lektor Kepala')
        ->and($protectedDosen->fresh()->jabatanAkademik->nama_jabatan)->toBe('Lektor');

    $this->assertDatabaseHas('dosen', ['nidn' => '2000000001', 'status_dosen' => 'DTPS']);
    $this->assertDatabaseHas('dosen', ['nidn' => '2000000002', 'status_dosen' => 'DTPS']);
    $this->assertDatabaseHas('dosen', ['nidn' => '2000000003', 'status_dosen' => 'DTT']);
    $this->assertDatabaseMissing('dosen', ['nidn' => '2000000004']);
    $this->assertDatabaseCount('dosen_histories', 6);
    expect(JabatanAkademik::whereRaw('LOWER(nama_jabatan) = ?', ['profesor'])->exists())->toBeFalse();

    $historyCount = DosenHistory::where('sumber_data', 'PDA')->count();
    $this->artisan('pda:sync-dosen')->assertSuccessful();
    expect(DosenHistory::where('sumber_data', 'PDA')->count())->toBe($historyCount);
    $this->assertDatabaseCount('dosen', 16);
});

test('pda enrichment fails explicitly when the public directory is unavailable', function () {
    Http::fake([
        'https://pda.polines.ac.id/internal/dosen/list*' => Http::response([], 503),
    ]);

    $this->artisan('pda:sync-dosen')
        ->expectsOutput('PDA mengembalikan HTTP 503 saat mengambil direktori dosen.')
        ->assertFailed();
});
