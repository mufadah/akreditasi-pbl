<?php

use App\Models\AnggotaKegiatan;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\JenisKegiatan;
use App\Models\JenisLuaran;
use App\Models\Kegiatan;
use App\Models\Luaran;
use App\Models\PendanaanKegiatan;
use App\Models\Pendidikan;
use App\Services\K3ApiService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    config(['assessment.penelitian' => [
        'PEN-01' => [
            'nama' => 'Uji rata-rata penelitian', 'metrik' => 'rata_kegiatan', 'satuan' => 'penelitian per DTPS', 'bobot' => 0.4,
            'rubrik' => [['skor' => 4, 'min' => 1.0], ['skor' => 3, 'min' => 0.75], ['skor' => 2, 'min' => 0.5], ['skor' => 1, 'min' => 0.25]],
        ],
        'PEN-02' => [
            'nama' => 'Uji persen dosen terlibat', 'metrik' => 'persen_dosen_terlibat', 'satuan' => 'persen DTPS terlibat', 'bobot' => 0.3,
            'rubrik' => [['skor' => 4, 'min' => 90], ['skor' => 3, 'min' => 70], ['skor' => 2, 'min' => 50], ['skor' => 1, 'min' => 25]],
        ],
        'PEN-03' => [
            'nama' => 'Uji rata-rata luaran', 'metrik' => 'rata_luaran', 'satuan' => 'luaran per DTPS', 'bobot' => 0.2,
            'rubrik' => [['skor' => 4, 'min' => 1.0], ['skor' => 3, 'min' => 0.75], ['skor' => 2, 'min' => 0.5], ['skor' => 1, 'min' => 0.25]],
        ],
        'PEN-04' => [
            'nama' => 'Uji rata-rata dana', 'metrik' => 'rata_dana', 'satuan' => 'rupiah dana per DTPS', 'bobot' => 0.1,
            'rubrik' => [['skor' => 4, 'min' => 10000000], ['skor' => 3, 'min' => 7500000], ['skor' => 2, 'min' => 5000000], ['skor' => 1, 'min' => 2500000]],
        ],
    ]]);

    $this->mock(K3ApiService::class, fn ($mock) => $mock->shouldReceive('getAmbangRubrik')->andReturn(3.5));
});

function buatDosenAs($nama, $status = 'DTPS')
{
    static $urut = 0;
    $urut++;

    return Dosen::create([
        'id_jabatan' => JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Lektor'])->id_jabatan,
        'id_pendidikan' => Pendidikan::firstOrCreate(
            ['jenjang' => 'S2'],
            ['universitas' => 'Universitas Uji', 'program_studi' => 'Informatika', 'tahun_lulus' => 2020]
        )->id_pendidikan,
        'nidn' => sprintf('33333%05d', $urut),
        'nama_dosen' => $nama,
        'email' => "as{$urut}@uji.test",
        'status_dosen' => $status,
    ]);
}

function kegiatanAs($jenis, $tahun, $dosenList, $dana = null, $jumlahLuaran = 0)
{
    $kegiatan = Kegiatan::create([
        'id_jenis_kegiatan' => JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => $jenis], ['deskripsi' => 'uji'])->id_jenis_kegiatan,
        'judul_kegiatan' => "{$jenis} {$tahun}",
        'tahun_kegiatan' => $tahun,
    ]);

    foreach ($dosenList as $dosen) {
        AnggotaKegiatan::create([
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'id_dosen' => $dosen->id_dosen,
            'jenis_anggota' => 'dosen',
            'peran' => 'Anggota',
        ]);
    }

    if ($dana !== null) {
        PendanaanKegiatan::create(['id_kegiatan' => $kegiatan->id_kegiatan, 'sumber_dana' => 'DIPA', 'jumlah_dana' => $dana]);
    }

    for ($i = 0; $i < $jumlahLuaran; $i++) {
        Luaran::create([
            'id_dosen' => $dosenList[0]->id_dosen,
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'id_jenis_luaran' => JenisLuaran::firstOrCreate(['nama_jenis_luaran' => 'Jurnal'])->id_jenis_luaran,
            'nama_jurnal' => 'Jurnal Uji',
        ]);
    }

    return $kegiatan;
}

function skenarioAs()
{
    $y = today()->year;

    $a = buatDosenAs('Dosen A');
    $b = buatDosenAs('Dosen B');
    $c = buatDosenAs('Dosen C');
    $d = buatDosenAs('Dosen D');
    $e = buatDosenAs('Dosen E', 'Non-DTPS');

    kegiatanAs('Penelitian', $y, [$a, $b], 10000000, 1);
    kegiatanAs('Penelitian', $y, [$a], null, 2);
    kegiatanAs('Penelitian', $y, [$c, $e], 6000000);
    kegiatanAs('Penelitian', $y - 1, [$d], 50000000, 3);
    kegiatanAs('PkM', $y, [$d], 80000000, 4);
}

function indikatorAs($response)
{
    return collect($response->json('data.indikator'))->keyBy('kode');
}

test('menghitung nilai metrik, skor rubrik, dan nilai bidang penelitian tahun berjalan', function () {
    skenarioAs();

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/assessment/penelitian-detail');

    $response->assertStatus(200)->assertJson(['success' => true]);

    $indikator = indikatorAs($response);

    expect($response->json('data.total_dtps'))->toBe(4)
        ->and($indikator['PEN-01']['nilai_metrik'])->toEqual(0.75)
        ->and($indikator['PEN-01']['skor'])->toBe(3)
        ->and($indikator['PEN-02']['nilai_metrik'])->toEqual(75)
        ->and($indikator['PEN-02']['skor'])->toBe(3)
        ->and($indikator['PEN-03']['nilai_metrik'])->toEqual(0.75)
        ->and($indikator['PEN-03']['skor'])->toBe(3)
        ->and($indikator['PEN-04']['nilai_metrik'])->toEqual(4000000)
        ->and($indikator['PEN-04']['skor'])->toBe(1);

    expect($response->json('data.ringkasan.nilai_bidang'))->toEqual(2.8)
        ->and($response->json('data.ringkasan.gap_terdeteksi'))->toBe(4)
        ->and($response->json('data.ringkasan.indikator_terpenuhi'))->toBe(0);

    expect($indikator['PEN-01']['status'])->toBe('Gap Terdeteksi')
        ->and($indikator['PEN-01']['gap'])->toEqual(0.5)
        ->and($indikator['PEN-01']['level_dibutuhkan']['skor'])->toBe(4)
        ->and($indikator['PEN-01']['level_dibutuhkan']['min'])->toEqual(1);
});

test('target dari K3 yang lebih rendah membuat indikator terpenuhi', function () {
    $this->mock(K3ApiService::class, fn ($mock) => $mock->shouldReceive('getAmbangRubrik')->andReturn(3.0));
    skenarioAs();

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/assessment/penelitian-detail');

    $indikator = indikatorAs($response);

    expect($indikator['PEN-01']['status'])->toBe('Terpenuhi')
        ->and($indikator['PEN-01']['gap'])->toEqual(0)
        ->and($indikator['PEN-04']['status'])->toBe('Gap Terdeteksi')
        ->and($indikator['PEN-04']['gap'])->toEqual(2)
        ->and($indikator['PEN-04']['level_dibutuhkan']['min'])->toEqual(7500000)
        ->and($response->json('data.ringkasan.indikator_terpenuhi'))->toBe(3);
});

test('filter tahun menghitung data tahun tersebut saja', function () {
    skenarioAs();

    $tahun = today()->year - 1;
    $response = $this->withToken($this->adminToken)->getJson("/api/v1/assessment/penelitian-detail?tahun={$tahun}");

    $indikator = indikatorAs($response);

    expect($indikator['PEN-01']['nilai_metrik'])->toEqual(0.25)
        ->and($indikator['PEN-01']['skor'])->toBe(1)
        ->and($indikator['PEN-03']['nilai_metrik'])->toEqual(0.75)
        ->and($indikator['PEN-04']['nilai_metrik'])->toEqual(12500000)
        ->and($indikator['PEN-04']['skor'])->toBe(4);
});

test('tanpa dosen DTPS semua skor nol dan tidak error', function () {
    $response = $this->withToken($this->adminToken)->getJson('/api/v1/assessment/penelitian-detail');

    $response->assertStatus(200);

    expect($response->json('data.ringkasan.nilai_bidang'))->toEqual(0)
        ->and(indikatorAs($response)['PEN-01']['skor'])->toBe(0);
});

test('parameter tidak valid ditolak dengan 422', function () {
    $this->withToken($this->adminToken)->getJson('/api/v1/assessment/penelitian-detail?tahun=24')->assertStatus(422);
    $this->withToken($this->adminToken)->getJson('/api/v1/assessment/penelitian-detail?jenjang=S9')->assertStatus(422);
});

test('role dosen ditolak dengan 403', function () {
    $this->withToken($this->dosenToken)->getJson('/api/v1/assessment/penelitian-detail')->assertStatus(403);
});

test('tanpa token ditolak dengan 401', function () {
    $this->getJson('/api/v1/assessment/penelitian-detail')->assertStatus(401);
});