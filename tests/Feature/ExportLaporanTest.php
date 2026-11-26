<?php

use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\Pendidikan;
use App\Services\K1ApiService;
use App\Services\K3ApiService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');

    $buatToken = fn ($role) => JWT::encode([
        'sub' => 1,
        'email' => strtolower($role).'@example.com',
        'name' => $role,
        'role' => $role,
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $this->adminToken = $buatToken('ADMINISTRATOR');
    $this->dosenToken = $buatToken('DOSEN');

    $rubrik = [['skor' => 4, 'min' => 1], ['skor' => 3, 'min' => 0.75], ['skor' => 2, 'min' => 0.5], ['skor' => 1, 'min' => 0.25]];

    config(['assessment' => [
        'penelitian' => ['PEN-01' => ['nama' => 'Uji penelitian', 'metrik' => 'rata_kegiatan', 'satuan' => 'penelitian per DTPS', 'bobot' => 1.0, 'rubrik' => $rubrik]],
        'pkm' => ['PKM-01' => ['nama' => 'Uji PkM', 'metrik' => 'rata_kegiatan', 'satuan' => 'kegiatan PkM per DTPS', 'bobot' => 1.0, 'rubrik' => $rubrik]],
        'kerjasama' => ['KS-01' => ['nama' => 'Uji kerja sama', 'metrik' => 'jumlah_internasional_nasional', 'satuan' => 'kerja sama yang berlaku', 'bobot' => 1.0, 'rubrik' => [['skor' => 4, 'min' => 6], ['skor' => 1, 'min' => 1]]]],
    ]]);

    $this->mock(K3ApiService::class, fn ($mock) => $mock->shouldReceive('getAmbangRubrik')->andReturn(3.5));
    $this->mock(K1ApiService::class, fn ($mock) => $mock->shouldReceive('getStatistikMahasiswa')->andReturn(['total_mahasiswa' => 70]));
});

function dosenLaporan()
{
    return Dosen::create([
        'id_jabatan' => JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Lektor'])->id_jabatan,
        'id_pendidikan' => Pendidikan::firstOrCreate(
            ['jenjang' => 'S3'],
            ['universitas' => 'Universitas Uji', 'program_studi' => 'Informatika', 'tahun_lulus' => 2020]
        )->id_pendidikan,
        'nidn' => '5555500001',
        'nama_dosen' => 'Dosen Laporan',
        'email' => 'laporan@uji.test',
        'status_dosen' => 'DTPS',
    ]);
}

function bukaXlsx($response)
{
    $dasar = $response->baseResponse;

    $isi = $dasar instanceof BinaryFileResponse
        ? file_get_contents($dasar->getFile()->getPathname())
        : $response->streamedContent();

    $berkas = tempnam(sys_get_temp_dir(), 'lap').'.xlsx';
    file_put_contents($berkas, $isi);

    return IOFactory::load($berkas);
}

test('export assessment pdf menghasilkan berkas pdf dengan nama sesuai', function () {
    $response = $this->withToken($this->adminToken)->get('/api/v1/export/assessment?format=pdf&tahun=2025&jenjang=S1');

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))->toContain('laporan-assessment-2025-S1.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('export assessment xlsx memuat lembar per bidang, gap kompetensi, dan rekomendasi', function () {
    $response = $this->withToken($this->adminToken)->get('/api/v1/export/assessment?format=xlsx&tahun=2025');

    $response->assertStatus(200);

    $buku = bukaXlsx($response);

    expect($buku->getSheetNames())->toBe(['Ringkasan', 'Penelitian', 'PkM', 'Kerja Sama', 'Gap Kompetensi', 'Rekomendasi']);

    $ringkasan = $buku->getSheetByName('Ringkasan');

    expect($ringkasan->getCell('A1')->getValue())->toBe('Bidang')
        ->and($ringkasan->getCell('A2')->getValue())->toBe('Penelitian')
        ->and($buku->getSheetByName('Rekomendasi')->getHighestRow())->toBe(4)
        ->and($buku->getSheetByName('Gap Kompetensi')->getHighestRow())->toBe(4);
});

test('export assessment dengan filter bidang hanya memuat bidang tersebut', function () {
    $response = $this->withToken($this->adminToken)->get('/api/v1/export/assessment?format=xlsx&bidang=pkm');

    expect(bukaXlsx($response)->getSheetNames())->toBe(['Ringkasan', 'PkM', 'Gap Kompetensi', 'Rekomendasi']);
});

test('export dashboard pdf menghasilkan berkas pdf', function () {
    dosenLaporan();

    $response = $this->withToken($this->adminToken)->get('/api/v1/export/dashboard?format=pdf&tahun=2025');

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))->toContain('laporan-dashboard-2025.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('export dashboard xlsx memuat semua lembar dan data dosen serta rasio', function () {
    dosenLaporan();

    $response = $this->withToken($this->adminToken)->get('/api/v1/export/dashboard?format=xlsx&tahun=2025');

    $response->assertStatus(200);

    $buku = bukaXlsx($response);

    expect($buku->getSheetNames())->toBe(['Ringkasan', 'Produktivitas', 'Luaran per Dosen', 'Rasio', 'Sebaran Jabatan', 'Tren', 'Early Warning']);

    expect($buku->getSheetByName('Luaran per Dosen')->getHighestRow())->toBe(2)
        ->and($buku->getSheetByName('Luaran per Dosen')->getCell('A2')->getValue())->toBe('Dosen Laporan')
        ->and($buku->getSheetByName('Rasio')->getCell('B3')->getValue())->toEqual(70)
        ->and($buku->getSheetByName('Tren')->getHighestRow())->toBe(6);
});

test('parameter tidak valid ditolak dengan 422', function () {
    $this->withToken($this->adminToken)->get('/api/v1/export/assessment')->assertStatus(422);
    $this->withToken($this->adminToken)->get('/api/v1/export/assessment?format=docx')->assertStatus(422);
    $this->withToken($this->adminToken)->get('/api/v1/export/assessment?format=pdf&bidang=sdm')->assertStatus(422);
    $this->withToken($this->adminToken)->get('/api/v1/export/dashboard?format=pdf&tahun=24')->assertStatus(422);
});

test('role dosen ditolak dengan 403 pada kedua endpoint', function () {
    $this->withToken($this->dosenToken)->get('/api/v1/export/assessment?format=pdf')->assertStatus(403);
    $this->withToken($this->dosenToken)->get('/api/v1/export/dashboard?format=pdf')->assertStatus(403);
});

test('tanpa token ditolak dengan 401', function () {
    $this->get('/api/v1/export/dashboard?format=pdf')->assertStatus(401);
});