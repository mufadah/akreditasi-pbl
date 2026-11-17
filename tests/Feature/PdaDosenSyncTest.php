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

test('pda sync updates functional positions and imports target homebases and current semester mku teachers', function () {
    $defaultPosition = JabatanAkademik::where('nama_jabatan', 'Tenaga Pengajar')->firstOrFail();
    $oldEducation = Pendidikan::where('jenjang', 'S2')->firstOrFail();
    $existing = Dosen::create([
        'id_jabatan' => $defaultPosition->id_jabatan,
        'id_pendidikan' => $oldEducation->id_pendidikan,
        'nama_dosen' => 'Nama Lama',
        'nidn' => '1111111111',
        'nuptk' => '198001012005011001',
        'email' => 'old@example.test',
        'status_dosen' => 'DTPS',
        'sertifikasi' => 'Tersertifikasi',
    ]);

    $lecturers = [
        [
            'nama' => 'Dosen TRK',
            'nidn' => '1111111111',
            'nip' => '198001012005011001',
            'email_polines' => 'trk@polines.ac.id',
            'slug' => 'dosen-trk',
            'jabatan_fungsional' => 'Lektor',
            'pendidikan_terakhir' => 'S3',
            'homebase_prodi_nama' => 'Teknologi Rekayasa Komputer',
        ],
        [
            'nama' => 'Dosen TI',
            'nidn' => '2222222222',
            'nip' => '198101012006011002',
            'email_polines' => 'ti@polines.ac.id',
            'slug' => 'dosen-ti',
            'jabatan_fungsional' => 'Lektor',
            'pendidikan_terakhir' => 'S2',
            'homebase_prodi_nama' => 'Teknik Informatika',
        ],
        [
            'nama' => 'Pengampu MKU',
            'nidn' => '3333333333',
            'nip' => '198201012007011003',
            'email_polines' => 'mku@polines.ac.id',
            'slug' => 'pengampu-mku',
            'jabatan_fungsional' => 'Lektor Kepala',
            'pendidikan_terakhir' => 'S2',
            'homebase_prodi_nama' => 'Akuntansi',
        ],
        [
            'nama' => 'Pengampu Semester Lama',
            'nidn' => '4444444444',
            'nip' => '198301012008011004',
            'email_polines' => 'old-semester@polines.ac.id',
            'slug' => 'pengampu-semester-lama',
            'jabatan_fungsional' => 'Asisten Ahli',
            'pendidikan_terakhir' => 'S2',
            'homebase_prodi_nama' => 'Akuntansi',
        ],
    ];

    $profiles = [
        'dosen-trk' => [
            'jabatan' => 'lektor',
            'namaSemester' => '2026/2027 Ganjil',
            'pendidikanFormals' => [[
                'jenjang' => 'S3',
                'nama_perguruan_tinggi' => 'Universitas Uji',
                'bidang_studi' => 'Ilmu Komputer',
                'tahun_lulus' => '2024',
            ]],
        ],
        'dosen-ti' => [
            'jabatan' => 'Lektor',
            'namaSemester' => '2026/2027 Ganjil',
            'pendidikanFormals' => [[
                'jenjang' => 'S2',
                'nama_perguruan_tinggi' => 'Universitas Uji',
                'bidang_studi' => 'Teknik Informatika',
                'tahun_lulus' => '2023',
            ]],
        ],
        'pengampu-mku' => [
            'jabatan' => 'Lektor Kepala',
            'namaSemester' => '2026/2027 Ganjil',
            'pendidikanFormals' => [[
                'jenjang' => 'S2',
                'nama_perguruan_tinggi' => 'Universitas Uji',
                'bidang_studi' => 'Pendidikan Bahasa Inggris',
                'tahun_lulus' => '2022',
            ]],
            'pengajaranTerbaru' => [
                ['semester' => '2026/2027 Ganjil', 'kelas' => 'TI-1A', 'mata_kuliah' => 'Bahasa Inggris II'],
                ['semester' => '2025/2026 Genap', 'kelas' => 'IK-1A', 'mata_kuliah' => 'Pendidikan Agama'],
            ],
        ],
        'pengampu-semester-lama' => [
            'jabatan' => 'Asisten Ahli',
            'namaSemester' => '2025/2026 Genap',
            'pendidikanFormals' => [[
                'jenjang' => 'S2',
                'nama_perguruan_tinggi' => 'Universitas Uji',
                'bidang_studi' => 'Pendidikan Agama',
                'tahun_lulus' => '2021',
            ]],
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
        ->assertSuccessful();

    expect($existing->fresh()->jabatanAkademik->nama_jabatan)->toBe('Lektor')
        ->and($existing->fresh()->pendidikan->jenjang)->toBe('S3')
        ->and($existing->fresh()->sertifikasi)->toBe('Tersertifikasi')
        ->and($existing->fresh()->status_dosen)->toBe('DTPS');
    expect(JabatanAkademik::whereRaw('LOWER(nama_jabatan) = ?', ['lektor'])->count())->toBe(1);

    $this->assertDatabaseHas('dosen_histories', [
        'id_dosen' => $existing->id_dosen,
        'sumber_data' => 'PDA',
        'jabatan_akademik' => 'Lektor',
        'pendidikan_terakhir' => 'S3',
        'sertifikasi_dosen' => 'Tersertifikasi',
    ]);
    $this->assertDatabaseHas('dosen', ['nidn' => '2222222222', 'status_dosen' => 'DTPS']);
    $this->assertDatabaseHas('dosen', ['nidn' => '3333333333', 'status_dosen' => 'DTT']);
    $this->assertDatabaseMissing('dosen', ['nidn' => '4444444444']);

    $historyCount = DosenHistory::where('sumber_data', 'PDA')->count();
    $this->artisan('pda:sync-dosen')->assertSuccessful();

    expect(DosenHistory::where('sumber_data', 'PDA')->count())->toBe($historyCount);
    $this->assertDatabaseCount('dosen', 12);
});

test('pda sync fails explicitly when the public directory is unavailable', function () {
    Http::fake([
        'https://pda.polines.ac.id/internal/dosen/list*' => Http::response([], 503),
    ]);

    $this->artisan('pda:sync-dosen')
        ->expectsOutput('PDA mengembalikan HTTP 503 saat mengambil direktori dosen.')
        ->assertFailed();
});
