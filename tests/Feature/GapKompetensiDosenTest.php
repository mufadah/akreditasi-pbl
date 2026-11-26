<?php

use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\KonfigurasiMetrik;
use App\Models\Pendidikan;
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
});

function buatDosenGap($nama, $jabatan, $jenjang, $status = 'DTPS')
{
    static $urut = 0;
    $urut++;

    $idJabatan = JabatanAkademik::firstOrCreate(['nama_jabatan' => $jabatan])->id_jabatan;
    $idPendidikan = Pendidikan::firstOrCreate(
        ['jenjang' => $jenjang],
        ['universitas' => 'Universitas Uji', 'program_studi' => 'Informatika', 'tahun_lulus' => 2020]
    )->id_pendidikan;

    return Dosen::create([
        'id_jabatan' => $idJabatan,
        'id_pendidikan' => $idPendidikan,
        'nidn' => sprintf('00000%05d', $urut),
        'nama_dosen' => $nama,
        'email' => "dosen{$urut}@uji.test",
        'status_dosen' => $status,
    ]);
}

function tambahSertifikasiGap($dosen, $kedaluwarsa = null, $status = 'Aktif')
{
    DB::table('sertifikasi')->insert([
        'id_dosen' => $dosen->id_dosen,
        'jenis_sertifikasi' => 'Sertifikasi Dosen',
        'tahun_sertifikasi' => '2024-01-01',
        'tanggal_kedaluwarsa' => $kedaluwarsa,
        'status_sertifikasi' => $status,
        'url_sertifikasi' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('menghitung persentase S3, lektor ke atas, dan tersertifikasi hanya untuk dosen DTPS', function () {
    $a = buatDosenGap('Dosen A', 'Lektor Kepala', 'S3');
    buatDosenGap('Dosen B', 'Lektor', 'S3');
    $c = buatDosenGap('Dosen C', 'Asisten Ahli', 'S2');
    buatDosenGap('Dosen D', 'Tenaga Pengajar', 'S2');
    buatDosenGap('Dosen E', 'Guru Besar', 'S3', 'Non-DTPS');

    tambahSertifikasiGap($a);
    tambahSertifikasiGap($c, now()->subDay()->toDateString());

    $response = $this->withToken($this->adminToken)
        ->getJson('/api/v1/gap-kompetensi-dosen?target_s3=60&target_lektor=50');

    $response->assertStatus(200)->assertJson(['success' => true]);

    expect($response->json('data.total_dosen'))->toBe(4);

    $indikator = collect($response->json('data.indikator'))->keyBy('kode');

    expect($indikator['GAP-S3-S1']['jumlah_dosen'])->toBe(2)
        ->and($indikator['GAP-S3-S1']['persentase'])->toEqual(50)
        ->and($indikator['GAP-S3-S1']['status'])->toBe('belum_terpenuhi')
        ->and($indikator['GAP-S3-S1']['kekurangan_dosen'])->toBe(1);

    expect($indikator['GAP-LEKTOR-S1']['jumlah_dosen'])->toBe(2)
        ->and($indikator['GAP-LEKTOR-S1']['status'])->toBe('terpenuhi')
        ->and($indikator['GAP-LEKTOR-S1']['kekurangan_dosen'])->toBe(0);

    expect($indikator['GAP-SERTIFIKASI-S1']['jumlah_dosen'])->toBe(1)
        ->and($indikator['GAP-SERTIFIKASI-S1']['persentase'])->toEqual(25)
        ->and($indikator['GAP-SERTIFIKASI-S1']['status'])->toBe('standar_belum_diatur');
});

test('standar diambil dari konfigurasi metrik jika parameter target tidak dikirim', function () {
    buatDosenGap('Dosen A', 'Lektor', 'S3');

    KonfigurasiMetrik::create([
        'kode_indikator' => 'GAP-S3-S1',
        'nama_indikator' => 'Standar persentase dosen S3',
        'bidang' => 'SDM',
        'rumus_formula' => '(COUNT(dosen_s3) / COUNT(dosen_dtps)) * 100',
        'ambang_target' => 40,
        'jenjang' => 'S1',
    ]);

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/gap-kompetensi-dosen');

    $indikator = collect($response->json('data.indikator'))->keyBy('kode');

    expect($indikator['GAP-S3-S1']['standar_persen'])->toEqual(40)
        ->and($indikator['GAP-S3-S1']['status'])->toBe('terpenuhi');
});

test('tanpa dosen DTPS persentase nol dan tidak error', function () {
    $response = $this->withToken($this->adminToken)->getJson('/api/v1/gap-kompetensi-dosen?target_s3=30');

    $response->assertStatus(200);

    expect($response->json('data.total_dosen'))->toBe(0);
    expect(collect($response->json('data.indikator'))->keyBy('kode')['GAP-S3-S1']['status'])->toBe('belum_terpenuhi');
});

test('jenjang tidak valid ditolak dengan 422', function () {
    $this->withToken($this->adminToken)
        ->getJson('/api/v1/gap-kompetensi-dosen?jenjang=S9')
        ->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('role dosen tidak boleh mengakses analisis gap', function () {
    $this->withToken($this->dosenToken)
        ->getJson('/api/v1/gap-kompetensi-dosen')
        ->assertStatus(403);
});

test('tanpa token ditolak dengan 401', function () {
    $this->getJson('/api/v1/gap-kompetensi-dosen')->assertStatus(401);
});