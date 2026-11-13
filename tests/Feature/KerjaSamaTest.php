<?php

use App\Models\Dosen;
use App\Models\Evidence;
use App\Models\JenisKerjaSama;
use App\Models\Mitra;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $this->adminToken = JWT::encode([
        'sub' => 1,
        'email' => 'admin@polines.ac.id',
        'role' => 'ADMINISTRATOR',
        'permissions' => ['*'],
        'exp' => time() + 3600,
    ], $secret, 'HS256');
});

function kerjaSamaPayload(int $mitraId, int $jenisKerjaSamaId, int $dosenId, array $overrides = []): array
{
    return array_merge([
        'id_mitra' => $mitraId,
        'id_jenis_kerjasama' => $jenisKerjaSamaId,
        'id_dosen' => $dosenId,
        'judul_kerja_sama' => 'Kerja Sama API Test',
        'tingkat' => 'Nasional',
        'bentuk_kegiatan' => 'Kelas industri dan riset bersama',
        'nomor_dokumen' => 'KS-API-TEST-001',
        'jenis_dokumen' => 'MoA',
        'tanggal_mulai' => '2026-01-15',
        'tanggal_selesai' => '2028-01-15',
        'bukti_dokumen' => 'https://example.test/kerja-sama-awal.pdf',
        'status' => 'Aktif',
    ], $overrides);
}

test('ID dari daftar membuka detail lengkap dengan relasi dan satu ID kanonis', function () {
    $this->seed(DatabaseSeeder::class);

    $mitra = Mitra::query()->firstOrFail();
    $jenisKerjaSama = JenisKerjaSama::query()->firstOrFail();
    $dosen = Dosen::query()->firstOrFail();

    $created = $this->withToken($this->adminToken)->postJson('/api/kerja-sama', kerjaSamaPayload(
        $mitra->id_mitra,
        $jenisKerjaSama->id_jenis_kerjasama,
        $dosen->id_dosen
    ));

    $created->assertCreated();

    $list = $this->withToken($this->adminToken)->getJson('/api/kerja-sama?search=KS-API-TEST-001');
    $list->assertOk();
    $listItem = $list->json('data.data.0');

    expect($listItem['id_kerja_sama'])->toBe($created->json('data.id_kerja_sama'));
    expect(array_key_exists('id_kerjasama', $listItem))->toBeFalse();

    $detail = $this->withToken($this->adminToken)->getJson('/api/kerja-sama/'.$listItem['id_kerja_sama']);

    $detail->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id_kerja_sama',
                'judul_kerja_sama',
                'tingkat',
                'bentuk_kegiatan',
                'jenis_dokumen',
                'bukti_dokumen',
                'mitra' => ['id_mitra'],
                'jenis_kerja_sama' => ['id_jenis_kerjasama'],
                'dosen' => ['id_dosen'],
            ],
        ])
        ->assertJsonPath('data.id_kerja_sama', $listItem['id_kerja_sama'])
        ->assertJsonPath('data.mitra.id_mitra', $mitra->id_mitra)
        ->assertJsonPath('data.jenis_kerja_sama.id_jenis_kerjasama', $jenisKerjaSama->id_jenis_kerjasama)
        ->assertJsonPath('data.dosen.id_dosen', $dosen->id_dosen);
});

test('ID dari daftar memperbarui data dan mempertahankan field dokumen yang tidak dikirim', function () {
    $this->seed(DatabaseSeeder::class);

    $mitra = Mitra::query()->firstOrFail();
    $jenisKerjaSama = JenisKerjaSama::query()->firstOrFail();
    $dosen = Dosen::query()->firstOrFail();

    $created = $this->withToken($this->adminToken)->postJson('/api/kerja-sama', kerjaSamaPayload(
        $mitra->id_mitra,
        $jenisKerjaSama->id_jenis_kerjasama,
        $dosen->id_dosen
    ));
    $created->assertCreated();

    $listItem = $this->withToken($this->adminToken)
        ->getJson('/api/kerja-sama?search=KS-API-TEST-001')
        ->assertOk()
        ->json('data.data.0');

    $id = $listItem['id_kerja_sama'];
    $updatedEvidence = 'https://example.test/kerja-sama-updated.pdf';

    $this->withToken($this->adminToken)->putJson('/api/kerja-sama/'.$id, [
        'judul_kerja_sama' => 'Kerja Sama API Test Diperbarui',
        'jenis_dokumen' => 'Adendum MoA',
        'bukti_dokumen' => $updatedEvidence,
    ])->assertOk()
        ->assertJsonPath('data.id_kerja_sama', $id)
        ->assertJsonPath('data.judul_kerja_sama', 'Kerja Sama API Test Diperbarui')
        ->assertJsonPath('data.jenis_dokumen', 'Adendum MoA')
        ->assertJsonPath('data.bukti_dokumen', $updatedEvidence);

    $this->withToken($this->adminToken)->putJson('/api/kerja-sama/'.$id, [
        'judul_kerja_sama' => 'Kerja Sama API Test Diperbarui Lagi',
    ])->assertOk()
        ->assertJsonPath('data.jenis_dokumen', 'Adendum MoA')
        ->assertJsonPath('data.bukti_dokumen', $updatedEvidence);

    $this->assertDatabaseHas('kerja_sama', [
        'id_kerja_sama' => $id,
        'judul_kerja_sama' => 'Kerja Sama API Test Diperbarui Lagi',
        'jenis_dokumen' => 'Adendum MoA',
        'bukti_dokumen' => $updatedEvidence,
    ]);
});

test('ID dari daftar dapat dihapus dan detail setelahnya mengembalikan 404', function () {
    $this->seed(DatabaseSeeder::class);

    $mitra = Mitra::query()->firstOrFail();
    $jenisKerjaSama = JenisKerjaSama::query()->firstOrFail();
    $dosen = Dosen::query()->firstOrFail();

    $created = $this->withToken($this->adminToken)->postJson('/api/kerja-sama', kerjaSamaPayload(
        $mitra->id_mitra,
        $jenisKerjaSama->id_jenis_kerjasama,
        $dosen->id_dosen,
        ['nomor_dokumen' => 'KS-API-DELETE-001']
    ));
    $created->assertCreated();

    $listItem = $this->withToken($this->adminToken)
        ->getJson('/api/kerja-sama?search=KS-API-DELETE-001')
        ->assertOk()
        ->json('data.data.0');
    $id = $listItem['id_kerja_sama'];

    $this->withToken($this->adminToken)->deleteJson('/api/kerja-sama/'.$id)->assertOk();

    $this->withToken($this->adminToken)->getJson('/api/kerja-sama/'.$id)->assertNotFound();
    $this->assertDatabaseMissing('kerja_sama', ['id_kerja_sama' => $id]);
});

test('data kerja sama yang masih terkait evidence tidak dapat dihapus', function () {
    $this->seed(DatabaseSeeder::class);

    $mitra = Mitra::query()->firstOrFail();
    $jenisKerjaSama = JenisKerjaSama::query()->firstOrFail();
    $dosen = Dosen::query()->firstOrFail();

    $created = $this->withToken($this->adminToken)->postJson('/api/kerja-sama', kerjaSamaPayload(
        $mitra->id_mitra,
        $jenisKerjaSama->id_jenis_kerjasama,
        $dosen->id_dosen,
        ['nomor_dokumen' => 'KS-API-EVIDENCE-001']
    ));
    $created->assertCreated();

    $listItem = $this->withToken($this->adminToken)
        ->getJson('/api/kerja-sama?search=KS-API-EVIDENCE-001')
        ->assertOk()
        ->json('data.data.0');
    $id = $listItem['id_kerja_sama'];

    Evidence::create([
        'id_kerja_sama' => $id,
        'jenis_dokumen' => 'MoA',
        'nama_file' => 'bukti-kerja-sama.pdf',
    ]);

    $this->withToken($this->adminToken)->deleteJson('/api/kerja-sama/'.$id)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Kerja sama Masih Dipakai: Data kerja sama tidak dapat dihapus karena masih terkait dengan dokumen evidence.');

    $this->assertDatabaseHas('kerja_sama', ['id_kerja_sama' => $id]);
});

test('GET PUT dan DELETE mengembalikan 404 untuk ID yang tidak ada', function () {
    $id = 999999;

    $this->withToken($this->adminToken)->getJson('/api/kerja-sama/'.$id)->assertNotFound();
    $this->withToken($this->adminToken)->putJson('/api/kerja-sama/'.$id, [])->assertNotFound();
    $this->withToken($this->adminToken)->deleteJson('/api/kerja-sama/'.$id)->assertNotFound();
});
