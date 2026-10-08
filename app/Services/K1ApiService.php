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

        // Jika URL K1 belum dikonfigurasi di .env, gunakan fallback pola NIM
        if (empty($this->baseUrl)) {
            // Standar NIM valid: panjang 8-15 digit numerik
            return (bool) preg_match('/^[0-9]{8,15}$/', $cleanNim);
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-API-KEY' => $this->apiKey])
                ->get("{$this->baseUrl}/mahasiswa/{$cleanNim}/check");

            if ($response->successful()) {
                return (bool) ($response->json('is_active') ?? true);
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal menghubungi API K1 untuk validasi NIM: {$cleanNim}. Error: {$e->getMessage()}");
        }

        // Fallback jika API K1 tidak merespon saat testing
        return (bool) preg_match('/^[0-9]{8,15}$/', $cleanNim);
    }

    /**
     * Ambil statistik mahasiswa untuk perhitungan rasio dosen:mahasiswa di Dashboard.
     */
    public function getStatistikMahasiswa(array $params = []): ?array
    {
        if (empty($this->baseUrl)) {
            // Mock data saat offline
            return [
                'total_mahasiswa' => 450,
                'prodi' => 'Teknik Informatika',
                'tahun_akademik' => '2025/2026',
            ];
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-API-KEY' => $this->apiKey])
                ->get("{$this->baseUrl}/statistik/mahasiswa", $params);

            if ($response->successful()) {
                return $response->json('data');
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal mengambil statistik mahasiswa dari API K1: {$e->getMessage()}");
        }

        return null;
    }
}
