<?php

use App\Models\Pendidikan;
use App\Models\Sertifikasi;
use App\Models\TenagaKependidikan;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
// 1. TAMPILKAN DAFTAR TENDIK (GET /api/tendik)
// ==============================================================================

test('flowchart: buka menu tenaga kependidikan berhasil menampilkan daftar tendik terpaginasi', function () {
    $pendidikan = Pendidikan::first();
    TenagaKependidikan::create([
        'nip' => '198503152010121002',
        'nama_tendik' => 'Bambang Sutejo, S.Kom.',
        'jabatan_tendik' => 'Laboran Komputer',
        'id_pendidikan' => $pendidikan->id_pendidikan,
    ]);

    $response = $this->withToken($this->adminToken)->getJson('/api/tendik');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data',
                'current_page',
                'total',
            ],
        ]);
});

test('flowchart: tampilkan daftar tendik mendukung pencarian berdasarkan nama, nip, dan jabatan', function () {
    $pendidikan = Pendidikan::first();
    TenagaKependidikan::create([
        'nip' => '199008222015042001',
        'nama_tendik' => 'Siti Rahmawati, A.Md.',
        'jabatan_tendik' => 'Pustakawan',
        'id_pendidikan' => $pendidikan->id_pendidikan,
    ]);

    // Cari by NIP
    $resNip = $this->withToken($this->adminToken)->getJson('/api/tendik?search=199008222015042001');
    $resNip->assertStatus(200);
    expect($resNip->json('data.data'))->toHaveCount(1)
        ->and($resNip->json('data.data.0.nama_tendik'))->toBe('Siti Rahmawati, A.Md.');

    // Cari by Nama
    $resNama = $this->withToken($this->adminToken)->getJson('/api/tendik?search=Rahmawati');
    $resNama->assertStatus(200);
    expect($resNama->json('data.data'))->toHaveCount(1);

    // Cari by Jabatan
    $resJab = $this->withToken($this->adminToken)->getJson('/api/tendik?search=Pustakawan');
    $resJab->assertStatus(200);
    expect($resJab->json('data.data'))->toHaveCount(1);
});

test('flowchart: tampilkan daftar tendik mendukung filter jenis tendik dan status', function () {
    $pendidikan = Pendidikan::first();
    $aktif = TenagaKependidikan::create([
        'nip' => '198811102014021003',
        'nama_tendik' => 'Agus Prasetyo, S.T.',
        'jabatan_tendik' => 'Teknisi Jaringan & Server',
        'id_pendidikan' => $pendidikan->id_pendidikan,
    ]);

    $nonaktif = TenagaKependidikan::create([
        'nip' => '198907192016022004',
        'nama_tendik' => 'Tendik Diarsipkan',
        'jabatan_tendik' => 'Laboran Komputer',
        'id_pendidikan' => $pendidikan->id_pendidikan,
    ]);
    $nonaktif->delete(); // Soft delete

    // Filter status aktif
    $resAktif = $this->withToken($this->adminToken)->getJson('/api/tendik?status=aktif');
    $resAktif->assertStatus(200);
    $idsAktif = collect($resAktif->json('data.data'))->pluck('id_tendik');
    expect($idsAktif)->toContain($aktif->id_tendik)
        ->and($idsAktif)->not->toContain($nonaktif->id_tendik);

    // Filter status nonaktif
    $resNonaktif = $this->withToken($this->adminToken)->getJson('/api/tendik?status=nonaktif');
    $resNonaktif->assertStatus(200);
    $idsNonaktif = collect($resNonaktif->json('data.data'))->pluck('id_tendik');
    expect($idsNonaktif)->toContain($nonaktif->id_tendik)
        ->and($idsNonaktif)->not->toContain($aktif->id_tendik);
});

// ==============================================================================
// 2. AKSI: INPUT NIP, NAMA, JENIS TENDIK, KUALIFIKASI (TAMBAH TENDIK)
// ==============================================================================

test('flowchart: aksi tambah tendik berhasil disimpan ke database dan menampilkan pesan berhasil', function () {
    $payload = [
        'nip' => '199205142018012002',
        'nama' => 'Dewi Lestari, S.E.',
        'jenis_tendik' => 'Tenaga Administrasi Akademik',
        'kualifikasi' => 'S1',
        'sertifikasi' => 'Sertifikasi Tata Kelola Administrasi PT',
    ];

    $response = $this->withToken($this->adminToken)->postJson('/api/tendik', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil ditambahkan.',
            'data' => [
                'nip' => '199205142018012002',
                'nama_tendik' => 'Dewi Lestari, S.E.',
                'jabatan_tendik' => 'Tenaga Administrasi Akademik',
            ],
        ]);

    $this->assertDatabaseHas('tenaga_kependidikan', [
        'nip' => '199205142018012002',
        'nama_tendik' => 'Dewi Lestari, S.E.',
        'jabatan_tendik' => 'Tenaga Administrasi Akademik',
    ]);
});

test('flowchart: tambah tendik gagal validasi jika NIP duplikat', function () {
    $pendidikan = Pendidikan::first();
    TenagaKependidikan::create([
        'nip' => '198501012020011001',
        'nama_tendik' => 'Tendik Eksis',
        'jabatan_tendik' => 'Laboran',
        'id_pendidikan' => $pendidikan->id_pendidikan,
    ]);

    $response = $this->withToken($this->adminToken)->postJson('/api/tendik', [
        'nip' => '198501012020011001',
        'nama' => 'Tendik Duplikat',
        'jenis_tendik' => 'Teknisi',
        'kualifikasi' => 'D3',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Validasi gagal.',
        ]);
});

// ==============================================================================
// 3. SCREEN: DETAIL TENDIK (GET /api/tendik/{id})
// ==============================================================================

test('screen: detail tendik memuat profil lengkap beserta riwayat sertifikasi kompetensi', function () {
    $pendidikan = Pendidikan::first();
    $tendik = TenagaKependidikan::create([
        'nip' => '199304052019031001',
        'nama_tendik' => 'Eko Wahyudi, A.Md.',
        'jabatan_tendik' => 'Laboran Perangkat Keras & IoT',
        'id_pendidikan' => $pendidikan->id_pendidikan,
        'sertifikasi' => 'Sertifikasi IoT Hardware',
    ]);

    Sertifikasi::create([
        'id_tendik' => $tendik->id_tendik,
        'jenis_sertifikasi' => 'Embedded Systems & IoT Hardware',
        'nomor_sertifikat' => 'IOT-IND-2023-301',
        'lembaga_penerbit' => 'LSP Elektronika & Komputer Indonesia',
        'tahun_sertifikasi' => 2023,
        'status_sertifikasi' => 'Aktif',
    ]);

    $response = $this->withToken($this->adminToken)->getJson("/api/tendik/{$tendik->id_tendik}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'id_tendik' => $tendik->id_tendik,
                'nip' => '199304052019031001',
                'nama_tendik' => 'Eko Wahyudi, A.Md.',
            ],
        ])
        ->assertJsonStructure([
            'success',
            'data' => [
                'id_tendik',
                'nip',
                'nama_tendik',
                'jabatan_tendik',
                'pendidikan',
                'sertifikasi',
            ],
        ]);

    expect($response->json('data.sertifikasi'))->toHaveCount(1);
});

// ==============================================================================
// 4. AKSI: UBAH DATA TENDIK (PUT /api/tendik/{id})
// ==============================================================================

test('flowchart: aksi ubah data tendik berhasil mengupdate database dan menampilkan pesan berhasil', function () {
    $pendidikan = Pendidikan::first();
    $tendik = TenagaKependidikan::create([
        'nip' => '198907192016022004',
        'nama_tendik' => 'Nurul Hidayah',
        'jabatan_tendik' => 'Staf Tata Usaha',
        'id_pendidikan' => $pendidikan->id_pendidikan,
    ]);

    $response = $this->withToken($this->adminToken)->putJson("/api/tendik/{$tendik->id_tendik}", [
        'nama' => 'Nurul Hidayah, S.Sos.',
        'jenis_tendik' => 'Arsiparis Ahli Pertama & Tata Usaha',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil diperbarui.',
            'data' => [
                'nama_tendik' => 'Nurul Hidayah, S.Sos.',
                'jabatan_tendik' => 'Arsiparis Ahli Pertama & Tata Usaha',
            ],
        ]);

    $this->assertDatabaseHas('tenaga_kependidikan', [
        'id_tendik' => $tendik->id_tendik,
        'nama_tendik' => 'Nurul Hidayah, S.Sos.',
        'jabatan_tendik' => 'Arsiparis Ahli Pertama & Tata Usaha',
    ]);
});

// ==============================================================================
// 5. AKSI: NONAKTIFKAN TENDIK & RESTORE
// ==============================================================================

test('flowchart: aksi nonaktifkan tendik berhasil melakukan soft delete dan menampilkan pesan berhasil', function () {
    $pendidikan = Pendidikan::first();
    $tendik = TenagaKependidikan::create([
        'nip' => '199501012022011005',
        'nama_tendik' => 'Tendik Siap Nonaktif',
        'jabatan_tendik' => 'Laboran',
        'id_pendidikan' => $pendidikan->id_pendidikan,
    ]);

    // Menggunakan endpoint /api/tendik/{id}/nonaktifkan
    $response = $this->withToken($this->adminToken)->postJson("/api/tendik/{$tendik->id_tendik}/nonaktifkan");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil dinonaktifkan.',
        ]);

    $this->assertSoftDeleted('tenaga_kependidikan', [
        'id_tendik' => $tendik->id_tendik,
    ]);
});

test('flowchart: aksi pulihkan tendik berhasil mengaktifkan kembali data yang dinonaktifkan', function () {
    $pendidikan = Pendidikan::first();
    $tendik = TenagaKependidikan::create([
        'nip' => '199501012022011006',
        'nama_tendik' => 'Tendik Siap Restore',
        'jabatan_tendik' => 'Pustakawan',
        'id_pendidikan' => $pendidikan->id_pendidikan,
    ]);
    $tendik->delete(); // Soft delete

    $response = $this->withToken($this->adminToken)->postJson("/api/tendik/{$tendik->id_tendik}/restore");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil diaktifkan kembali.',
        ]);

    $this->assertDatabaseHas('tenaga_kependidikan', [
        'id_tendik' => $tendik->id_tendik,
        'deleted_at' => null,
    ]);
});

// ==============================================================================
// 6. ROLE PERMISSION & AUTHENTICATION (RBAC)
// ==============================================================================

test('rbac: request tanpa token ditolak dengan HTTP 401 Unauthorized', function () {
    $response = $this->getJson('/api/tendik');
    $response->assertStatus(401);
});

test('rbac: role non-administrator (seperti Dosen) ditolak akses master tendik dengan HTTP 403 Forbidden', function () {
    $response = $this->withToken($this->dosenToken)->getJson('/api/tendik');
    $response->assertStatus(403);
});
