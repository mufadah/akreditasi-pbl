<?php

use App\Models\AnggotaKegiatan;
use App\Models\Dosen;
use App\Models\JenisKegiatan;
use App\Models\JenisLuaran;
use App\Models\Kegiatan;
use App\Models\Luaran;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->dosenA = Dosen::create([
        'id_jabatan' => 1, 'id_pendidikan' => 1, 'nama_dosen' => 'Dosen Filter A',
        'nidn' => '7000000001', 'email' => 'filter-a@polines.ac.id', 'status_dosen' => 'DTPS',
    ]);
    $this->dosenB = Dosen::create([
        'id_jabatan' => 1, 'id_pendidikan' => 1, 'nama_dosen' => 'Dosen Filter B',
        'nidn' => '7000000002', 'email' => 'filter-b@polines.ac.id', 'status_dosen' => 'DTPS',
    ]);
    $this->token = JWT::encode([
        'sub' => 1,
        'email' => 'filter@polines.ac.id',
        'role' => 'ADMINISTRATOR',
        'exp' => time() + 3600,
    ], config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes'), 'HS256');
});

function kegiatanBaru(string $jenis, string $judul, ?Dosen $dosen = null): Kegiatan
{
    $kegiatan = Kegiatan::create([
        'id_jenis_kegiatan' => JenisKegiatan::where('nama_jenis_kegiatan', $jenis)->value('id_jenis_kegiatan'),
        'judul_kegiatan' => $judul,
        'tahun_kegiatan' => 2025,
        'status_kegiatan' => 'Selesai',
    ]);

    if ($dosen) {
        AnggotaKegiatan::create([
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'id_dosen' => $dosen->id_dosen,
            'jenis_anggota' => 'dosen',
            'peran' => 'Ketua',
        ]);
    }

    return $kegiatan;
}

function luaranBaru(Dosen $dosen, string $namaJenis, array $atribut): Luaran
{
    $jenis = JenisLuaran::where('nama_jenis_luaran', 'like', "%{$namaJenis}%")->firstOrFail();

    return Luaran::create($atribut + [
        'id_dosen' => $dosen->id_dosen,
        'id_jenis_luaran' => $jenis->id_jenis_luaran,
    ]);
}

test('kegiatan dapat difilter berdasarkan nama jenis penelitian atau pkm', function () {
    kegiatanBaru('Penelitian', 'Judul Penelitian Filter');
    kegiatanBaru('PkM', 'Judul PkM Filter');

    $penelitian = $this->withToken($this->token)->getJson('/api/kegiatan?jenis=penelitian&per_page=100')
        ->assertOk()->json('data.data');
    $pkm = $this->withToken($this->token)->getJson('/api/kegiatan?jenis=PKM&per_page=100')
        ->assertOk()->json('data.data');

    expect(collect($penelitian)->pluck('judul_kegiatan'))->toContain('Judul Penelitian Filter')
        ->not->toContain('Judul PkM Filter')
        ->and(collect($pkm)->pluck('judul_kegiatan'))->toContain('Judul PkM Filter')
        ->not->toContain('Judul Penelitian Filter');
});

test('kegiatan dapat difilter berdasarkan dosen anggota', function () {
    kegiatanBaru('Penelitian', 'Kegiatan Milik A', $this->dosenA);
    kegiatanBaru('Penelitian', 'Kegiatan Milik B', $this->dosenB);

    $judul = collect($this->withToken($this->token)
        ->getJson("/api/kegiatan?id_dosen={$this->dosenA->id_dosen}&per_page=100")
        ->assertOk()->json('data.data'))->pluck('judul_kegiatan');

    expect($judul)->toContain('Kegiatan Milik A')->not->toContain('Kegiatan Milik B');
});

test('luaran dapat difilter per kategori ala sinta', function () {
    luaranBaru($this->dosenA, 'Jurnal', ['nama_jurnal' => 'Artikel Kategori Uji']);
    luaranBaru($this->dosenA, 'Buku', ['judul_buku' => 'Buku Kategori Uji']);
    luaranBaru($this->dosenA, 'HKI', ['judul_hak_cipta' => 'HKI Kategori Uji']);
    luaranBaru($this->dosenA, 'Prosiding', ['nama_seminar' => 'Seminar Kategori Uji']);

    $ambil = fn (string $kategori) => collect($this->withToken($this->token)
        ->getJson("/api/luaran?kategori={$kategori}&per_page=200")
        ->assertOk()->json('data.data'))
        ->map(fn ($l) => $l['nama_jurnal'] ?? $l['judul_buku'] ?? $l['judul_hak_cipta'] ?? $l['nama_seminar'])
        ->filter(fn ($judul) => str_contains((string) $judul, 'Kategori Uji'))
        ->values()->all();

    expect($ambil('artikel'))->toBe(['Artikel Kategori Uji'])
        ->and($ambil('buku'))->toBe(['Buku Kategori Uji'])
        ->and($ambil('hki'))->toBe(['HKI Kategori Uji'])
        ->and($ambil('seminar'))->toBe(['Seminar Kategori Uji']);
});

test('luaran menolak kategori yang tidak dikenal', function () {
    $this->withToken($this->token)->getJson('/api/luaran?kategori=tidak-ada')
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('luaran dapat difilter per tahun dari tahun terbit maupun tanggal terkait', function () {
    luaranBaru($this->dosenA, 'Jurnal', ['nama_jurnal' => 'Terbit 2019 Uji', 'tahun_terbit' => 2019]);
    luaranBaru($this->dosenA, 'HKI', ['judul_hak_cipta' => 'HKI 2018 Uji', 'tanggal_terbit_sertifikat_hki' => '2018-05-10']);
    luaranBaru($this->dosenA, 'Prosiding', ['nama_seminar' => 'Seminar 2017 Uji', 'tanggal_seminar' => '2017-09-01']);

    $judulTahun = fn (int $tahun) => collect($this->withToken($this->token)
        ->getJson("/api/luaran?tahun={$tahun}&per_page=200")
        ->assertOk()->json('data.data'))
        ->map(fn ($l) => $l['nama_jurnal'] ?? $l['judul_hak_cipta'] ?? $l['nama_seminar'])
        ->filter(fn ($judul) => str_contains((string) $judul, 'Uji'))
        ->values()->all();

    expect($judulTahun(2019))->toBe(['Terbit 2019 Uji'])
        ->and($judulTahun(2018))->toBe(['HKI 2018 Uji'])
        ->and($judulTahun(2017))->toBe(['Seminar 2017 Uji']);
});
