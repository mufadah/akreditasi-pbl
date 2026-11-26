<?php

use App\Models\AnggotaKegiatan;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\JenisKegiatan;
use App\Models\JenisLuaran;
use App\Models\Kegiatan;
use App\Models\KonfigurasiMetrik;
use App\Models\Luaran;
use App\Models\Notifikasi;
use App\Models\Pendidikan;
use App\Services\K1ApiService;
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

    $this->mock(K1ApiService::class, fn ($mock) => $mock->shouldReceive('getStatistikMahasiswa')->andReturn(['total_mahasiswa' => 70]));
});

function buatDosenEw($nama, $status = 'DTPS')
{
    static $urut = 0;
    $urut++;

    return Dosen::create([
        'id_jabatan' => JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Lektor'])->id_jabatan,
        'id_pendidikan' => Pendidikan::firstOrCreate(
            ['jenjang' => 'S2'],
            ['universitas' => 'Universitas Uji', 'program_studi' => 'Informatika', 'tahun_lulus' => 2020]
        )->id_pendidikan,
        'nidn' => sprintf('22222%05d', $urut),
        'nama_dosen' => $nama,
        'email' => "ew{$urut}@uji.test",
        'status_dosen' => $status,
    ]);
}

function penelitianEw($dosen, $tahun)
{
    $kegiatan = Kegiatan::create([
        'id_jenis_kegiatan' => JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => 'Penelitian'], ['deskripsi' => 'uji'])->id_jenis_kegiatan,
        'judul_kegiatan' => "Penelitian {$tahun}",
        'tahun_kegiatan' => $tahun,
    ]);

    AnggotaKegiatan::create([
        'id_kegiatan' => $kegiatan->id_kegiatan,
        'id_dosen' => $dosen->id_dosen,
        'jenis_anggota' => 'dosen',
        'peran' => 'Ketua',
    ]);
}

function luaranEw($dosen, $jenis, $atribut)
{
    Luaran::create($atribut + [
        'id_dosen' => $dosen->id_dosen,
        'id_jenis_luaran' => JenisLuaran::firstOrCreate(['nama_jenis_luaran' => $jenis])->id_jenis_luaran,
    ]);
}

function sertifikasiEw($dosen, $jenis, $kedaluwarsa)
{
    DB::table('sertifikasi')->insert([
        'id_dosen' => $dosen->id_dosen,
        'jenis_sertifikasi' => $jenis,
        'tahun_sertifikasi' => '2024-01-01',
        'tanggal_kedaluwarsa' => $kedaluwarsa,
        'status_sertifikasi' => 'Aktif',
        'url_sertifikasi' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function skenarioEw()
{
    $y = today()->year;

    $a = buatDosenEw('Dosen A');
    $b = buatDosenEw('Dosen B');
    $c = buatDosenEw('Dosen C');
    buatDosenEw('Dosen D', 'Non-DTPS');

    penelitianEw($a, $y - 3);
    penelitianEw($b, $y - 1);

    luaranEw($a, 'Jurnal', ['tanggal_terbit' => ($y - 1).'-06-01']);
    luaranEw($c, 'Buku', ['tahun_terbit' => $y - 5]);

    sertifikasiEw($a, 'Serdos', today()->addMonths(3)->toDateString());
    sertifikasiEw($b, 'Serdos', today()->subYear()->toDateString());
    sertifikasiEw($b, 'Serdos', today()->addYears(2)->toDateString());
    sertifikasiEw($c, 'Serdos', today()->subDay()->toDateString());

    return [$a, $b, $c];
}

function indikatorEw($response)
{
    return collect($response->json('data.indikator'))->keyBy('kode');
}

test('mendeteksi dosen berisiko pada keempat indikator', function () {
    [$a, $b, $c] = skenarioEw();

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/early-warning?rasio_maks=20');

    $response->assertStatus(200)->assertJson(['success' => true]);

    $indikator = indikatorEw($response);

    expect(collect($indikator['EW-PENELITIAN']['daftar'])->pluck('id_dosen')->all())->toEqual([$a->id_dosen, $c->id_dosen])
        ->and(collect($indikator['EW-PUBLIKASI']['daftar'])->pluck('id_dosen')->all())->toEqual([$b->id_dosen, $c->id_dosen]);

    $sertifikasi = collect($indikator['EW-SERTIFIKASI']['daftar'])->keyBy('id_dosen');

    expect($sertifikasi)->toHaveCount(2)
        ->and($sertifikasi[$a->id_dosen]['status'])->toBe('segera_kedaluwarsa')
        ->and($sertifikasi[$c->id_dosen]['status'])->toBe('sudah_kedaluwarsa')
        ->and($sertifikasi->has($b->id_dosen))->toBeFalse();

    expect($indikator['EW-RASIO']['status'])->toBe('melebihi_batas')
        ->and($indikator['EW-RASIO']['rasio_dosen_mahasiswa'])->toEqual(23.33)
        ->and($response->json('data.ringkasan.total_peringatan'))->toBe(7)
        ->and($response->json('data.total_dosen'))->toBe(3);
});

test('parameter mengubah jendela deteksi', function () {
    [$a, $b, $c] = skenarioEw();

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/early-warning?bulan_sertifikasi=1&tahun_tanpa_penelitian=5');
    $response->assertStatus(200);
    $indikator = indikatorEw($response);

    expect(collect($indikator['EW-SERTIFIKASI']['daftar'])->pluck('id_dosen')->all())->toEqual([$c->id_dosen])
        ->and(collect($indikator['EW-PENELITIAN']['daftar'])->pluck('id_dosen')->all())->toEqual([$c->id_dosen]);
});

test('rasio: batas belum diatur jika tidak ada parameter maupun konfigurasi', function () {
    skenarioEw();

    $indikator = indikatorEw($this->withToken($this->adminToken)->getJson('/api/v1/early-warning'));

    expect($indikator['EW-RASIO']['status'])->toBe('batas_belum_diatur');
});

test('rasio: aman jika di bawah batas pada konfigurasi metrik', function () {
    skenarioEw();

    KonfigurasiMetrik::create([
        'kode_indikator' => 'EW-RASIO',
        'nama_indikator' => 'Batas rasio mahasiswa per dosen',
        'bidang' => 'SDM',
        'rumus_formula' => 'total_mahasiswa / total_dtps',
        'ambang_target' => 100,
        'jenjang' => 'S1',
    ]);

    $indikator = indikatorEw($this->withToken($this->adminToken)->getJson('/api/v1/early-warning'));

    expect($indikator['EW-RASIO']['status'])->toBe('aman')
        ->and($indikator['EW-RASIO']['batas_maksimal'])->toEqual(100);
});

test('rasio: data tidak tersedia jika API mahasiswa tidak menjawab', function () {
    $this->mock(K1ApiService::class, fn ($mock) => $mock->shouldReceive('getStatistikMahasiswa')->andReturn(null));
    skenarioEw();

    $indikator = indikatorEw($this->withToken($this->adminToken)->getJson('/api/v1/early-warning?rasio_maks=20'));

    expect($indikator['EW-RASIO']['status'])->toBe('data_tidak_tersedia')
        ->and($indikator['EW-RASIO']['jumlah_peringatan'])->toBe(0);
});

test('parameter tidak valid ditolak dengan 422', function () {
    $this->withToken($this->adminToken)->getJson('/api/v1/early-warning?bulan_sertifikasi=0')->assertStatus(422);
    $this->withToken($this->adminToken)->getJson('/api/v1/early-warning?rasio_maks=-5')->assertStatus(422);
});

test('role dosen ditolak dengan 403', function () {
    $this->withToken($this->dosenToken)->getJson('/api/v1/early-warning')->assertStatus(403);
});

test('tanpa token ditolak dengan 401', function () {
    $this->getJson('/api/v1/early-warning')->assertStatus(401);
});

test('perintah early-warning:check mengirim satu notifikasi per indikator per hari', function () {
    skenarioEw();

    $this->artisan('early-warning:check')->assertExitCode(0);

    expect(Notifikasi::where('tipe', 'EW_PENELITIAN')->count())->toBe(1)
        ->and(Notifikasi::where('tipe', 'EW_PUBLIKASI')->count())->toBe(1)
        ->and(Notifikasi::where('tipe', 'EW_SERTIFIKASI')->count())->toBe(1)
        ->and(Notifikasi::where('tipe', 'EW_RASIO')->count())->toBe(0);

    $this->artisan('early-warning:check')->assertExitCode(0);

    expect(Notifikasi::where('tipe', 'EW_PENELITIAN')->count())->toBe(1);
});