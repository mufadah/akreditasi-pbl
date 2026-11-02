<?php

use App\Models\Dosen;
use App\Models\Kegiatan;
use App\Models\Luaran;
use App\Services\PdaSyncService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('pda sync command requires either dosen id or all flag', function () {
    $this->artisan('pda:sync')
        ->expectsOutput('Tentukan opsi --dosen=<id> atau --all untuk sinkronisasi seluruh dosen.')
        ->assertExitCode(1);
});

test('pda sync service inserts portfolio and ignores duplicates', function () {
    $dosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nama_dosen' => 'Achmad Fahrul Aji S.ST., M.T.',
        'nidn' => '0015088501',
        'email' => 'fahrul@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    Http::fake([
        'https://pda.polines.ac.id/dosen/achmad-fahrul-aji*' => Http::response([
            'props' => [
                'fullName' => 'Achmad Fahrul Aji S.ST., M.T.',
                'allPenelitian' => [
                    [
                        'judul' => 'Sistem AI untuk Akreditasi Polines',
                        'tahun_pelaksanaan' => 2024,
                        'tahun_usulan' => 2024,
                    ],
                ],
                'allPengabdians' => [
                    [
                        'judul' => 'Pelatihan IoT UMKM Semarang',
                        'tahun' => 2023,
                        'lokasi' => 'Semarang',
                    ],
                ],
                'allPublikasis' => [
                    [
                        'judul' => 'Deep Learning in Accreditation Evaluation',
                        'jenis_publikasi' => 'Jurnal Internasional Terindeks Scopus',
                    ],
                ],
                'allHakis' => [
                    [
                        'judul' => 'Perangkat Lunak Evaluasi Mutu Otomatis',
                        'jenis' => 'Hak Cipta',
                    ],
                ],
            ],
        ], 200),
    ]);

    $initialKegiatanCount = Kegiatan::count();
    $initialLuaranCount = Luaran::count();

    $service = app(PdaSyncService::class);
    $result = $service->syncDosen($dosen->id_dosen);

    expect($result['success'])->toBeTrue();
    expect($result['synced']['penelitian'])->toBe(1);
    expect($result['synced']['pengabdian'])->toBe(1);
    expect($result['synced']['publikasi'])->toBe(1);
    expect($result['synced']['hki'])->toBe(1);

    expect(Kegiatan::count())->toBe($initialKegiatanCount + 2);
    expect(Luaran::count())->toBe($initialLuaranCount + 2);

    // Jalankan sinkronisasi kedua untuk membuktikan idempotency (tidak duplikat)
    $secondResult = $service->syncDosen($dosen->id_dosen);
    expect($secondResult['success'])->toBeTrue();
    expect($secondResult['synced']['penelitian'])->toBe(0);
    expect($secondResult['synced']['pengabdian'])->toBe(0);
    expect(Kegiatan::count())->toBe($initialKegiatanCount + 2);
    expect(Luaran::count())->toBe($initialLuaranCount + 2);
});

test('pda sync command runs successfully for specific lecturer', function () {
    $dosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nama_dosen' => 'Budi Santoso',
        'nidn' => '9999999999',
        'email' => 'budisantoso99@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    Http::fake([
        'https://pda.polines.ac.id/dosen/*' => Http::response([
            'props' => [
                'fullName' => 'Budi Santoso',
                'allPenelitian' => [],
                'allPengabdians' => [],
                'allPublikasis' => [],
                'allHakis' => [],
            ],
        ], 200),
    ]);

    $this->artisan("pda:sync --dosen={$dosen->id_dosen}")
        ->assertSuccessful();
});

test('pda import-dosen command runs successfully', function () {
    Http::fake([
        'https://pda.polines.ac.id/dosen?mode=tabel*' => Http::response([
            'props' => [
                'listDosen' => [
                    'total' => 1,
                    'last_page' => 1,
                    'data' => [
                        [
                            'Nama_dosen' => 'Dosen Test PDA',
                            'gelar_depan' => 'Dr. ',
                            'gelar_belakang' => 'M.Kom.',
                            'NIDN' => '8888777766',
                            'NIP' => '198001012005011001',
                            'slug' => 'dosen-test-pda',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('pda:import-dosen')
        ->assertSuccessful();
});
