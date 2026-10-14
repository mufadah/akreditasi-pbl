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
