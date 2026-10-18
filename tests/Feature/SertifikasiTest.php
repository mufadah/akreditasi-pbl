<?php

use App\Models\Dosen;
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
        'email' => 'admin@polines.ac.id',
        'role' => 'ADMINISTRATOR',
        'permissions' => ['*'],
        'exp' => time() + 3600,
    ], $secret, 'HS256');
});

test('filter sertifikasi: dapat memfilter berdasarkan tipe pemilik dosen vs tendik', function () {
    $dosen = Dosen::first();
    $tendik = TenagaKependidikan::first();

    Sertifikasi::create([
        'id_dosen' => $dosen->id_dosen,
        'jenis_sertifikasi' => 'Sertifikat Pendidik Dosen Khusus',
        'nomor_sertifikat' => 'CERT-DOSEN-999',
        'tahun_sertifikasi' => 2024,
        'status_sertifikasi' => 'Aktif',
    ]);

    Sertifikasi::create([
        'id_tendik' => $tendik->id_tendik,
        'jenis_sertifikasi' => 'Sertifikasi BNSP Tendik Khusus',
        'nomor_sertifikat' => 'CERT-TENDIK-999',
        'tahun_sertifikasi' => 2024,
        'status_sertifikasi' => 'Aktif',
    ]);

    // Filter Dosen
    $resDosen = $this->withToken($this->adminToken)->getJson('/api/sertifikasi?tipe_pemilik=dosen');
    $resDosen->assertStatus(200);
    foreach ($resDosen->json('data.data') as $item) {
        expect($item['id_dosen'])->not->toBeNull()
            ->and($item['id_tendik'])->toBeNull();
    }

    // Filter Tendik
    $resTendik = $this->withToken($this->adminToken)->getJson('/api/sertifikasi?tipe_pemilik=tendik');
    $resTendik->assertStatus(200);
    foreach ($resTendik->json('data.data') as $item) {
        expect($item['id_tendik'])->not->toBeNull()
            ->and($item['id_dosen'])->toBeNull();
    }
});

test('filter sertifikasi: dapat memfilter berdasarkan status masa berlaku aktif, kedaluwarsa, dan segera berakhir', function () {
    $dosen = Dosen::first();

    // 1. Sertifikasi Aktif (kedaluwarsa 2 tahun lagi)
    Sertifikasi::create([
        'id_dosen' => $dosen->id_dosen,
        'jenis_sertifikasi' => 'Sertifikasi Aktif',
        'nomor_sertifikat' => 'CERT-AKTIF-01',
        'tahun_sertifikasi' => 2024,
        'tanggal_kedaluwarsa' => now()->addYears(2)->toDateString(),
        'status_sertifikasi' => 'Aktif',
    ]);

    // 2. Sertifikasi Kedaluwarsa (kedaluwarsa bulan lalu)
    Sertifikasi::create([
        'id_dosen' => $dosen->id_dosen,
        'jenis_sertifikasi' => 'Sertifikasi Kedaluwarsa',
        'nomor_sertifikat' => 'CERT-EXPIRED-01',
        'tahun_sertifikasi' => 2020,
        'tanggal_kedaluwarsa' => now()->subMonths(1)->toDateString(),
        'status_sertifikasi' => 'Kedaluwarsa',
    ]);

    // 3. Sertifikasi Segera Berakhir (15 hari lagi)
    Sertifikasi::create([
        'id_dosen' => $dosen->id_dosen,
        'jenis_sertifikasi' => 'Sertifikasi Segera Berakhir',
        'nomor_sertifikat' => 'CERT-SOON-01',
        'tahun_sertifikasi' => 2023,
        'tanggal_kedaluwarsa' => now()->addDays(15)->toDateString(),
        'status_sertifikasi' => 'Aktif',
    ]);

    // Filter Aktif
    $resAktif = $this->withToken($this->adminToken)->getJson('/api/sertifikasi?status_masa_berlaku=aktif');
    $resAktif->assertStatus(200);
    $jenisAktif = collect($resAktif->json('data.data'))->pluck('jenis_sertifikasi');
    expect($jenisAktif)->toContain('Sertifikasi Aktif')
        ->and($jenisAktif)->not->toContain('Sertifikasi Kedaluwarsa');

    // Filter Kedaluwarsa
    $resExp = $this->withToken($this->adminToken)->getJson('/api/sertifikasi?status_masa_berlaku=kedaluwarsa');
    $resExp->assertStatus(200);
    $jenisExp = collect($resExp->json('data.data'))->pluck('jenis_sertifikasi');
    expect($jenisExp)->toContain('Sertifikasi Kedaluwarsa')
        ->and($jenisExp)->not->toContain('Sertifikasi Aktif');

    // Filter Segera Berakhir
    $resSoon = $this->withToken($this->adminToken)->getJson('/api/sertifikasi?status_masa_berlaku=segera_berakhir&hari_kedaluwarsa=30');
    $resSoon->assertStatus(200);
    $jenisSoon = collect($resSoon->json('data.data'))->pluck('jenis_sertifikasi');
    expect($jenisSoon)->toContain('Sertifikasi Segera Berakhir');
});

test('filter sertifikasi: dapat memfilter berdasarkan tahun dan rentang tahun sertifikasi', function () {
    $dosen = Dosen::first();

    Sertifikasi::create([
        'id_dosen' => $dosen->id_dosen,
        'jenis_sertifikasi' => 'Sertifikat 2018',
        'nomor_sertifikat' => 'CERT-2018',
        'tahun_sertifikasi' => 2018,
    ]);

    Sertifikasi::create([
        'id_dosen' => $dosen->id_dosen,
        'jenis_sertifikasi' => 'Sertifikat 2025',
        'nomor_sertifikat' => 'CERT-2025',
        'tahun_sertifikasi' => 2025,
    ]);

    // Filter spesifik tahun 2018
    $res2018 = $this->withToken($this->adminToken)->getJson('/api/sertifikasi?tahun_sertifikasi=2018');
    $res2018->assertStatus(200);
    $tahun2018 = collect($res2018->json('data.data'))->pluck('tahun_sertifikasi')->unique();
    expect($tahun2018->all())->toBe([2018]);

    // Filter rentang tahun 2024 - 2026
    $resRentang = $this->withToken($this->adminToken)->getJson('/api/sertifikasi?tahun_mulai=2024&tahun_selesai=2026');
    $resRentang->assertStatus(200);
    foreach ($resRentang->json('data.data') as $item) {
        expect($item['tahun_sertifikasi'])->toBeGreaterThanOrEqual(2024)
            ->and($item['tahun_sertifikasi'])->toBeLessThanOrEqual(2026);
    }
});

test('sinkronisasi otomatis: menambah dan menghapus sertifikasi otomatis memperbarui profil Dosen dan Tendik', function () {
    $dosen = Dosen::first();

    // 1. Tambah sertifikasi pertama via API
    $resStore1 = $this->withToken($this->adminToken)->postJson('/api/sertifikasi', [
        'id_dosen' => $dosen->id_dosen,
        'jenis_sertifikasi' => 'Sertifikat Pendidik Profesional',
        'tahun_sertifikasi' => 2024,
    ]);
    $resStore1->assertStatus(201);
    $idSertif1 = $resStore1->json('data.id_sertifikasi');

    // Cek kolom sertifikasi pada model Dosen telah terupdate otomatis
    $dosenFresh = $dosen->fresh();
    expect($dosenFresh->sertifikasi)->toContain('Sertifikat Pendidik Profesional');

    // 2. Tambah sertifikasi kedua via API
    $resStore2 = $this->withToken($this->adminToken)->postJson('/api/sertifikasi', [
        'id_dosen' => $dosen->id_dosen,
        'jenis_sertifikasi' => 'Sertifikasi BNSP Cloud Architect',
        'tahun_sertifikasi' => 2025,
    ]);
    $resStore2->assertStatus(201);
    $idSertif2 = $resStore2->json('data.id_sertifikasi');

    $dosenFresh2 = $dosen->fresh();
    expect($dosenFresh2->sertifikasi)->toContain('Sertifikat Pendidik Profesional')
        ->and($dosenFresh2->sertifikasi)->toContain('Sertifikasi BNSP Cloud Architect');

    // 3. Hapus sertifikasi kedua
    $resDel = $this->withToken($this->adminToken)->deleteJson("/api/sertifikasi/{$idSertif2}");
    $resDel->assertStatus(200);

    $dosenFresh3 = $dosen->fresh();
    expect($dosenFresh3->sertifikasi)->toContain('Sertifikat Pendidik Profesional')
        ->and($dosenFresh3->sertifikasi)->not->toContain('Sertifikasi BNSP Cloud Architect');
});

test('endpoint sync-profil: dapat memicu sinkronisasi massal seluruh profil Dosen dan Tendik', function () {
    $response = $this->withToken($this->adminToken)->postJson('/api/sertifikasi/sync-profil');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Sinkronisasi sertifikasi ke profil Dosen & Tendik berhasil.',
        ])
        ->assertJsonStructure([
            'success',
            'data' => [
                'total_dosen_disinkron',
                'total_tendik_disinkron',
            ],
        ]);
});
