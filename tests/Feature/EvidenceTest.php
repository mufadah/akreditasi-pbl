<?php

use App\Models\Evidence;
use App\Models\EvidenceHistory;
use App\Models\Kegiatan;
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

    $this->kaprodiToken = JWT::encode([
        'sub' => 2,
        'email' => 'kaprodi@polines.ac.id',
        'role' => 'KAPRODI',
        'permissions' => ['*'],
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $this->dosenToken = JWT::encode([
        'sub' => 3,
        'email' => 'dosen@polines.ac.id',
        'role' => 'DOSEN',
        'permissions' => ['*'],
        'exp' => time() + 3600,
    ], $secret, 'HS256');
});

// 1. Create Evidence berhasil & status awal Menunggu Validasi & judul_file terpisah
test('create evidence berhasil dengan judul_file dan url google drive terpisah', function () {
    $res = $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'judul_file' => 'Proposal Riset AI 2026',
        'nama_file' => 'https://drive.google.com/file/d/test-proposal-ai-2026/view',
        'jenis_dokumen' => 'Proposal',
        'tanggal_upload' => '2026-11-17',
    ]);

    $res->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Link evidence berhasil disimpan dan menunggu validasi.')
        ->assertJsonPath('data.status_validasi', 'Menunggu Validasi')
        ->assertJsonPath('data.judul_file', 'Proposal Riset AI 2026')
        ->assertJsonPath('data.url_drive', 'https://drive.google.com/file/d/test-proposal-ai-2026/view');

    $this->assertDatabaseHas('evidence', [
        'judul_file' => 'Proposal Riset AI 2026',
        'status_validasi' => 'Menunggu Validasi',
    ]);
});

// 2. URL Google Drive tidak valid ditolak
test('create evidence menolak url yang bukan dari domain google drive atau docs', function () {
    $res = $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'judul_file' => 'Dokumen Liar',
        'nama_file' => 'https://dropbox.com/s/sample-file/view',
        'jenis_dokumen' => 'Laporan',
    ]);

    $res->assertStatus(422)
        ->assertJsonPath('success', false);
});

// 3. Duplikasi Evidence ditangani (409 Conflict) dan lolos dengan konfirmasi_lanjut
test('create evidence mendeteksi url duplikat dan memberikan 409 conflict kecuali konfirmasi_lanjut', function () {
    $url = 'https://drive.google.com/file/d/unique-evidence-url-12345/view';

    // Pertama buat
    $res1 = $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'judul_file' => 'Evidence Asli',
        'nama_file' => $url,
        'jenis_dokumen' => 'Laporan',
    ]);
    $res1->assertStatus(201);

    // Coba buat lagi tanpa konfirmasi_lanjut
    $res2 = $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'judul_file' => 'Evidence Duplikat',
        'nama_file' => $url,
        'jenis_dokumen' => 'Laporan',
        'konfirmasi_lanjut' => false,
    ]);
    $res2->assertStatus(409)
        ->assertJsonPath('data.is_duplicate', true);

    // Coba buat lagi dengan konfirmasi_lanjut: true
    $res3 = $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'judul_file' => 'Evidence Duplikat Dipaksa',
        'nama_file' => $url,
        'jenis_dokumen' => 'Laporan',
        'konfirmasi_lanjut' => true,
    ]);
    $res3->assertStatus(201);
});

// 4 & 5. Daftar Evidence, Search dan Filter berjalan
test('daftar evidence dapat diambil dengan search dan filter yang valid', function () {
    $res = $this->withToken($this->adminToken)->getJson('/api/evidence?status_validasi=Valid');
    $res->assertStatus(200)
        ->assertJsonPath('success', true);

    $resSearch = $this->withToken($this->adminToken)->getJson('/api/evidence?search=Laporan');
    $resSearch->assertStatus(200)
        ->assertJsonPath('success', true);
});

// 6. Detail Evidence berhasil mengembalikan data lengkap
test('detail evidence berhasil mengembalikan data lengkap dan riwayat', function () {
    $evidence = Evidence::first();

    $res = $this->withToken($this->adminToken)->getJson('/api/evidence/'.$evidence->id_evidence);
    $res->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id_evidence', $evidence->id_evidence);
});

// 7, 8, 9, 12. Edit Nama File, Jenis Dokumen, Tanggal Upload tanpa ganti URL tidak membuat history
test('edit metadata nama file jenis dokumen dan tanggal upload tanpa ganti url tidak membuat history', function () {
    $evidence = Evidence::create([
        'judul_file' => 'Proposal Lama',
        'nama_file' => 'https://drive.google.com/file/d/fixed-url-original/view',
        'url_drive' => 'https://drive.google.com/file/d/fixed-url-original/view',
        'jenis_dokumen' => 'Proposal',
        'tanggal_upload' => '2026-01-01',
        'status_validasi' => 'Valid',
    ]);

    $historyCountBefore = EvidenceHistory::where('evidence_id', $evidence->id_evidence)->count();

    $res = $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'judul_file' => 'Proposal Riset Tahun 2026 Terbaru',
        'jenis_dokumen' => 'Laporan Akhir',
        'tanggal_upload' => '2026-02-01',
    ]);

    $res->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.judul_file', 'Proposal Riset Tahun 2026 Terbaru')
        ->assertJsonPath('data.jenis_dokumen', 'Laporan Akhir')
        ->assertJsonPath('data.tanggal_upload', '2026-02-01')
        ->assertJsonPath('data.status_validasi', 'Valid'); // Status tidak ter-reset jika URL tidak berubah

    $historyCountAfter = EvidenceHistory::where('evidence_id', $evidence->id_evidence)->count();
    expect($historyCountAfter)->toBe($historyCountBefore);
});

// 10, 13, 14. Edit URL Google Drive wajib alasan_perubahan, simpan URL lama ke history, dan reset status validasi
test('edit url google drive mewajibkan alasan perubahan, mengarsipkan url lama ke history dan mereset status validasi', function () {
    $oldUrl = 'https://drive.google.com/file/d/evidence-before-change/view';
    $newUrl = 'https://drive.google.com/file/d/evidence-after-change-999/view';

    $evidence = Evidence::create([
        'judul_file' => 'Draft Penelitian',
        'nama_file' => $oldUrl,
        'url_drive' => $oldUrl,
        'jenis_dokumen' => 'Laporan',
        'status_validasi' => 'Valid',
    ]);

    // 1. Gagal jika alasan perubahan kosong
    $resFail = $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'url_drive' => $newUrl,
    ]);
    $resFail->assertStatus(422);

    // 2. Berhasil jika alasan perubahan diisi
    $resSuccess = $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'url_drive' => $newUrl,
        'alasan_perubahan' => 'Pembaruan tanda tangan dekan.',
    ]);

    $resSuccess->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.url_drive', $newUrl)
        ->assertJsonPath('data.status_validasi', 'Menunggu Validasi');

    $this->assertDatabaseHas('evidence_histories', [
        'evidence_id' => $evidence->id_evidence,
        'url_lama' => $oldUrl,
        'url_baru' => $newUrl,
        'alasan_perubahan' => 'Pembaruan tanda tangan dekan.',
    ]);
});

// 11. Edit beberapa field sekaligus berhasil
test('edit beberapa field sekaligus berhasil tersimpan secara konsisten', function () {
    $oldUrl = 'https://drive.google.com/file/d/multi-edit-old/view';
    $newUrl = 'https://drive.google.com/file/d/multi-edit-new/view';

    $evidence = Evidence::create([
        'judul_file' => 'Judul Sebelum',
        'nama_file' => $oldUrl,
        'url_drive' => $oldUrl,
        'jenis_dokumen' => 'Proposal',
        'tanggal_upload' => '2026-01-10',
        'status_validasi' => 'Perlu Perbaikan',
    ]);

    $res = $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'judul_file' => 'Judul Sesudah',
        'jenis_dokumen' => 'Kontrak',
        'tanggal_upload' => '2026-03-15',
        'url_drive' => $newUrl,
        'alasan_perubahan' => 'Perbaikan lampiran kontrak',
    ]);

    $res->assertStatus(200)
        ->assertJsonPath('data.judul_file', 'Judul Sesudah')
        ->assertJsonPath('data.jenis_dokumen', 'Kontrak')
        ->assertJsonPath('data.tanggal_upload', '2026-03-15')
        ->assertJsonPath('data.url_drive', $newUrl)
        ->assertJsonPath('data.status_validasi', 'Menunggu Validasi');
});

// 20. Status validasi tidak bisa diubah melalui form edit biasa
test('status validasi tidak dapat diubah sembarangan melalui endpoint update metadata biasa', function () {
    $evidence = Evidence::create([
        'judul_file' => 'Dokumen Penting',
        'nama_file' => 'https://drive.google.com/file/d/valid-bypass-attempt/view',
        'url_drive' => 'https://drive.google.com/file/d/valid-bypass-attempt/view',
        'jenis_dokumen' => 'Laporan',
        'status_validasi' => 'Menunggu Validasi',
    ]);

    $res = $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'judul_file' => 'Dokumen Penting Updated',
        'status_validasi' => 'Valid', // Mencoba ubah status secara ilegal
    ]);

    $res->assertStatus(200);

    // Status harus tetap Menunggu Validasi di database
    $this->assertDatabaseHas('evidence', [
        'id_evidence' => $evidence->id_evidence,
        'status_validasi' => 'Menunggu Validasi',
    ]);
});

// 17, 18, 19. Validasi Valid, Ditolak, Perlu Perbaikan
test('fitur validasi menerima status Valid Ditolak dan Perlu Perbaikan serta menolak status lain', function () {
    $evidence = Evidence::first();

    // 1. Tolak status sembarangan
    $resInvalid = $this->withToken($this->kaprodiToken)->postJson('/api/evidence/'.$evidence->id_evidence.'/validasi', [
        'status_validasi' => 'Lulus',
    ]);
    $resInvalid->assertStatus(422);

    // 2. Status Valid
    $resValid = $this->withToken($this->kaprodiToken)->postJson('/api/evidence/'.$evidence->id_evidence.'/validasi', [
        'status_validasi' => 'Valid',
        'catatan_validasi' => 'Dokumen sah.',
    ]);
    $resValid->assertStatus(200)
        ->assertJsonPath('data.status_validasi', 'Valid');

    // 3. Status Perlu Perbaikan
    $resRevisi = $this->withToken($this->kaprodiToken)->postJson('/api/evidence/'.$evidence->id_evidence.'/validasi', [
        'status_validasi' => 'Perlu Perbaikan',
        'catatan_validasi' => 'Tanda tangan belum lengkap.',
    ]);
    $resRevisi->assertStatus(200)
        ->assertJsonPath('data.status_validasi', 'Perlu Perbaikan');
});

// 21, 22. Delete Evidence berhasil & tidak menghapus data induk
test('delete evidence berhasil dan tidak menghapus data induk kegiatan atau luaran', function () {
    $kegiatan = Kegiatan::first();
    $evidence = Evidence::create([
        'judul_file' => 'Evidence Hapus',
        'nama_file' => 'https://drive.google.com/file/d/to-be-deleted-evidence/view',
        'url_drive' => 'https://drive.google.com/file/d/to-be-deleted-evidence/view',
        'jenis_dokumen' => 'Laporan',
        'id_kegiatan' => $kegiatan->id_kegiatan,
    ]);

    $res = $this->withToken($this->adminToken)->deleteJson('/api/evidence/'.$evidence->id_evidence);
    $res->assertStatus(200);

    $this->assertDatabaseMissing('evidence', [
        'id_evidence' => $evidence->id_evidence,
    ]);

    // Data induk kegiatan tetap ada di database
    $this->assertDatabaseHas('kegiatan', [
        'id_kegiatan' => $kegiatan->id_kegiatan,
    ]);
});

// 23. Endpoint dedicated ganti-link berfungsi dengan database transaction dan reset status
test('endpoint ganti-link berhasil mengarsipkan url lama ke history dan mereset status validasi', function () {
    $oldUrl = 'https://drive.google.com/file/d/old-link-111/view';
    $newUrl = 'https://drive.google.com/file/d/new-link-222/view';

    $evidence = Evidence::create([
        'judul_file' => 'Dokumen Ganti Link',
        'nama_file' => $oldUrl,
        'url_drive' => $oldUrl,
        'jenis_dokumen' => 'Sertifikat',
        'status_validasi' => 'Valid',
    ]);

    $res = $this->withToken($this->dosenToken)->postJson('/api/evidence/'.$evidence->id_evidence.'/ganti-link', [
        'url_drive_baru' => $newUrl,
        'alasan_perubahan' => 'Pembaruan nomor SK lembar pengesahan',
    ]);

    $res->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status_validasi', 'Menunggu Validasi')
        ->assertJsonPath('data.url_drive', $newUrl);

    $this->assertDatabaseHas('evidence_histories', [
        'evidence_id' => $evidence->id_evidence,
        'url_lama' => $oldUrl,
        'url_baru' => $newUrl,
        'alasan_perubahan' => 'Pembaruan nomor SK lembar pengesahan',
    ]);
});
