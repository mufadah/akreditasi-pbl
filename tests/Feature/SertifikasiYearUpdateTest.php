<?php

use App\Models\Sertifikasi;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('updating certification with a four digit year stores the first day of that year', function () {
    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $token = JWT::encode([
        'sub' => 1,
        'email' => 'admin@polines.ac.id',
        'role' => 'ADMINISTRATOR',
        'permissions' => ['*'],
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $sertifikasi = Sertifikasi::create([
        'jenis_sertifikasi' => 'Sertifikat Kompetensi',
        'tahun_sertifikasi' => '2025-01-01',
        'url_sertifikasi' => '',
    ]);

    $response = $this->withToken($token)->putJson(
        "/api/sertifikasi/{$sertifikasi->id_sertifikasi}",
        ['tahun_sertifikasi' => 2024],
    );

    $response->assertOk()
        ->assertJsonPath('data.tahun_sertifikasi', '2024-01-01');

    $this->assertDatabaseHas('sertifikasi', [
        'id_sertifikasi' => $sertifikasi->id_sertifikasi,
        'tahun_sertifikasi' => '2024-01-01',
    ]);
});
