<?php

use App\Enums\PdaVerificationCode;
use App\Enums\PdaVerificationStatus;
use App\Models\Dosen;
use App\Models\Kegiatan;
use App\Models\Luaran;
use App\Models\PdaRecord;
use App\Services\PdaSyncService;
use App\Services\PdaVerification\PdaDuplicateDetector;
use App\Services\PdaVerification\PdaVerificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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
                'jabatan' => 'Lektor',
                'pendidikanFormals' => [
                    ['jenjang' => 'S3'],
                ],
                'sertifikasi_dosen' => 'Tersertifikasi',
                'allPenelitian' => [
                    [
                        'judul' => 'Sistem AI untuk Akreditasi Polines',
                        'tahun_pelaksanaan' => (int) now()->format('Y'),
                        'tanggal_pelaksanaan' => now()->toDateString(),
                    ],
                    [
                        'judul' => 'Penelitian Historis yang Tidak Disinkronkan',
                        'tahun_pelaksanaan' => 2024,
                    ],
                ],
                'allPengabdians' => [
                    [
                        'judul' => 'Pelatihan IoT UMKM Semarang',
                        'tahun' => (int) now()->format('Y'),
                        'tanggal' => now()->toDateString(),
                        'lokasi' => 'Semarang',
                    ],
                ],
                'allPublikasis' => [
                    [
                        'judul' => 'Deep Learning in Accreditation Evaluation',
                        'jenis_publikasi' => 'Jurnal Internasional Terindeks Scopus',
                        'tanggal_terbit' => now()->toDateString(),
                    ],
                    [
                        'judul' => 'Publikasi Dengan Tahun Akademik Aktif',
                        'jenis_publikasi' => 'Jurnal Nasional',
                        'tahun_terbit' => (int) now()->format('Y'),
                    ],
                    [
                        'judul' => 'Publikasi Tanpa Periode yang Tidak Disinkronkan',
                        'jenis_publikasi' => 'Jurnal Nasional',
                    ],
                ],
                'allHakis' => [
                    [
                        'judul' => 'Perangkat Lunak Evaluasi Mutu Otomatis',
                        'jenis' => 'Hak Cipta',
                        'tanggal_pendaftaran' => now()->toDateString(),
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
    expect($result['synced']['publikasi'])->toBe(2);
    expect($result['synced']['hki'])->toBe(1);

    expect(Kegiatan::count())->toBe($initialKegiatanCount + 2);
    expect(Luaran::count())->toBe($initialLuaranCount + 3);
    expect(Kegiatan::where('judul_kegiatan', 'Penelitian Historis yang Tidak Disinkronkan')->exists())->toBeFalse();
    expect(Luaran::where('nama_jurnal', 'Publikasi Tanpa Periode yang Tidak Disinkronkan')->exists())->toBeFalse();
    expect(Luaran::where('nama_jurnal', 'Publikasi Dengan Tahun Akademik Aktif')->exists())->toBeTrue();
    $this->assertDatabaseHas('dosen_histories', [
        'id_dosen' => $dosen->id_dosen,
        'sumber_data' => 'PDA',
        'pendidikan_terakhir' => 'S3',
        'jabatan_akademik' => 'Lektor',
        'sertifikasi_dosen' => 'Tersertifikasi',
    ]);

    // Jalankan sinkronisasi kedua untuk membuktikan idempotency (tidak duplikat)
    $secondResult = $service->syncDosen($dosen->id_dosen);
    expect($secondResult['success'])->toBeTrue();
    expect($secondResult['synced']['penelitian'])->toBe(0);
    expect($secondResult['synced']['pengabdian'])->toBe(0);
    expect(Kegiatan::count())->toBe($initialKegiatanCount + 2);
    expect($secondResult['synced']['publikasi'])->toBe(0);
    expect(Luaran::count())->toBe($initialLuaranCount + 3);
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

test('pda import-dosen command runs successfully with homebase and outside lecturers', function () {
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
        'https://pda.polines.ac.id/internal/dosen/list*' => Http::response([
            'data' => [
                [
                    'nama' => 'Dosen Luar MKU',
                    'gelar_depan' => '',
                    'gelar_belakang' => 'M.Pd.',
                    'nidn' => '7777888899',
                    'nip' => '198501012010011002',
                    'slug' => 'filda-hulwani-dewi',
                    'homebase_prodi_nama' => 'Teknik Informatika',
                ],
            ],
        ], 200),
    ]);

    $this->artisan('pda:import-dosen')
        ->assertSuccessful();

    expect(Dosen::where('nidn', '8888777766')->value('status_dosen'))->toBe('DTPS');
    expect(Dosen::where('nidn', '7777888899')->value('status_dosen'))->toBe('Tidak Tetap');
    $this->assertDatabaseHas('dosen_histories', [
        'id_dosen' => Dosen::where('nidn', '8888777766')->value('id_dosen'),
        'sumber_data' => 'PDA',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validPdaRecord(array $overrides = []): array
{
    return array_merge([
        'judul' => 'Sistem Informasi Akreditasi Berbasis Web',
        'penulis' => 'Budi Santoso',
        'tahun' => '2024',
        'jenis' => 'Jurnal',
        'link_universal' => 'https://jurnal.example.test/artikel/1',
    ], $overrides);
}

function fakePdaLinks(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://jurnal.example.test/*' => Http::response('<html>Artikel publik</html>', 200),
        'https://moved.example.test/old' => Http::response('', 301, ['Location' => '/artikel/baru']),
        'https://moved.example.test/artikel/baru' => Http::response('<html>Artikel publik</html>', 200),
        'https://notfound.example.test/*' => Http::response('Not Found', 404),
        'https://error.example.test/*' => Http::response('Server Error', 500),
        'https://redirect-login.example.test/*' => Http::response('', 302, ['Location' => '/login?next=/artikel']),
    ]);
}

test('pda verification stores a fully valid record as verified', function () {
    fakePdaLinks();

    $report = app(PdaVerificationService::class)->verifyAndStore([
        validPdaRecord(),
        validPdaRecord([
            'judul' => 'Buku Ajar Jaringan Komputer',
            'jenis' => 'buku',
            'tahun' => 2023,
            'link_universal' => 'https://moved.example.test/old',
        ]),
    ]);

    expect($report->passedCount())->toBe(2)
        ->and($report->failedCount())->toBe(0);

    $record = PdaRecord::query()->where('judul', 'Sistem Informasi Akreditasi Berbasis Web')->firstOrFail();
    expect($record->verification_status)->toBe(PdaVerificationStatus::Verified)
        ->and($record->verification_codes)->toBe([])
        ->and($record->verification_notes)->toBeNull()
        ->and($record->verified_at)->not->toBeNull();

    $redirected = PdaRecord::query()->where('judul', 'Buku Ajar Jaringan Komputer')->firstOrFail();
    expect($redirected->verification_status)->toBe(PdaVerificationStatus::Verified)
        ->and($redirected->jenis)->toBe('Buku')
        ->and($redirected->tahun)->toBe('2023');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://moved.example.test/artikel/baru');
});

test('pda verification stores records with missing or invalid fields as verification_failed', function () {
    fakePdaLinks();

    app(PdaVerificationService::class)->verifyAndStore([
        validPdaRecord(['judul' => '   ']),
        validPdaRecord(['judul' => 'Tanpa Penulis', 'penulis' => null]),
        validPdaRecord(['judul' => 'Tahun Dua Digit', 'tahun' => '24']),
        validPdaRecord(['judul' => 'Tahun Huruf', 'tahun' => '20a4']),
        validPdaRecord(['judul' => 'Jenis Tidak Dikenal', 'jenis' => 'Skripsi']),
        validPdaRecord(['judul' => 'Tanpa Link', 'link_universal' => '']),
    ]);

    $failed = PdaRecord::query()->verificationFailed()->get();
    expect($failed)->toHaveCount(6)
        ->and(PdaRecord::query()->verified()->count())->toBe(0);

    foreach ($failed as $record) {
        expect($record->verification_codes)->toBe([PdaVerificationCode::MissingField->value]);
    }

    expect(PdaRecord::query()->whereNull('judul')->value('verification_notes'))->toContain('judul wajib diisi')
        ->and(PdaRecord::query()->where('judul', 'Tanpa Penulis')->value('verification_notes'))->toContain('penulis wajib diisi')
        ->and(PdaRecord::query()->where('judul', 'Tahun Dua Digit')->value('verification_notes'))->toContain('tepat 4 digit')
        ->and(PdaRecord::query()->where('judul', 'Tahun Huruf')->value('verification_notes'))->toContain('tepat 4 digit')
        ->and(PdaRecord::query()->where('judul', 'Jenis Tidak Dikenal')->value('verification_notes'))->toContain('Jurnal, Prosiding, Buku, HKI, Produk')
        ->and(PdaRecord::query()->where('judul', 'Tanpa Link')->value('verification_notes'))->toContain('link universal wajib diisi');
});

test('pda verification flags duplicates against the database and within the same batch', function () {
    fakePdaLinks();

    $existing = PdaRecord::factory()->create([
        'judul' => 'Analisis Data Akreditasi',
        'penulis' => 'Siti Aminah',
        'tahun' => '2024',
        'duplicate_key' => PdaDuplicateDetector::makeKey('Analisis Data Akreditasi', 'Siti Aminah', '2024'),
    ]);
    PdaRecord::factory()->verificationFailed()->create([
        'judul' => 'Riset Yang Pernah Gagal',
        'penulis' => 'Andi',
        'tahun' => '2025',
        'duplicate_key' => PdaDuplicateDetector::makeKey('Riset Yang Pernah Gagal', 'Andi', '2025'),
    ]);

    $report = app(PdaVerificationService::class)->verifyAndStore([
        validPdaRecord(['judul' => '  ANALISIS   data akreditasi ', 'penulis' => 'siti aminah', 'tahun' => 2024]),
        validPdaRecord(['judul' => 'Publikasi Kembar']),
        validPdaRecord(['judul' => 'publikasi kembar ', 'penulis' => ' BUDI SANTOSO']),
        validPdaRecord(['judul' => 'Riset Yang Pernah Gagal', 'penulis' => 'Andi', 'tahun' => '2025']),
    ]);

    [$dbDuplicate, $original, $batchDuplicate, $retried] = $report->results;

    expect($dbDuplicate->codes())->toBe([PdaVerificationCode::Duplicate])
        ->and($dbDuplicate->notes())->toContain("pda_records #{$existing->id}")
        ->and($original->passed())->toBeTrue()
        ->and($batchDuplicate->codes())->toBe([PdaVerificationCode::Duplicate])
        ->and($batchDuplicate->notes())->toContain('record #2 pada batch yang sama')
        ->and($retried->passed())->toBeTrue()
        ->and($report->countByCode(PdaVerificationCode::Duplicate))->toBe(2);

    expect(PdaRecord::query()->find($batchDuplicate->storedId)->verification_status)->toBe(PdaVerificationStatus::VerificationFailed)
        ->and(PdaRecord::query()->find($batchDuplicate->storedId)->verification_codes)->toBe(['DUPLICATE'])
        ->and(PdaRecord::query()->verified()->where('judul', 'Publikasi Kembar')->count())->toBe(1);
});

test('pda verification flags inaccessible, forbidden and malformed links', function () {
    fakePdaLinks();

    $report = app(PdaVerificationService::class)->verifyAndStore([
        validPdaRecord(['judul' => 'Link 404', 'link_universal' => 'https://notfound.example.test/artikel']),
        validPdaRecord(['judul' => 'Link 500', 'link_universal' => 'https://error.example.test/artikel']),
        validPdaRecord(['judul' => 'Redirect Login', 'link_universal' => 'https://redirect-login.example.test/artikel']),
        validPdaRecord(['judul' => 'Google Drive', 'link_universal' => 'https://drive.google.com/file/d/abc123/view']),
        validPdaRecord(['judul' => 'Google Docs', 'link_universal' => 'https://docs.google.com/document/d/abc123']),
        validPdaRecord(['judul' => 'Halaman Dashboard', 'link_universal' => 'https://jurnal.example.test/dashboard/artikel']),
        validPdaRecord(['judul' => 'Halaman Signin', 'link_universal' => 'https://jurnal.example.test/signin']),
        validPdaRecord(['judul' => 'URL Malformed', 'link_universal' => 'htp:/bukan url valid']),
        validPdaRecord(['judul' => 'URL Tanpa Skema', 'link_universal' => 'jurnal.example.test/artikel']),
    ]);

    $codesByTitle = collect($report->results)->mapWithKeys(fn ($result) => [
        $result->record->judul => array_map(fn (PdaVerificationCode $code) => $code->value, $result->codes()),
    ])->all();

    expect($codesByTitle)->toBe([
        'Link 404' => ['INVALID_LINK'],
        'Link 500' => ['INVALID_LINK'],
        'Redirect Login' => ['INVALID_LINK'],
        'Google Drive' => ['INVALID_LINK'],
        'Google Docs' => ['INVALID_LINK'],
        'Halaman Dashboard' => ['INVALID_LINK'],
        'Halaman Signin' => ['INVALID_LINK'],
        'URL Malformed' => ['MALFORMED_LINK'],
        'URL Tanpa Skema' => ['MALFORMED_LINK'],
    ]);

    expect(PdaRecord::query()->where('judul', 'Link 404')->value('verification_notes'))->toContain('HTTP 404')
        ->and(PdaRecord::query()->where('judul', 'Link 500')->value('verification_notes'))->toContain('HTTP 500')
        ->and(PdaRecord::query()->where('judul', 'Redirect Login')->value('verification_notes'))->toContain('/login')
        ->and(PdaRecord::query()->where('judul', 'Google Drive')->value('verification_notes'))->toContain('Google Drive')
        ->and(PdaRecord::query()->verified()->count())->toBe(0);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'google.com')
        || str_contains($request->url(), '/login')
        || str_contains($request->url(), '/dashboard'));
});

test('pda verification report summarises counts, integrity impact and problematic records', function () {
    fakePdaLinks();

    $records = [
        validPdaRecord(),
        validPdaRecord(['penulis' => 'budi santoso']),
        validPdaRecord(['judul' => 'Link Mati', 'link_universal' => 'https://notfound.example.test/x']),
        validPdaRecord(['judul' => 'Link Rusak', 'link_universal' => 'not-a-url']),
        validPdaRecord(['judul' => '']),
    ];

    $dryRun = app(PdaVerificationService::class)->verify($records);
    expect($dryRun->totalRecords())->toBe(5)
        ->and(PdaRecord::query()->count())->toBe(0);

    $report = app(PdaVerificationService::class)->verifyAndStore($records)->toArray();

    expect($report['summary'])->toMatchArray([
        'total_records' => 5,
        'passed_count' => 1,
        'failed_count' => 4,
        'duplicate_count' => 1,
        'invalid_link_count' => 1,
        'missing_field_count' => 1,
        'malformed_link_count' => 1,
    ])
        ->and($report['summary']['data_integrity_impact']['integrity_score'])->toBe(20.0)
        ->and($report['summary']['data_integrity_impact']['level'])->toBe('high')
        ->and($report['summary']['data_integrity_impact']['summary'])->toContain('4 dari 5 record (80%)')
        ->and($report['problematic_records'])->toHaveCount(4)
        ->and(array_column($report['problematic_records'], 'codes'))->toBe([
            ['DUPLICATE'],
            ['INVALID_LINK'],
            ['MALFORMED_LINK'],
            ['MISSING_FIELD'],
        ])
        ->and(array_column($report['problematic_records'], 'status'))->each->toBe('verification_failed')
        ->and($report['problematic_records'][0]['issues'][0])->toHaveKeys(['code', 'field', 'message'])
        ->and(json_encode(app(PdaVerificationService::class)->verify([])))->toBe(
            '{"summary":{"total_records":0,"passed_count":0,"failed_count":0,"duplicate_count":0,"invalid_link_count":0,"missing_field_count":0,"malformed_link_count":0,"data_integrity_impact":{"integrity_score":100,"level":"none","summary":"Tidak ada record yang diproses."}},"problematic_records":[]}'
        );

    expect(PdaRecord::query()->count())->toBe(5)
        ->and(PdaRecord::query()->verified()->count())->toBe(1)
        ->and(PdaRecord::query()->verificationFailed()->count())->toBe(4);
});
