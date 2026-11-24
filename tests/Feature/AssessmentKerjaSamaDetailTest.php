<?php

use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\Pendidikan;
use App\Services\K3ApiService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

    config(['assessment.kerjasama' => [
        'KS-01' => [
            'nama' => 'Uji kerja sama internasional dan nasional',
            'metrik' => 'jumlah_internasional_nasional',
            'satuan' => 'kerja sama yang berlaku',
            'bobot' => 1.0,
            'rubrik' => [['skor' => 4, 'min' => 6], ['skor' => 3, 'min' => 4], ['skor' => 2, 'min' => 3], ['skor' => 1, 'min' => 1]],
        ],
    ]]);

    $this->mock(K3ApiService::class, fn ($mock) => $mock->shouldReceive('getAmbangRubrik')->andReturn(3.5));
});

function dosenKs()
{
    return Dosen::query()->first() ?? Dosen::create([
        'id_jabatan' => JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Lektor'])->id_jabatan,
        'id_pendidikan' => Pendidikan::firstOrCreate(
            ['jenjang' => 'S2'],
            ['universitas' => 'Universitas Uji', 'program_studi' => 'Informatika', 'tahun_lulus' => 2020]
        )->id_pendidikan,
        'nidn' => '4444400001',
        'nama_dosen' => 'Dosen Kerja Sama',
        'email' => 'ks@uji.test',
        'status_dosen' => 'DTPS',
    ]);
}

function mitraKs()
{
    static $urut = 0;
    $urut++;

    return DB::table('mitra')->insertGetId([
        'nama_mitra' => "Mitra {$urut}",
        'jenis_mitra' => 'Instansi',
        'created_at' => now(),
        'updated_at' => now(),
    ], 'id_mitra');
}

function kerjaSamaKs($tingkat, $mulai, $selesai = null, $status = 'Aktif', $idMitra = null)
{
    static $urut = 0;
    $urut++;

    $idJenis = DB::table('jenis_kerja_sama')->value('id_jenis_kerjasama')
        ?? DB::table('jenis_kerja_sama')->insertGetId([
            'nama_jenis_kerjasama' => 'MoA',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_jenis_kerjasama');

    DB::table('kerja_sama')->insert([
        'id_mitra' => $idMitra ?? mitraKs(),
        'id_jenis_kerjasama' => $idJenis,
        'id_dosen' => dosenKs()->id_dosen,
        'nomor_dokumen' => "KS/{$urut}",
        'jenis_dokumen' => 'MoA',
        'tanggal_mulai' => $mulai,
        'tanggal_selesai' => $selesai,
        'status' => $status,
        'judul_kerja_sama' => "Kerja sama {$urut}",
        'tingkat' => $tingkat,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function skenarioKs()
{
    $y = today()->year;
    $mitraBersama = mitraKs();

    kerjaSamaKs('Internasional', ($y - 1).'-03-01', ($y + 1).'-03-01', 'Aktif', $mitraBersama);
    kerjaSamaKs('Nasional', "{$y}-02-01", null, 'Aktif', $mitraBersama);
    kerjaSamaKs('Nasional', ($y - 2).'-01-01', ($y - 1).'-12-31', 'Kedaluwarsa');
    kerjaSamaKs('Lokal', "{$y}-01-01", ($y + 1).'-01-01');
    kerjaSamaKs('Internasional', "{$y}-01-10", null, 'Non-aktif');
    kerjaSamaKs('NASIONAL', "{$y}-03-01", "{$y}-11-30");
}

test('menghitung kerja sama internasional dan nasional yang berlaku pada tahun berjalan', function () {
    skenarioKs();

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/assessment/kerjasama-detail');

    $response->assertStatus(200)->assertJson(['success' => true]);

    $ks = collect($response->json('data.indikator'))->keyBy('kode')['KS-01'];

    expect($response->json('data.bidang'))->toBe('kerjasama')
        ->and($response->json('data.rincian'))->toEqual([
            'total_berlaku' => 4,
            'internasional' => 1,
            'nasional' => 2,
            'lainnya' => 1,
            'mitra_unik' => 3,
        ])
        ->and($ks['nilai_metrik'])->toBe(3)
        ->and($ks['skor'])->toBe(2)
        ->and($ks['status'])->toBe('Gap Terdeteksi')
        ->and($ks['gap'])->toEqual(1.5)
        ->and($ks['level_dibutuhkan']['skor'])->toBe(4)
        ->and($ks['level_dibutuhkan']['min'])->toBe(6)
        ->and($response->json('data.ringkasan.nilai_bidang'))->toEqual(2)
        ->and($response->json('data.catatan_data.kriteria_mahasiswa_terlibat'))->toContain('Belum dinilai');
});

test('filter tahun menghitung kerja sama yang berlaku pada tahun tersebut termasuk yang sudah kedaluwarsa', function () {
    skenarioKs();

    $tahun = today()->year - 1;
    $response = $this->withToken($this->adminToken)->getJson("/api/v1/assessment/kerjasama-detail?tahun={$tahun}");

    $ks = collect($response->json('data.indikator'))->keyBy('kode')['KS-01'];

    expect($response->json('data.rincian.internasional'))->toBe(1)
        ->and($response->json('data.rincian.nasional'))->toBe(1)
        ->and($ks['nilai_metrik'])->toBe(2)
        ->and($ks['skor'])->toBe(1);
});

test('target K3 yang lebih rendah membuat indikator terpenuhi', function () {
    $this->mock(K3ApiService::class, fn ($mock) => $mock->shouldReceive('getAmbangRubrik')->andReturn(2.0));
    skenarioKs();

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/assessment/kerjasama-detail');

    $ks = collect($response->json('data.indikator'))->keyBy('kode')['KS-01'];

    expect($ks['status'])->toBe('Terpenuhi')
        ->and($ks['gap'])->toEqual(0)
        ->and($response->json('data.ringkasan.indikator_terpenuhi'))->toBe(1);
});

test('tanpa kerja sama skor nol dan tidak error', function () {
    $response = $this->withToken($this->adminToken)->getJson('/api/v1/assessment/kerjasama-detail');

    $response->assertStatus(200);

    expect($response->json('data.rincian.total_berlaku'))->toBe(0)
        ->and(collect($response->json('data.indikator'))->keyBy('kode')['KS-01']['skor'])->toBe(0);
});

test('parameter tidak valid ditolak dengan 422', function () {
    $this->withToken($this->adminToken)->getJson('/api/v1/assessment/kerjasama-detail?tahun=24')->assertStatus(422);
    $this->withToken($this->adminToken)->getJson('/api/v1/assessment/kerjasama-detail?jenjang=S9')->assertStatus(422);
});

test('role dosen ditolak dengan 403', function () {
    $this->withToken($this->dosenToken)->getJson('/api/v1/assessment/kerjasama-detail')->assertStatus(403);
});

test('tanpa token ditolak dengan 401', function () {
    $this->getJson('/api/v1/assessment/kerjasama-detail')->assertStatus(401);
});