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

// 1. Create: nama_file = nama dokumen (teks), url_drive = tautan bukti
test('create evidence: nama_file menyimpan nama dokumen dan url_drive menyimpan tautan bukti', function () {
    $res = $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'nama_file' => 'Proposal Penelitian Dosen 2026',
        'url_drive' => 'https://drive.google.com/file/d/test-proposal-2026/view',
        'jenis_dokumen' => 'Proposal',
        'tanggal_upload' => '2026-11-20',
    ]);

    $res->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.nama_file', 'Proposal Penelitian Dosen 2026')
        ->assertJsonPath('data.url_drive', 'https://drive.google.com/file/d/test-proposal-2026/view')
        ->assertJsonPath('data.status_validasi', 'Menunggu Validasi')
        ->assertJsonPath('data.tanggal_upload', '2026-11-20');

    $this->assertDatabaseHas('evidence', [
        'nama_file' => 'Proposal Penelitian Dosen 2026',
        'url_drive' => 'https://drive.google.com/file/d/test-proposal-2026/view',
        'status_validasi' => 'Menunggu Validasi',
    ]);
});

// 2. Create: nama_file bukan URL, bukan hasil ekstraksi
test('create evidence: nama_file tersimpan sebagai teks bukan url ekstraksi', function () {
    $res = $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'nama_file' => 'Laporan Kerjasama PT Maju 2026',
        'url_drive' => 'https://example.com/dokumen/laporan.pdf',
        'jenis_dokumen' => 'Laporan',
    ]);

    $res->assertStatus(201)
        ->assertJsonPath('data.nama_file', 'Laporan Kerjasama PT Maju 2026');

    // Pastikan nama_file di database bukan URL
    $evidence = Evidence::where('jenis_dokumen', 'Laporan')
        ->where('nama_file', 'Laporan Kerjasama PT Maju 2026')
        ->first();
    expect($evidence)->not->toBeNull();
    expect(filter_var($evidence->nama_file, FILTER_VALIDATE_URL))->toBeFalse();
});

// 3. Create: tautan dari domain mana pun (bukan hanya Google Drive) diterima
test('create evidence: tautan dari domain selain google drive dapat diterima', function () {
    $urlDropbox = 'https://www.dropbox.com/s/abcdef123/dokumen.pdf?dl=0';
    $urlOnedrive = 'https://onedrive.live.com/edit.aspx?cid=abc123';
    $urlBiasa = 'https://example.com/laporan-akhir.pdf';

    foreach ([$urlDropbox, $urlOnedrive, $urlBiasa] as $i => $url) {
        $res = $this->withToken($this->dosenToken)->postJson('/api/evidence', [
            'nama_file' => 'Dokumen Uji Domain '.$i,
            'url_drive' => $url,
            'jenis_dokumen' => 'Laporan',
        ]);
        $res->assertStatus(201);
        $this->assertDatabaseHas('evidence', ['url_drive' => $url]);
    }
});

// 4. Create: url_drive wajib diisi
test('create evidence gagal jika url_drive tidak diisi', function () {
    $res = $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'nama_file' => 'Dokumen Tanpa URL',
        'jenis_dokumen' => 'Laporan',
    ]);

    $res->assertStatus(422);
});

// 5. Deteksi duplikasi URL
test('create evidence mendeteksi url duplikat dan memberikan 409 conflict kecuali konfirmasi_lanjut', function () {
    $url = 'https://drive.google.com/file/d/unique-evidence-url-12345/view';

    $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'nama_file' => 'Evidence Asli',
        'url_drive' => $url,
        'jenis_dokumen' => 'Laporan',
    ])->assertStatus(201);

    $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'nama_file' => 'Evidence Duplikat',
        'url_drive' => $url,
        'jenis_dokumen' => 'Laporan',
        'konfirmasi_lanjut' => false,
    ])->assertStatus(409)->assertJsonPath('data.is_duplicate', true);

    $this->withToken($this->dosenToken)->postJson('/api/evidence', [
        'nama_file' => 'Evidence Duplikat Dipaksa',
        'url_drive' => $url,
        'jenis_dokumen' => 'Laporan',
        'konfirmasi_lanjut' => true,
    ])->assertStatus(201);
});

// 6. Daftar Evidence dan search
test('daftar evidence dapat diambil dengan search dan filter yang valid', function () {
    $this->withToken($this->adminToken)->getJson('/api/evidence?status_validasi=Valid')
        ->assertStatus(200)->assertJsonPath('success', true);

    $this->withToken($this->adminToken)->getJson('/api/evidence?search=Laporan')
        ->assertStatus(200)->assertJsonPath('success', true);
});

// 7. Detail Evidence
test('detail evidence mengembalikan data lengkap dengan nama_file dan url_drive', function () {
    $evidence = Evidence::create([
        'nama_file' => 'Sertifikat Kompetensi Dosen',
        'url_drive' => 'https://drive.google.com/file/d/sertif-dosen/view',
        'jenis_dokumen' => 'Sertifikat',
        'tanggal_upload' => '2026-11-01',
    ]);

    $res = $this->withToken($this->adminToken)->getJson('/api/evidence/'.$evidence->id_evidence);
    $res->assertStatus(200)
        ->assertJsonPath('data.nama_file', 'Sertifikat Kompetensi Dosen')
        ->assertJsonPath('data.url_drive', 'https://drive.google.com/file/d/sertif-dosen/view')
        ->assertJsonPath('data.tanggal_upload', '2026-11-01');
});

// 8. Edit nama_file (nama dokumen), jenis_dokumen, tanggal_upload tanpa ganti URL tidak membuat history
test('edit metadata nama_file jenis_dokumen tanggal_upload tanpa ganti url tidak membuat history dan tidak mereset status', function () {
    $evidence = Evidence::create([
        'nama_file' => 'Nama Lama',
        'url_drive' => 'https://drive.google.com/file/d/fixed-url-original/view',
        'jenis_dokumen' => 'Proposal',
        'tanggal_upload' => '2026-01-01',
        'status_validasi' => 'Valid',
    ]);

    $historyBefore = EvidenceHistory::where('evidence_id', $evidence->id_evidence)->count();

    $res = $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'nama_file' => 'Proposal Penelitian 2026 Terbaru',
        'jenis_dokumen' => 'Laporan Akhir',
        'tanggal_upload' => '2026-02-01',
    ]);

    $res->assertStatus(200)
        ->assertJsonPath('data.nama_file', 'Proposal Penelitian 2026 Terbaru')
        ->assertJsonPath('data.jenis_dokumen', 'Laporan Akhir')
        ->assertJsonPath('data.tanggal_upload', '2026-02-01')
        ->assertJsonPath('data.status_validasi', 'Valid'); // Status tidak ter-reset

    expect(EvidenceHistory::where('evidence_id', $evidence->id_evidence)->count())->toBe($historyBefore);
});

// 9. Edit url_drive wajib alasan_perubahan, simpan history, reset status
test('edit url_drive mewajibkan alasan perubahan mengarsipkan url lama ke history dan mereset status', function () {
    $oldUrl = 'https://drive.google.com/file/d/before-change/view';
    $newUrl = 'https://onedrive.live.com/edit.aspx?cid=newlink999';

    $evidence = Evidence::create([
        'nama_file' => 'Draft Penelitian',
        'url_drive' => $oldUrl,
        'jenis_dokumen' => 'Laporan',
        'status_validasi' => 'Valid',
    ]);

    // Gagal jika alasan perubahan kosong
    $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'url_drive' => $newUrl,
    ])->assertStatus(422);

    // Berhasil jika alasan perubahan diisi
    $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'url_drive' => $newUrl,
        'alasan_perubahan' => 'Pembaruan dokumen revisi.',
    ])->assertStatus(200)
        ->assertJsonPath('data.url_drive', $newUrl)
        ->assertJsonPath('data.status_validasi', 'Menunggu Validasi');

    $this->assertDatabaseHas('evidence_histories', [
        'evidence_id' => $evidence->id_evidence,
        'url_lama' => $oldUrl,
        'url_baru' => $newUrl,
    ]);
});

// 10. Edit beberapa field sekaligus (nama_file + url_drive + jenis_dokumen + tanggal_upload)
test('edit beberapa field sekaligus tersimpan secara konsisten', function () {
    $oldUrl = 'https://drive.google.com/file/d/multi-edit-old/view';
    $newUrl = 'https://drive.google.com/file/d/multi-edit-new/view';

    $evidence = Evidence::create([
        'nama_file' => 'Judul Sebelum',
        'url_drive' => $oldUrl,
        'jenis_dokumen' => 'Proposal',
        'tanggal_upload' => '2026-01-10',
        'status_validasi' => 'Perlu Perbaikan',
    ]);

    $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'nama_file' => 'Judul Sesudah',
        'jenis_dokumen' => 'Kontrak',
        'tanggal_upload' => '2026-03-15',
        'url_drive' => $newUrl,
        'alasan_perubahan' => 'Perbaikan lampiran kontrak',
    ])->assertStatus(200)
        ->assertJsonPath('data.nama_file', 'Judul Sesudah')
        ->assertJsonPath('data.jenis_dokumen', 'Kontrak')
        ->assertJsonPath('data.tanggal_upload', '2026-03-15')
        ->assertJsonPath('data.url_drive', $newUrl)
        ->assertJsonPath('data.status_validasi', 'Menunggu Validasi');
});

// 11. status_validasi TIDAK bisa diubah melalui PUT oleh role DOSEN
test('status validasi tidak dapat diubah oleh role dosen melalui endpoint update biasa', function () {
    $evidence = Evidence::create([
        'nama_file' => 'Dokumen Penting',
        'url_drive' => 'https://drive.google.com/file/d/status-bypass/view',
        'jenis_dokumen' => 'Laporan',
        'status_validasi' => 'Menunggu Validasi',
    ]);

    $res = $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'nama_file' => 'Dokumen Updated',
        'status_validasi' => 'Valid',
    ]);

    // Harus 403 karena Dosen tidak berwenang ubah status
    $res->assertStatus(403);

    $this->assertDatabaseHas('evidence', [
        'id_evidence' => $evidence->id_evidence,
        'status_validasi' => 'Menunggu Validasi',
    ]);
});

// 12. status_validasi BISA diubah melalui PUT oleh role ADMINISTRATOR
test('status validasi dapat diubah oleh role administrator melalui endpoint update', function () {
    $evidence = Evidence::create([
        'nama_file' => 'Dokumen Admin Update',
        'url_drive' => 'https://drive.google.com/file/d/admin-update-status/view',
        'jenis_dokumen' => 'Laporan',
        'status_validasi' => 'Menunggu Validasi',
    ]);

    $res = $this->withToken($this->adminToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'status_validasi' => 'Valid',
        'catatan_validasi' => 'Divalidasi oleh admin.',
    ]);

    $res->assertStatus(200)
        ->assertJsonPath('data.status_validasi', 'Valid');
});

// 13. Validasi Evidence oleh KAPRODI
test('fitur validasi menerima status Valid Ditolak Perlu Perbaikan dan menolak status tidak valid', function () {
    $evidence = Evidence::first();

    $this->withToken($this->kaprodiToken)->postJson('/api/evidence/'.$evidence->id_evidence.'/validasi', [
        'status_validasi' => 'StatusSembarangan',
    ])->assertStatus(422);

    $this->withToken($this->kaprodiToken)->postJson('/api/evidence/'.$evidence->id_evidence.'/validasi', [
        'status_validasi' => 'Valid',
    ])->assertStatus(200)->assertJsonPath('data.status_validasi', 'Valid');

    $this->withToken($this->kaprodiToken)->postJson('/api/evidence/'.$evidence->id_evidence.'/validasi', [
        'status_validasi' => 'Perlu Perbaikan',
        'catatan_validasi' => 'Tanda tangan belum lengkap.',
    ])->assertStatus(200)->assertJsonPath('data.status_validasi', 'Perlu Perbaikan');
});

// 14. Delete Evidence tidak menghapus data induk
test('delete evidence berhasil dan tidak menghapus data induk kegiatan', function () {
    $kegiatan = Kegiatan::first();
    $evidence = Evidence::create([
        'nama_file' => 'Evidence Hapus',
        'url_drive' => 'https://drive.google.com/file/d/to-be-deleted/view',
        'jenis_dokumen' => 'Laporan',
        'id_kegiatan' => $kegiatan->id_kegiatan,
    ]);

    $this->withToken($this->adminToken)->deleteJson('/api/evidence/'.$evidence->id_evidence)
        ->assertStatus(200);

    $this->assertDatabaseMissing('evidence', ['id_evidence' => $evidence->id_evidence]);
    $this->assertDatabaseHas('kegiatan', ['id_kegiatan' => $kegiatan->id_kegiatan]);
});

// 15. Endpoint ganti-link (domain-agnostic)
test('endpoint ganti-link menerima url dari domain manapun dan mengarsipkan url lama', function () {
    $oldUrl = 'https://drive.google.com/file/d/old-link-111/view';
    $newUrl = 'https://onedrive.live.com/edit.aspx?cid=newlink-222';

    $evidence = Evidence::create([
        'nama_file' => 'Dokumen Ganti Link',
        'url_drive' => $oldUrl,
        'jenis_dokumen' => 'Sertifikat',
        'status_validasi' => 'Valid',
    ]);

    $this->withToken($this->dosenToken)->postJson('/api/evidence/'.$evidence->id_evidence.'/ganti-link', [
        'url_drive_baru' => $newUrl,
        'alasan_perubahan' => 'Pindah platform penyimpanan',
    ])->assertStatus(200)
        ->assertJsonPath('data.url_drive', $newUrl)
        ->assertJsonPath('data.status_validasi', 'Menunggu Validasi');

    $this->assertDatabaseHas('evidence_histories', [
        'evidence_id' => $evidence->id_evidence,
        'url_lama' => $oldUrl,
        'url_baru' => $newUrl,
    ]);
});

// 16. Tanggal upload tersedia di response
test('tanggal_upload dapat disimpan dan ditampilkan pada response', function () {
    $evidence = Evidence::create([
        'nama_file' => 'Dokumen Tanggal Upload',
        'url_drive' => 'https://drive.google.com/file/d/tanggal-test/view',
        'jenis_dokumen' => 'Laporan',
        'tanggal_upload' => '2026-05-15',
    ]);

    $res = $this->withToken($this->adminToken)->getJson('/api/evidence/'.$evidence->id_evidence);
    $res->assertStatus(200)->assertJsonPath('data.tanggal_upload', '2026-05-15');

    $this->withToken($this->dosenToken)->putJson('/api/evidence/'.$evidence->id_evidence, [
        'tanggal_upload' => '2026-07-20',
    ])->assertStatus(200)->assertJsonPath('data.tanggal_upload', '2026-07-20');
});
