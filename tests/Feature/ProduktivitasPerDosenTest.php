<?php

use App\Models\AnggotaKegiatan;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\JenisKegiatan;
use App\Models\JenisLuaran;
use App\Models\Kegiatan;
use App\Models\Luaran;
use App\Models\Pendidikan;
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
});

function buatDosenProd($nama, $status = 'DTPS')
{
    static $urut = 0;
    $urut++;

    return Dosen::create([
        'id_jabatan' => JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Lektor'])->id_jabatan,
        'id_pendidikan' => Pendidikan::firstOrCreate(
            ['jenjang' => 'S2'],
            ['universitas' => 'Universitas Uji', 'program_studi' => 'Informatika', 'tahun_lulus' => 2020]
        )->id_pendidikan,
        'nidn' => sprintf('11111%05d', $urut),
        'nama_dosen' => $nama,
        'email' => "prod{$urut}@uji.test",
        'status_dosen' => $status,
    ]);
}

function buatKegiatanProd($jenis, $tahun, $dosenList)
{
    $idJenis = JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => $jenis], ['deskripsi' => 'uji'])->id_jenis_kegiatan;

    $kegiatan = Kegiatan::create([
        'id_jenis_kegiatan' => $idJenis,
        'judul_kegiatan' => "{$jenis} {$tahun}",
        'tahun_kegiatan' => $tahun,
    ]);

    foreach ($dosenList as $peran => $dosen) {
        AnggotaKegiatan::create([
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'id_dosen' => $dosen->id_dosen,
            'jenis_anggota' => 'dosen',
            'peran' => $peran,
        ]);
    }

    return $kegiatan;
}

function buatLuaranProd($dosen, $indeksasi, $kegiatan = null)
{
    return Luaran::create([
        'id_dosen' => $dosen->id_dosen,
        'id_kegiatan' => $kegiatan?->id_kegiatan,
        'id_jenis_luaran' => JenisLuaran::firstOrCreate(['nama_jenis_luaran' => 'Jurnal'])->id_jenis_luaran,
        'nama_jurnal' => 'Jurnal Uji',
        'indeksasi' => $indeksasi,
    ]);
}

test('menghitung penelitian, pkm, luaran, dan skor per dosen per tahun', function () {
    $a = buatDosenProd('Dosen A');
    $b = buatDosenProd('Dosen B');
    $c = buatDosenProd('Dosen C', 'Non-DTPS');

    $penelitian = buatKegiatanProd('Penelitian', 2024, ['Ketua' => $a]);
    buatKegiatanProd('Penelitian', 2024, ['Anggota' => $a, 'Ketua' => $b]);
    buatKegiatanProd('PkM', 2025, ['Ketua' => $a]);
    buatKegiatanProd('Penelitian', 2024, ['Ketua' => $c]);

    AnggotaKegiatan::create([
        'id_kegiatan' => $penelitian->id_kegiatan,
        'id_dosen' => $a->id_dosen,
        'jenis_anggota' => 'dosen',
        'peran' => 'Anggota',
    ]);

    buatLuaranProd($a, 'Scopus Q1', $penelitian);
    buatLuaranProd($a, 'SINTA 2');

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/produktivitas-per-dosen');

    $response->assertStatus(200)->assertJson(['success' => true]);

    expect($response->json('data.total_dosen'))->toBe(2);

    $daftar = collect($response->json('data.daftar_dosen'))->keyBy('id_dosen');
    $perTahunA = collect($daftar[$a->id_dosen]['per_tahun'])->keyBy('tahun');

    expect($perTahunA[2024]['jumlah_penelitian'])->toBe(2)
        ->and($perTahunA[2024]['jumlah_pkm'])->toBe(0)
        ->and($perTahunA[2024]['luaran']['scopus'])->toBe(1)
        ->and($perTahunA[2024]['skor_produktivitas'])->toBe(3);

    expect($perTahunA[2025]['jumlah_pkm'])->toBe(1)
        ->and($perTahunA[2025]['skor_produktivitas'])->toBe(1);

    expect($perTahunA[now()->year]['luaran']['non_indeks'])->toBe(1);

    expect($daftar[$a->id_dosen]['total']['skor_produktivitas'])->toBe(5);

    expect($daftar[$b->id_dosen]['per_tahun'][0]['jumlah_penelitian'])->toBe(1);
    expect($daftar->has($c->id_dosen))->toBeFalse();
});

test('dosen tanpa aktivitas tetap muncul dengan skor nol', function () {
    $d = buatDosenProd('Dosen Kosong');

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/produktivitas-per-dosen');

    $dosen = collect($response->json('data.daftar_dosen'))->keyBy('id_dosen')[$d->id_dosen];

    expect($dosen['per_tahun'])->toBe([])
        ->and($dosen['total']['skor_produktivitas'])->toBe(0);
});

test('filter tahun hanya memuat data tahun tersebut', function () {
    $a = buatDosenProd('Dosen A');
    buatKegiatanProd('Penelitian', 2024, ['Ketua' => $a]);
    buatKegiatanProd('PkM', 2025, ['Ketua' => $a]);

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/produktivitas-per-dosen?tahun=2025');

    $perTahun = $response->json('data.daftar_dosen.0.per_tahun');

    expect($perTahun)->toHaveCount(1)
        ->and($perTahun[0]['tahun'])->toBe(2025)
        ->and($perTahun[0]['jumlah_pkm'])->toBe(1);
});

test('filter id_dosen hanya memuat satu dosen', function () {
    $a = buatDosenProd('Dosen A');
    buatDosenProd('Dosen B');

    $response = $this->withToken($this->adminToken)->getJson("/api/v1/produktivitas-per-dosen?id_dosen={$a->id_dosen}");

    expect($response->json('data.total_dosen'))->toBe(1)
        ->and($response->json('data.daftar_dosen.0.id_dosen'))->toBe($a->id_dosen);
});

test('id dosen tidak ada atau tahun tidak valid ditolak dengan 422', function () {
    $this->withToken($this->adminToken)->getJson('/api/v1/produktivitas-per-dosen?id_dosen=99999')->assertStatus(422);
    $this->withToken($this->adminToken)->getJson('/api/v1/produktivitas-per-dosen?tahun=24')->assertStatus(422);
});

test('role dosen ditolak dengan 403', function () {
    $this->withToken($this->dosenToken)->getJson('/api/v1/produktivitas-per-dosen')->assertStatus(403);
});

test('tanpa token ditolak dengan 401', function () {
    $this->getJson('/api/v1/produktivitas-per-dosen')->assertStatus(401);
});
