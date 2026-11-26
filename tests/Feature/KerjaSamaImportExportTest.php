<?php

use App\Exports\KerjaSamaExport;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $secret = config(
        'jwt.secret',
        'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes'
    );

    $this->adminToken = JWT::encode([
        'sub' => 1,
        'email' => 'admin@polines.ac.id',
        'role' => 'ADMINISTRATOR',
        'permissions' => ['*'],
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $this->seed(DatabaseSeeder::class);
});

test('endpoint template kerja sama dapat diakses', function () {
    Excel::fake();

    $this->withToken($this->adminToken)
        ->get('/api/kerja-sama/template')
        ->assertOk();

    Excel::assertDownloaded('template_kerja_sama.xlsx');
});

test('endpoint export kerja sama dapat diakses', function () {
    Excel::fake();

    $this->withToken($this->adminToken)
        ->get('/api/kerja-sama/export')
        ->assertOk();

    Excel::assertDownloaded('data_kerja_sama.xlsx');
});

test('endpoint import menolak permintaan tanpa file', function () {
    $this->withToken($this->adminToken)
        ->postJson('/api/kerja-sama/import', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

test('export menggunakan query kerja sama', function () {
    $export = new KerjaSamaExport;

    expect($export->query())->toBeInstanceOf(
        Builder::class
    );

    expect($export->headings())->toContain(
        'nama_mitra',
        'nama_jenis_kerjasama',
        'nidn'
    );
});

test('template export tidak mengambil data kerja sama', function () {
    $export = new KerjaSamaExport(true);

    expect($export->query()->count())->toBe(0);
});
