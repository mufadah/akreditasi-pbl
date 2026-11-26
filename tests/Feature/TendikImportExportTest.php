<?php

use App\Exports\TendikExport;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
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

test('template tendik dapat diunduh', function () {
    Excel::fake();

    $this->withToken($this->adminToken)
        ->get('/api/tendik/template')
        ->assertOk();

    Excel::assertDownloaded('template_tendik.xlsx');
});

test('export tendik dapat diunduh', function () {
    Excel::fake();

    $this->withToken($this->adminToken)
        ->get('/api/tendik/export')
        ->assertOk();

    Excel::assertDownloaded('data_tendik.xlsx');
});

test('import tendik menolak permintaan tanpa file', function () {
    $this->withToken($this->adminToken)
        ->postJson('/api/tendik/import', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

test('export tendik memiliki dua sheet', function () {
    $export = new TendikExport;

    expect($export->sheets())->toHaveCount(2);
});

test('template tendik memiliki dua sheet', function () {
    $export = new TendikExport(true);

    expect($export->sheets())->toHaveCount(2);
});
