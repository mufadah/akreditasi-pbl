<?php

use App\Models\Evidence;
use App\Models\Kegiatan;
use App\Models\KerjaSama;
use App\Models\Luaran;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('models and seeders can be queried without SQL error', function () {
    $this->seed();

    $kerjaSama = KerjaSama::first();
    $evidence = Evidence::first();
    $kegiatan = Kegiatan::first();
    $luaran = Luaran::first();

    expect(true)->toBeTrue();
});

test('api v1 dashboard returns successful response for all tabs', function () {
    $this->seed();

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $payload = [
        'sub' => 1,
        'email' => 'admin@polines.ac.id',
        'role' => 'ADMINISTRATOR',
        'permissions' => ['*'],
        'exp' => time() + 3600,
    ];
    $token = JWT::encode($payload, $secret, 'HS256');

    foreach (['ringkasan', 'produktivitas', 'rasio_jabatan', 'tren'] as $tab) {
        $dash = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/dashboard?tab={$tab}");

        $dash->assertStatus(200);
    }
});

test('swagger request bypasses jwt auth and returns successful response', function () {
    $response = $this->withHeader('X-Swagger-Request', 'true')
        ->getJson('/api/v1/dosen/statistik');

    $response->assertStatus(200)
        ->assertJson(['success' => true]);
});

test('referer from swagger documentation bypasses jwt auth', function () {
    $response = $this->withHeader('Referer', 'http://localhost:8000/api/documentation')
        ->getJson('/api/v1/dosen/statistik');

    $response->assertStatus(200)
        ->assertJson(['success' => true]);
});

test('post mitra with swagger payload succeeds', function () {
    $payload = [
        'nama_instansi' => 'PT Telekomunikasi Selular (Telkomsel)',
        'alamat' => 'Telkom Landmark Tower, Jl. Jend. Gatot Subroto Kav. 52 Jakarta',
        'email' => 'partnership@telkomsel.co.id',
        'telepon' => '021-5240811',
    ];

    $response = $this->withHeader('X-Swagger-Request', 'true')
        ->postJson('/api/mitra', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'nama_mitra' => 'PT Telekomunikasi Selular (Telkomsel)',
                'nama_instansi' => 'PT Telekomunikasi Selular (Telkomsel)',
            ],
        ]);

    $id = $response->json('data.id_mitra');

    $show = $this->withHeader('X-Swagger-Request', 'true')
        ->getJson("/api/mitra/{$id}");

    $show->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'nama_mitra' => 'PT Telekomunikasi Selular (Telkomsel)',
            ],
        ]);
});
