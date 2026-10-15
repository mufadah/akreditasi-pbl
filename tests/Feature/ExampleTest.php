<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('models and seeders can be queried without SQL error', function () {
    $this->seed();

    $kerjaSama = \App\Models\KerjaSama::first();
    $evidence = \App\Models\Evidence::first();
    $kegiatan = \App\Models\Kegiatan::first();
    $luaran = \App\Models\Luaran::first();

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
    $token = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

    foreach (['ringkasan', 'produktivitas', 'rasio_jabatan', 'tren'] as $tab) {
        $dash = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/dashboard?tab={$tab}");

        $dash->assertStatus(200);
    }
});
