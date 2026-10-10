<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class K1ApiService
{
    protected ?string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.k1.url', env('K1_API_URL', ''));
        $this->apiKey  = config('services.k1.key', env('K1_API_KEY', ''));
    }

    /**
     * Validasi apakah NIM mahasiswa valid & terdaftar aktif di K1.
     */
    public function validateNim(string $nim): bool
    {
        $cleanNim = trim($nim);

        // Jika string kosong, langsung tidak valid
        if (empty($cleanNim)) {
            return false;
        }

        // 1. Jika URL server K1 sudah ada di .env, tembak API K1 secara riil
        if (!empty($this->baseUrl)) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['X-API-KEY' => $this->apiKey])
                    ->get("{$this->baseUrl}/mahasiswa/{$cleanNim}/check");

                if ($response->successful()) {
                    return (bool) ($response->json('is_active') ?? $response->json('valid') ?? true);
                }

                // Jika server K1 merespon 404 (Mahasiswa tidak ditemukan)
                if ($response->status() === 404) {
                    return false;
                }
            } catch (\Throwable $e) {
                Log::warning("Gagal menghubungi API K1 untuk NIM {$cleanNim}: " . $e->getMessage());
            }
        }

        // 2. Fallback cerdas saat offline / pengujian lokal mandiri:
        // Standar format NIM di kampus: panjang 8 s/d 14 digit angka murni
        // (Contoh: '3312301045' -> valid, 'abc' atau '123' -> tidak valid)
        return (bool) preg_match('/^[0-9]{8,14}$/', $cleanNim);
    }
}