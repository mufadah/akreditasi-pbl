<?php

use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\Pendidikan;
use App\Models\StatistikSnapshot;
use App\Services\K1ApiService;
use App\Services\K3ApiService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(K3ApiService::class, fn ($mock) => $mock->shouldReceive('getAmbangRubrik')->andReturn(3.5));
    $this->mock(K1ApiService::class, fn ($mock) => $mock->shouldReceive('getStatistikMahasiswa')->andReturn(['total_mahasiswa' => 70]));
});

function dosenJadwal()
{
    return Dosen::create([
        'id_jabatan' => JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Lektor'])->id_jabatan,
        'id_pendidikan' => Pendidikan::firstOrCreate(
            ['jenjang' => 'S2'],
            ['universitas' => 'Universitas Uji', 'program_studi' => 'Informatika', 'tahun_lulus' => 2020]
        )->id_pendidikan,
        'nidn' => '6666600001',
        'nama_dosen' => 'Dosen Jadwal',
        'email' => 'jadwal@uji.test',
        'status_dosen' => 'DTPS',
    ]);
}

function eventJadwal($perintah)
{
    test()->artisan('schedule:list')->assertExitCode(0);

    return collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, $perintah));
}

test('early warning terdaftar harian pukul 06:00 WIB tanpa tumpang tindih', function () {
    $event = eventJadwal('early-warning:check');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 6 * * *')
        ->and($event->timezone)->toBe('Asia/Jakarta')
        ->and($event->withoutOverlapping)->toBeTrue();
});

test('snapshot statistik terdaftar harian pukul 05:00 WIB sebelum early warning', function () {
    $event = eventJadwal('statistik:snapshot');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 5 * * *')
        ->and($event->timezone)->toBe('Asia/Jakarta')
        ->and($event->withoutOverlapping)->toBeTrue();
});

test('perintah snapshot menyimpan ringkasan dashboard', function () {
    dosenJadwal();

    $this->artisan('statistik:snapshot', ['--tahun' => 2025])->assertExitCode(0);

    $snapshot = StatistikSnapshot::first();

    expect(StatistikSnapshot::count())->toBe(1)
        ->and($snapshot->tahun)->toBe(2025)
        ->and($snapshot->tanggal)->toBe(today()->toDateString())
        ->and(array_keys($snapshot->data))->toBe(['ringkasan', 'produktivitas', 'rasio', 'tren', 'early_warning'])
        ->and($snapshot->data['produktivitas'])->not->toHaveKey('daftar_dosen')
        ->and($snapshot->data['produktivitas']['total_dtps'])->toBe(1)
        ->and($snapshot->data['early_warning']['total_peringatan'])->toBeInt();
});

test('perintah snapshot dijalankan dua kali pada hari yang sama memperbarui baris yang sama', function () {
    $this->artisan('statistik:snapshot', ['--tahun' => 2025])->assertExitCode(0);

    expect(StatistikSnapshot::first()->data['produktivitas']['total_dtps'])->toBe(0);

    dosenJadwal();

    $this->artisan('statistik:snapshot', ['--tahun' => 2025])->assertExitCode(0);

    expect(StatistikSnapshot::count())->toBe(1)
        ->and(StatistikSnapshot::first()->data['produktivitas']['total_dtps'])->toBe(1);
});

test('tahun penilaian berbeda disimpan sebagai baris terpisah', function () {
    $this->artisan('statistik:snapshot', ['--tahun' => 2025])->assertExitCode(0);
    $this->artisan('statistik:snapshot', ['--tahun' => 2024])->assertExitCode(0);

    expect(StatistikSnapshot::count())->toBe(2)
        ->and(StatistikSnapshot::pluck('tahun')->sort()->values()->all())->toBe([2024, 2025]);
});

test('tahun tidak valid ditolak tanpa menyimpan apa pun', function () {
    $this->artisan('statistik:snapshot', ['--tahun' => 'abc'])->assertExitCode(1);

    expect(StatistikSnapshot::count())->toBe(0);
});

test('snapshot tetap tersimpan walau api mahasiswa tidak menjawab', function () {
    $this->mock(K1ApiService::class, fn ($mock) => $mock->shouldReceive('getStatistikMahasiswa')->andThrow(new RuntimeException('K1 mati')));

    $this->artisan('statistik:snapshot', ['--tahun' => 2025])->assertExitCode(0);

    expect(StatistikSnapshot::count())->toBe(1)
        ->and(StatistikSnapshot::first()->data['rasio'])->toBe([]);
});