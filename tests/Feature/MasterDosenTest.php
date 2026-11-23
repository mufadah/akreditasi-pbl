<?php

use App\Exports\DosenExport;
use App\Models\AnggotaKegiatan;
use App\Models\BidangKeahlian;
use App\Models\Dosen;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $this->adminToken = JWT::encode([
        'sub' => 1,
        'email' => 'administrator@example.com',
        'name' => 'Administrator K1',
        'role' => 'ADMINISTRATOR',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $this->dosenToken = JWT::encode([
        'sub' => 2,
        'email' => 'dosen@example.com',
        'name' => 'Dosen K1',
        'role' => 'DOSEN',
        'exp' => time() + 3600,
    ], $secret, 'HS256');
});

// ==============================================================================
// 1. TAMPILKAN DAFTAR DOSEN (GET /api/dosen)
// ==============================================================================

test('flowchart: buka menu master dosen berhasil menampilkan daftar dosen terpaginasi', function () {
    $response = $this->withToken($this->adminToken)->getJson('/api/dosen');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'success',
            'data' => [
                'data',
                'current_page',
                'total',
            ],
        ]);
});

test('flowchart: tampilkan daftar dosen mendukung filter pencarian berdasarkan nama, nidn, dan nuptk', function () {
    Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nidn' => '9988776655',
        'nuptk' => '1234567890123456',
        'nama_dosen' => 'Dr. Budi Santoso',
        'email' => 'budi.santoso@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    // Cari by NUPTK
    $resNuptk = $this->withToken($this->adminToken)->getJson('/api/dosen?search=1234567890123456');
    $resNuptk->assertStatus(200);
    expect($resNuptk->json('data.data'))->toHaveCount(1)
        ->and($resNuptk->json('data.data.0.nama_dosen'))->toBe('Dr. Budi Santoso');

    // Cari by NIDN
    $resNidn = $this->withToken($this->adminToken)->getJson('/api/dosen?search=9988776655');
    $resNidn->assertStatus(200);
    expect($resNidn->json('data.data'))->toHaveCount(1);

    // Cari by Nama
    $resNama = $this->withToken($this->adminToken)->getJson('/api/dosen?search=Budi');
    $resNama->assertStatus(200);
    expect($resNama->json('data.data'))->toHaveCount(1);
});

// ==============================================================================
// 2. AKSI: INPUT NIDN, NUPTK, NAMA, JABATAN, BIDANG KEAHLIAN, STATUS DTPS/DTT
// ==============================================================================

test('flowchart: aksi tambah dosen manual berhasil menyimpan data ke database dan menampilkan pesan berhasil', function () {
    $bidang = BidangKeahlian::first();

    $payload = [
        'nidn' => '0015088501',
        'nuptk' => '5432167890123456',
        'nama' => 'Dr. Hendra Gunawan, M.T.',
        'jabatan' => 'Lektor',
        'pendidikan' => 'S3',
        'bidang_keahlian' => [$bidang->id_bidang_keahlian],
        'status_dosen' => 'DTPS',
    ];

    $response = $this->withToken($this->adminToken)->postJson('/api/dosen', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Data dosen berhasil ditambahkan.',
            'data' => [
                'nidn' => '0015088501',
                'nuptk' => '5432167890123456',
                'nama_dosen' => 'Dr. Hendra Gunawan, M.T.',
                'status_dosen' => 'DTPS',
            ],
        ]);

    $this->assertDatabaseHas('dosen', [
        'nidn' => '0015088501',
        'nuptk' => '5432167890123456',
        'nama_dosen' => 'Dr. Hendra Gunawan, M.T.',
        'status_dosen' => 'DTPS',
    ]);
});

test('flowchart: tambah dosen dengan status DTT berhasil tersimpan sebagai DTT', function () {
    $payload = [
        'nidn' => '0019098902',
        'nuptk' => '8899001122334455',
        'nama' => 'Ir. Maya Safitri, M.Sc.',
        'status_dosen' => 'DTT',
    ];

    $response = $this->withToken($this->adminToken)->postJson('/api/dosen', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'status_dosen' => 'DTT',
            ],
        ]);

    $this->assertDatabaseHas('dosen', [
        'nidn' => '0019098902',
        'status_dosen' => 'DTT',
    ]);
});

test('flowchart: tambah dosen gagal validasi jika NIDN duplikat', function () {
    Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nidn' => '1122334455',
        'nama_dosen' => 'Dosen Eksis',
        'email' => 'dosen.eksis@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    $response = $this->withToken($this->adminToken)->postJson('/api/dosen', [
        'nidn' => '1122334455',
        'nama' => 'Dosen Duplikat NIDN',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Validasi gagal.',
        ]);
});

test('manual academic changes are historized and summary fields are returned without history rows', function () {
    $response = $this->withToken($this->adminToken)->postJson('/api/dosen', [
        'nidn' => '0066554433',
        'nama_dosen' => 'Dosen Dengan Riwayat Akademik',
        'jabatan_akademik' => 'Lektor',
        'pendidikan_terakhir' => 'S3',
        'sertifikasi_dosen' => 'Tersertifikasi',
        'jurusan' => 'Teknik Elektro',
        'prodi' => 'D4 - Teknologi Rekayasa Komputer',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.jabatan_akademik.nama_jabatan', 'Lektor')
        ->assertJsonPath('data.pendidikan_terakhir', 'S3')
        ->assertJsonPath('data.sertifikasi_dosen', 'Tersertifikasi')
        ->assertJsonPath('data.jurusan', 'Teknik Elektro')
        ->assertJsonPath('data.prodi', 'D4 - Teknologi Rekayasa Komputer');

    $dosen = Dosen::where('nidn', '0066554433')->firstOrFail();
    $this->assertDatabaseHas('dosen_histories', [
        'id_dosen' => $dosen->id_dosen,
        'sumber_data' => 'MANUAL',
        'pendidikan_terakhir' => 'S3',
        'jabatan_akademik' => 'Lektor',
        'sertifikasi_dosen' => 'Tersertifikasi',
    ]);
    $this->assertDatabaseHas('dosen', [
        'id_dosen' => $dosen->id_dosen,
        'jurusan' => 'Teknik Elektro',
        'prodi' => 'D4 - Teknologi Rekayasa Komputer',
    ]);

    $this->withToken($this->adminToken)->putJson("/api/dosen/{$dosen->id_dosen}", [
        'jabatan_akademik' => 'Asisten Ahli',
        'pendidikan_terakhir' => 'S2',
        'sertifikasi_dosen' => 'Belum Tersertifikasi',
        'jurusan' => 'Teknik Elektro',
        'prodi' => 'D4 - Teknologi Rekayasa Komputer',
    ])->assertOk()
        ->assertJsonPath('data.jabatan_akademik.nama_jabatan', 'Asisten Ahli')
        ->assertJsonPath('data.pendidikan_terakhir', 'S2')
        ->assertJsonPath('data.sertifikasi_dosen', 'Belum Tersertifikasi')
        ->assertJsonPath('data.jurusan', 'Teknik Elektro')
        ->assertJsonPath('data.prodi', 'D4 - Teknologi Rekayasa Komputer');

    $this->assertDatabaseCount('dosen_histories', 2);

    $indexResponse = $this->withToken($this->adminToken)->getJson('/api/dosen?search=0066554433');
    $indexResponse->assertOk()
        ->assertJsonPath('data.data.0.pendidikan_terakhir', 'S2')
        ->assertJsonPath('data.data.0.jabatan_akademik.nama_jabatan', 'Asisten Ahli')
        ->assertJsonPath('data.data.0.sertifikasi_dosen', 'Belum Tersertifikasi')
        ->assertJsonPath('data.data.0.jurusan', 'Teknik Elektro')
        ->assertJsonPath('data.data.0.prodi', 'D4 - Teknologi Rekayasa Komputer');
    $this->assertArrayNotHasKey('dosen_histories', $indexResponse->json('data.data.0'));

    $showResponse = $this->withToken($this->adminToken)->getJson("/api/dosen/{$dosen->id_dosen}");
    $showResponse->assertOk()
        ->assertJsonPath('data.pendidikan_terakhir', 'S2')
        ->assertJsonPath('data.jabatan_akademik.nama_jabatan', 'Asisten Ahli')
        ->assertJsonPath('data.sertifikasi_dosen', 'Belum Tersertifikasi')
        ->assertJsonPath('data.jurusan', 'Teknik Elektro')
        ->assertJsonPath('data.prodi', 'D4 - Teknologi Rekayasa Komputer');
    $this->assertArrayNotHasKey('dosen_histories', $showResponse->json('data'));
});

test('jurusan and prodi are nullable strings limited to 255 characters', function () {
    $payload = [
        'nidn' => '0066554434',
        'nama_dosen' => 'Dosen Validasi Homebase',
        'jurusan' => str_repeat('J', 256),
        'prodi' => 'Teknik Informatika',
    ];

    $this->withToken($this->adminToken)->postJson('/api/dosen', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['jurusan']);

    $dosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nidn' => '0066554435',
        'nama_dosen' => 'Dosen Validasi Homebase Update',
        'email' => 'validasi.homebase@example.test',
        'status_dosen' => 'DTPS',
    ]);

    $this->withToken($this->adminToken)->putJson("/api/dosen/{$dosen->id_dosen}", [
        'prodi' => str_repeat('P', 256),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['prodi']);
});

test('dosen export downloads an xlsx file', function () {
    Excel::fake();
    $dosen = Dosen::with(['jabatanAkademik', 'pendidikan'])->firstOrFail();

    $response = $this->withToken($this->adminToken)->get('/api/dosen/export');

    $response->assertOk();
    Excel::assertDownloaded('dosen.xlsx', function (DosenExport $export) use ($dosen): bool {
        $dosen->jurusan = 'Teknik Elektro';
        $dosen->prodi = 'D4 - Teknologi Rekayasa Komputer';

        expect($export->headings())->toBe([
            'NIDN',
            'Nama',
            'Jabatan Akademik',
            'Pendidikan Terakhir',
            'Jurusan',
            'Program Studi',
            'Serdos',
            'Status',
        ])->and($export->map($dosen))->toBe([
            $dosen->nidn,
            $dosen->nama_dosen,
            $dosen->jabatanAkademik?->nama_jabatan,
            $dosen->pendidikan?->jenjang,
            $dosen->jurusan,
            $dosen->prodi,
            $dosen->sertifikasi,
            $dosen->status_dosen,
        ]);

        return true;
    });
});

// ==============================================================================
// 3. AKSI: UPLOAD FILE EXCEL ATAU CSV
// ==============================================================================

test('flowchart: upload file CSV dosen berhasil divalidasi dan disimpan ke database', function () {
    $csvContent = "nidn,nuptk,nama,jabatan,pendidikan,status_dosen\n".
                  "7788990011,1029384756102938,Dosen Import CSV 1,Lektor,S2,DTPS\n".
                  "7788990022,1029384756102939,Dosen Import CSV 2,Asisten Ahli,S3,DTT\n";

    $file = UploadedFile::fake()->createWithContent('dosen_import.csv', $csvContent);

    $response = $this->withToken($this->adminToken)->postJson('/api/dosen/import', [
        'file' => $file,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Data dosen berhasil diimpor dari file Excel atau CSV.',
        ]);

    $this->assertDatabaseHas('dosen', [
        'nidn' => '7788990011',
        'nuptk' => '1029384756102938',
        'nama_dosen' => 'Dosen Import CSV 1',
        'status_dosen' => 'DTPS',
    ]);

    $this->assertDatabaseHas('dosen', [
        'nidn' => '7788990022',
        'nuptk' => '1029384756102939',
        'nama_dosen' => 'Dosen Import CSV 2',
        'status_dosen' => 'DTT',
    ]);
});

// ==============================================================================
// 4. AKSI: UBAH DATA DOSEN
// ==============================================================================

test('flowchart: aksi ubah data dosen berhasil mengupdate database dan menampilkan pesan berhasil', function () {
    $dosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nidn' => '3344556677',
        'nuptk' => '1111222233334444',
        'nama_dosen' => 'Nama Sebelum Update',
        'email' => 'update.test@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    $response = $this->withToken($this->adminToken)->putJson("/api/dosen/{$dosen->id_dosen}", [
        'nama' => 'Nama Setelah Update, Ph.D.',
        'status_dosen' => 'DTT',
        'nuptk' => '9999888877776666',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Data dosen berhasil diperbarui.',
            'data' => [
                'nama_dosen' => 'Nama Setelah Update, Ph.D.',
                'status_dosen' => 'DTT',
                'nuptk' => '9999888877776666',
            ],
        ]);

    $this->assertDatabaseHas('dosen', [
        'id_dosen' => $dosen->id_dosen,
        'nama_dosen' => 'Nama Setelah Update, Ph.D.',
        'status_dosen' => 'DTT',
        'nuptk' => '9999888877776666',
    ]);
});

// ==============================================================================
// 5. AKSI: CEK KETERKAITAN KE PENELITIAN DAN PKM (DATA SEDANG DIGUNAKAN?)
// ==============================================================================

test('flowchart: status Tidak Tetap saat update tersimpan sebagai DTT', function () {
    $dosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nidn' => '3344556688',
        'nama_dosen' => 'Dosen Status Tetap',
        'email' => 'status.tidak.tetap@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    $response = $this->withToken($this->adminToken)->putJson("/api/dosen/{$dosen->id_dosen}", [
        'status_dosen' => 'Tidak Tetap',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status_dosen', 'DTT');

    $this->assertDatabaseHas('dosen', [
        'id_dosen' => $dosen->id_dosen,
        'status_dosen' => 'DTT',
    ]);
});

test('flowchart: dosen yang sedang digunakan di kegiatan Penelitian ditolak dinonaktifkan dengan pesan Dosen Masih Aktif', function () {
    $dosen = Dosen::create([
        'id_jabatan' => 2,
        'id_pendidikan' => 1,
        'nidn' => '4455667788',
        'nama_dosen' => 'Dosen Peneliti Aktif',
        'email' => 'peneliti.aktif@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    $jenisPenelitian = JenisKegiatan::firstOrCreate(
        ['nama_jenis_kegiatan' => 'Penelitian'],
        ['deskripsi' => 'Kegiatan Penelitian']
    );

    $kegiatan = Kegiatan::create([
        'id_jenis_kegiatan' => $jenisPenelitian->id_jenis_kegiatan,
        'judul_kegiatan' => 'Penelitian Sistem AI untuk Diagnosa Medis',
        'status_kegiatan' => 'Sedang Berjalan',
    ]);

    AnggotaKegiatan::create([
        'id_kegiatan' => $kegiatan->id_kegiatan,
        'id_dosen' => $dosen->id_dosen,
        'jenis_anggota' => 'dosen',
        'peran' => 'Ketua',
    ]);

    // Percobaan nonaktifkan via DELETE
    $response = $this->withToken($this->adminToken)->deleteJson("/api/dosen/{$dosen->id_dosen}");

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Dosen Masih Aktif: Data dosen tidak dapat dinonaktifkan karena sedang digunakan dalam kegiatan Penelitian atau PkM.',
        ]);

    // Verifikasi dosen TIDAK di-soft-delete dan status TIDAK berubah ke NONAKTIF
    $this->assertDatabaseHas('dosen', [
        'id_dosen' => $dosen->id_dosen,
        'status_dosen' => 'DTPS',
        'deleted_at' => null,
    ]);
});

test('flowchart: dosen yang sedang digunakan di kegiatan PkM ditolak dinonaktifkan dengan pesan Dosen Masih Aktif', function () {
    $dosen = Dosen::create([
        'id_jabatan' => 2,
        'id_pendidikan' => 1,
        'nidn' => '5566778899',
        'nama_dosen' => 'Dosen PkM Aktif',
        'email' => 'pkm.aktif@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    $jenisPkm = JenisKegiatan::firstOrCreate(
        ['nama_jenis_kegiatan' => 'PkM'],
        ['deskripsi' => 'Pengabdian kepada Masyarakat']
    );

    $kegiatan = Kegiatan::create([
        'id_jenis_kegiatan' => $jenisPkm->id_jenis_kegiatan,
        'judul_kegiatan' => 'Pelatihan Digital Marketing untuk UMKM',
        'status_kegiatan' => 'Sedang Berjalan',
    ]);

    AnggotaKegiatan::create([
        'id_kegiatan' => $kegiatan->id_kegiatan,
        'id_dosen' => $dosen->id_dosen,
        'jenis_anggota' => 'dosen',
        'peran' => 'Anggota',
    ]);

    // Percobaan nonaktifkan via endpoint nonaktifkan
    $response = $this->withToken($this->adminToken)->postJson("/api/dosen/{$dosen->id_dosen}/nonaktifkan");

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Dosen Masih Aktif: Data dosen tidak dapat dinonaktifkan karena sedang digunakan dalam kegiatan Penelitian atau PkM.',
        ]);

    // Percobaan ubah status langsung ke NONAKTIF via PUT juga ditolak
    $responsePut = $this->withToken($this->adminToken)->putJson("/api/dosen/{$dosen->id_dosen}", [
        'status_dosen' => 'NONAKTIF',
    ]);

    $responsePut->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Dosen Masih Aktif: Data dosen tidak dapat dinonaktifkan karena sedang digunakan dalam kegiatan Penelitian atau PkM.',
        ]);

    $this->assertDatabaseHas('dosen', [
        'id_dosen' => $dosen->id_dosen,
        'status_dosen' => 'DTPS',
        'deleted_at' => null,
    ]);
});

test('flowchart: dosen yang tidak sedang digunakan di Penelitian dan PkM berhasil dinonaktifkan dan di-soft-delete', function () {
    $dosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nidn' => '6677889900',
        'nama_dosen' => 'Dosen Bebas Tanpa Keterkaitan',
        'email' => 'bebas@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    $response = $this->withToken($this->adminToken)->deleteJson("/api/dosen/{$dosen->id_dosen}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Data dosen berhasil dinonaktifkan.',
        ]);

    // Dosen berubah status menjadi NONAKTIF dan di-soft-delete (deleted_at tidak null)
    $this->assertSoftDeleted('dosen', [
        'id_dosen' => $dosen->id_dosen,
        'status_dosen' => 'NONAKTIF',
    ]);
});

test('flowchart: dosen yang dinonaktifkan dapat diaktifkan kembali dengan status DTPS', function () {
    $dosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nidn' => '7788991122',
        'nama_dosen' => 'Dosen Siap Restore',
        'email' => 'restore@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    // Nonaktifkan
    $this->withToken($this->adminToken)->deleteJson("/api/dosen/{$dosen->id_dosen}");

    // Restore
    $restoreResponse = $this->withToken($this->adminToken)->postJson("/api/dosen/{$dosen->id_dosen}/restore");

    $restoreResponse->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Data dosen berhasil diaktifkan kembali.',
            'data' => [
                'status_dosen' => 'DTPS',
            ],
        ]);

    $this->assertDatabaseHas('dosen', [
        'id_dosen' => $dosen->id_dosen,
        'status_dosen' => 'DTPS',
        'deleted_at' => null,
    ]);
});

// ==============================================================================
// 6. SCREEN: DETAIL DOSEN (GET /api/dosen/{id})
// ==============================================================================

test('screen: ambil detail satu dosen memuat riwayat tridharma lengkap', function () {
    $dosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nidn' => '1231231234',
        'nuptk' => '9876543210987654',
        'nama_dosen' => 'Prof. Ir. Detail Test, M.Eng.',
        'email' => 'detail.test@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    $response = $this->withToken($this->adminToken)->getJson("/api/dosen/{$dosen->id_dosen}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'id_dosen' => $dosen->id_dosen,
                'nidn' => '1231231234',
                'nuptk' => '9876543210987654',
                'nama_dosen' => 'Prof. Ir. Detail Test, M.Eng.',
                'status_dosen' => 'DTPS',
            ],
        ])
        ->assertJsonStructure([
            'success',
            'data' => [
                'id_dosen',
                'nidn',
                'nuptk',
                'nama_dosen',
                'status_dosen',
                'jabatan_akademik',
                'pendidikan',
                'bidang_keahlian',
                'penelitian',
                'pkm',
                'luaran',
                'buku',
                'hki',
                'sertifikasi',
            ],
        ]);
});
