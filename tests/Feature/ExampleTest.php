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

    $response = $this->postJson('/api/login', [
        'email' => 'admin@polines.ac.id',
        'password' => 'password123',
    ]);

    $token = $response->json('data.authorization.access_token');

    foreach (['ringkasan', 'produktivitas', 'rasio_jabatan', 'tren'] as $tab) {
        $dash = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/dashboard?tab={$tab}");

        $dash->assertStatus(200);
    }
});
