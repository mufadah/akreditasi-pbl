<?php

use App\Models\AnggotaKegiatan;
use App\Models\Dosen;
use App\Models\JenisKegiatan;
use App\Models\JenisLuaran;
use App\Models\Kegiatan;
use App\Models\Luaran;
use App\Models\PendanaanKegiatan;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('Ekstensi PHP zip tidak aktif; dibutuhkan untuk membuat dan membaca file .xlsx.');
    }

    $this->seed(DatabaseSeeder::class);
    $this->dosen = Dosen::create([
        'id_jabatan' => 1, 'id_pendidikan' => 1, 'nama_dosen' => 'Dr. Eksportir',
        'nidn' => '0099000011', 'email' => 'eksportir@polines.ac.id', 'status_dosen' => 'DTPS',
    ]);
    $this->token = JWT::encode([
        'sub' => 1,
        'email' => 'export@polines.ac.id',
        'role' => 'ADMINISTRATOR',
        'exp' => time() + 3600,
    ], config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes'), 'HS256');
});

/**
 * Buka file xlsx dari respons unduhan dan kembalikan seluruh baris (array of array).
 *
 * @return array{rows: array<int, array<int, mixed>>, sheet: Worksheet}
 */
function bacaXlsx($response): array
{
    $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

    return ['rows' => $sheet->toArray(null, false, false, false), 'sheet' => $sheet];
}

test('export kegiatan hanya berisi jenis yang diminta, lengkap dengan ketua, anggota, dan dana', function () {
    $anggota = Dosen::create([
        'id_jabatan' => 1, 'id_pendidikan' => 1, 'nama_dosen' => 'Dosen Anggota Export',
        'nidn' => '0099000022', 'email' => 'anggota-export@polines.ac.id', 'status_dosen' => 'DTPS',
    ]);

    $penelitian = Kegiatan::create([
        'id_jenis_kegiatan' => JenisKegiatan::where('nama_jenis_kegiatan', 'Penelitian')->value('id_jenis_kegiatan'),
        'judul_kegiatan' => 'Penelitian Untuk Export',
        'tahun_kegiatan' => 2025,
        'status_kegiatan' => 'Selesai',
    ]);
    AnggotaKegiatan::create(['id_kegiatan' => $penelitian->id_kegiatan, 'id_dosen' => $this->dosen->id_dosen, 'jenis_anggota' => 'dosen', 'peran' => 'Ketua']);
    AnggotaKegiatan::create(['id_kegiatan' => $penelitian->id_kegiatan, 'id_dosen' => $anggota->id_dosen, 'jenis_anggota' => 'dosen', 'peran' => 'Anggota']);
    PendanaanKegiatan::create(['id_kegiatan' => $penelitian->id_kegiatan, 'sumber_dana' => 'DIPA Polines', 'jumlah_dana' => 12500000, 'tahun_pendanaan' => 2025]);
    PendanaanKegiatan::create(['id_kegiatan' => $penelitian->id_kegiatan, 'sumber_dana' => 'Mitra Industri', 'jumlah_dana' => 2500000, 'tahun_pendanaan' => 2025]);

    Kegiatan::create([
        'id_jenis_kegiatan' => JenisKegiatan::where('nama_jenis_kegiatan', 'PkM')->value('id_jenis_kegiatan'),
        'judul_kegiatan' => 'PkM Yang Tidak Boleh Ikut',
        'tahun_kegiatan' => 2025,
    ]);

    $response = $this->withToken($this->token)->get('/api/kegiatan/export?jenis=penelitian&tahun_kegiatan=2025');

    $response->assertOk()
        ->assertDownload();
    expect($response->headers->get('content-disposition'))->toMatch('/penelitian_\d{8}_\d{6}\.xlsx/');

    ['rows' => $rows, 'sheet' => $sheet] = bacaXlsx($response);
    $judul = array_column($rows, 2);

    expect($rows[0])->toContain('Judul Kegiatan', 'Ketua', 'Total Dana (Rp)')
        ->and($judul)->toContain('Penelitian Untuk Export')
        ->not->toContain('PkM Yang Tidak Boleh Ikut');

    $baris = $rows[array_search('Penelitian Untuk Export', $judul, true)];
    expect($baris[1])->toBe('Penelitian')
        ->and($baris[3])->toBe('Dr. Eksportir')
        ->and($baris[4])->toBe('Dosen Anggota Export')
        ->and((float) $baris[11])->toBe(15000000.0)
        ->and($baris[12])->toBe('DIPA Polines; Mitra Industri');

    // Total dana harus tersimpan sebagai angka agar bisa dijumlahkan di Excel.
    $barisExcel = array_search('Penelitian Untuk Export', $judul, true) + 1;
    expect($sheet->getCell("L{$barisExcel}")->getDataType())->toBe(DataType::TYPE_NUMERIC);
});

test('export kegiatan memakai filter yang sama dengan daftar', function () {
    foreach (['Alpha Export Cocok', 'Beta Export Lain'] as $judul) {
        Kegiatan::create([
            'id_jenis_kegiatan' => JenisKegiatan::where('nama_jenis_kegiatan', 'PkM')->value('id_jenis_kegiatan'),
            'judul_kegiatan' => $judul,
        ]);
    }

    $judul = array_column(bacaXlsx($this->withToken($this->token)->get('/api/kegiatan/export?jenis=pkm&search=Alpha'))['rows'], 2);

    expect($judul)->toContain('Alpha Export Cocok')->not->toContain('Beta Export Lain');
});

test('export luaran artikel memakai kolom artikel dan menjaga NIDN/ISBN serta teks berawalan = sebagai teks', function () {
    $jurnal = JenisLuaran::where('nama_jenis_luaran', 'like', '%Jurnal%')->firstOrFail();
    $buku = JenisLuaran::where('nama_jenis_luaran', 'like', '%Buku%')->firstOrFail();

    Luaran::create([
        'id_dosen' => $this->dosen->id_dosen, 'id_jenis_luaran' => $jurnal->id_jenis_luaran,
        'nama_jurnal' => '=HYPERLINK("http://jahat.example","klik")', 'issn' => '0012-3456',
        'doi' => '10.1234/export', 'indeksasi' => 'Scopus Q1', 'tahun_terbit' => 2025,
        'tautan_jurnal' => 'https://doi.org/10.1234/export',
    ]);
    Luaran::create([
        'id_dosen' => $this->dosen->id_dosen, 'id_jenis_luaran' => $buku->id_jenis_luaran,
        'judul_buku' => 'Buku Yang Tidak Boleh Ikut', 'isbn' => '0012345678901',
    ]);

    $response = $this->withToken($this->token)->get('/api/luaran/export?kategori=artikel');
    $response->assertOk()->assertDownload();
    expect($response->headers->get('content-disposition'))->toContain('luaran-artikel_');

    ['rows' => $rows, 'sheet' => $sheet] = bacaXlsx($response);

    expect($rows[0])->toBe(['No', 'Jenis Luaran', 'Penulis', 'Judul / Nama Jurnal', 'ISSN', 'Volume', 'Nomor', 'Halaman', 'DOI', 'Indeksasi', 'Tanggal Terbit', 'Tautan', 'Tahun', 'Kegiatan Terkait'])
        ->and(array_column($rows, 3))->not->toContain('Buku Yang Tidak Boleh Ikut');

    $index = array_search('=HYPERLINK("http://jahat.example","klik")', array_column($rows, 3), true);
    expect($index)->not->toBeFalse();

    $barisExcel = $index + 1;
    expect($sheet->getCell("D{$barisExcel}")->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($rows[$index][2])->toBe('Dr. Eksportir')
        ->and($rows[$index][4])->toBe('0012-3456')
        ->and($rows[$index][8])->toBe('10.1234/export')
        ->and((int) $rows[$index][12])->toBe(2025);
});

test('export luaran buku menjaga ISBN berawalan nol', function () {
    $buku = JenisLuaran::where('nama_jenis_luaran', 'like', '%Buku%')->firstOrFail();
    Luaran::create([
        'id_dosen' => $this->dosen->id_dosen, 'id_jenis_luaran' => $buku->id_jenis_luaran,
        'judul_buku' => 'Buku ISBN Nol', 'isbn' => '0012345678901', 'penerbit_buku' => 'Penerbit Uji',
    ]);

    $rows = bacaXlsx($this->withToken($this->token)->get('/api/luaran/export?kategori=buku'))['rows'];
    $index = array_search('Buku ISBN Nol', array_column($rows, 3), true);

    expect($index)->not->toBeFalse()
        ->and($rows[$index][4])->toBe('Penerbit Uji')
        ->and($rows[$index][5])->toBe('0012345678901');
});

test('export luaran tanpa kategori memakai kolom umum', function () {
    $hki = JenisLuaran::where('nama_jenis_luaran', 'like', '%HKI%')->firstOrFail();
    Luaran::create([
        'id_dosen' => $this->dosen->id_dosen, 'id_jenis_luaran' => $hki->id_jenis_luaran,
        'judul_hak_cipta' => 'HKI Umum Export',
    ]);

    $rows = bacaXlsx($this->withToken($this->token)->get('/api/luaran/export'))['rows'];

    expect($rows[0])->toBe(['No', 'Jenis Luaran', 'Penulis', 'Judul', 'Tahun', 'Kegiatan Terkait'])
        ->and(array_column($rows, 3))->toContain('HKI Umum Export');
});

test('export luaran menolak kategori tidak dikenal dan menuntut autentikasi', function () {
    $this->withToken($this->token)->getJson('/api/luaran/export?kategori=zzz')
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->flushHeaders()->getJson('/api/luaran/export')->assertUnauthorized();
    $this->flushHeaders()->getJson('/api/kegiatan/export')->assertUnauthorized();
});
