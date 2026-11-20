<?php

use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');

    $this->adminToken = JWT::encode([
        'sub' => 1,
        'email' => 'administrator@example.com',
        'name' => 'Administrator',
        'role' => 'ADMINISTRATOR',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $this->jenisPenelitian = JenisKegiatan::firstOrCreate(
        ['nama_jenis_kegiatan' => 'Penelitian'],
        ['deskripsi' => 'Kegiatan penelitian']
    );

    $this->jenisPkm = JenisKegiatan::firstOrCreate(
        ['nama_jenis_kegiatan' => 'PkM'],
        ['deskripsi' => 'Pengabdian kepada masyarakat']
    );
});

test('import Penelitian berhasil menyimpan data kegiatan', function () {
    $csv = "judul_kegiatan,lokasi_kegiatan,tahun_usulan,tahun_kegiatan,tahun_pelaksanaan,lama_kegiatan,status_kegiatan,in_kind\n".
        "Penelitian Sistem Informasi,Semarang,2026,2026,2027,1 Tahun,Usulan,Tidak ada\n";

    $file = UploadedFile::fake()->createWithContent('penelitian.csv', $csv);

    $response = $this->withToken($this->adminToken)
        ->post('/api/kegiatan/import/penelitian', [
            'file' => $file,
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Data Penelitian berhasil diimpor.');

    $this->assertDatabaseHas('kegiatan', [
        'id_jenis_kegiatan' => $this->jenisPenelitian->id_jenis_kegiatan,
        'judul_kegiatan' => 'Penelitian Sistem Informasi',
        'lokasi_kegiatan' => 'Semarang',
    ]);
});

test('import PkM berhasil menyimpan data kegiatan', function () {
    $csv = "judul_kegiatan,lokasi_kegiatan,tahun_usulan,tahun_kegiatan,tahun_pelaksanaan,lama_kegiatan,status_kegiatan,in_kind\n".
        "Pelatihan Digital UMKM,Demak,2026,2026,2026,6 Bulan,Berjalan,Bantuan alat\n";

    $file = UploadedFile::fake()->createWithContent('pkm.csv', $csv);

    $response = $this->withToken($this->adminToken)
        ->post('/api/kegiatan/import/pkm', [
            'file' => $file,
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Data PkM berhasil diimpor.');

    $this->assertDatabaseHas('kegiatan', [
        'id_jenis_kegiatan' => $this->jenisPkm->id_jenis_kegiatan,
        'judul_kegiatan' => 'Pelatihan Digital UMKM',
        'lokasi_kegiatan' => 'Demak',
    ]);
});

test('import ditolak jika judul kegiatan kosong', function () {
    $csv = "judul_kegiatan,lokasi_kegiatan,tahun_usulan\n".
        ",Semarang,2026\n";

    $file = UploadedFile::fake()->createWithContent('invalid.csv', $csv);

    $response = $this->withToken($this->adminToken)
        ->post('/api/kegiatan/import/penelitian', [
            'file' => $file,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Validasi data Excel gagal.');

    $this->assertDatabaseMissing('kegiatan', [
        'judul_kegiatan' => 'Data Tidak Valid',
    ]);
});

test('import ditolak jika jenis kegiatan tidak valid', function () {
    $file = UploadedFile::fake()->createWithContent(
        'data.csv',
        "judul_kegiatan\nData Uji\n"
    );

    $response = $this->withToken($this->adminToken)
        ->post('/api/kegiatan/import/jenis-tidak-valid', [
            'file' => $file,
        ]);

    $response->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Jenis kegiatan tidak valid.');
});

test('import ditolak jika jenis kegiatan dalam file tidak sesuai endpoint', function () {
    $csv = "jenis_kegiatan,judul_kegiatan,lokasi_kegiatan\n".
        "PkM,Pelatihan UMKM,Demak\n";

    $file = UploadedFile::fake()->createWithContent('salah_jenis.csv', $csv);

    $response = $this->withToken($this->adminToken)
        ->post('/api/kegiatan/import/penelitian', [
            'file' => $file,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->assertDatabaseMissing('kegiatan', [
        'judul_kegiatan' => 'Pelatihan UMKM',
    ]);
});

test('export Penelitian berhasil diunduh', function () {
    Kegiatan::create([
        'id_jenis_kegiatan' => $this->jenisPenelitian->id_jenis_kegiatan,
        'judul_kegiatan' => 'Penelitian untuk Export',
        'tahun_kegiatan' => 2026,
    ]);

    $response = $this->withToken($this->adminToken)
        ->get('/api/kegiatan/export/penelitian');

    $response->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain('penelitian_')
        ->toContain('.xlsx');
});

test('export PkM berhasil diunduh', function () {
    Kegiatan::create([
        'id_jenis_kegiatan' => $this->jenisPkm->id_jenis_kegiatan,
        'judul_kegiatan' => 'PkM untuk Export',
        'tahun_kegiatan' => 2026,
    ]);

    $response = $this->withToken($this->adminToken)
        ->get('/api/kegiatan/export/pkm');

    $response->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain('pkm_')
        ->toContain('.xlsx');
});

test('template Penelitian berhasil diunduh', function () {
    $response = $this->withToken($this->adminToken)
        ->get('/api/kegiatan/template/penelitian');

    $response->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain('penelitian_template.xlsx');
});

test('template PkM berhasil diunduh', function () {
    $response = $this->withToken($this->adminToken)
        ->get('/api/kegiatan/template/pkm');

    $response->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain('pkm_template.xlsx');
});
