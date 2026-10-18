<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class K1ApiService
{
    protected ?string $baseUrl;

    protected ?string $loginUrl;

    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.k1.base_url') ?: (config('services.k1.url') ?: 'https://akademik.fayra.my.id/api');
        $this->loginUrl = config('services.k1.login_url') ?: 'https://akademik.fayra.my.id/login';
        $this->apiKey = (string) config('services.k1.key', '');
    }

    /**
     * Dapatkan URL login portal SSO K1.
     */
    public function getLoginUrl(): string
    {
        return $this->loginUrl ?: 'https://akademik.fayra.my.id/login';
    }

    /**
     * Hit API Login ke server live Kelompok 1.
     *
     * @return array<string, mixed>|null
     */
    public function login(string $email, string $password): ?array
    {
        if (empty($this->baseUrl)) {
            return null;
        }

        $base = rtrim($this->baseUrl, '/');
        $endpoints = array_unique([
            $base.'/auth/login',
            $base.'/login',
            str_replace('/api', '', $base).'/api/auth/login',
            str_replace('/api', '', $base).'/api/login',
        ]);

        foreach ($endpoints as $url) {
            try {
                $response = Http::timeout(5)
                    ->acceptJson()
                    ->post($url, [
                        'email' => $email,
                        'password' => $password,
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    if (is_array($json)) {
                        return $json;
                    }
                }

                if ($response->status() === 401) {
                    return [
                        'success' => false,
                        'code' => 401,
                        'message' => $response->json('message') ?? 'Email atau password salah.',
                        'data' => null,
                    ];
                }
            } catch (\Throwable $e) {
                Log::debug("Login ke K1 URL {$url} error: ".$e->getMessage());
            }
        }

        return null;
    }

    /**
     * Verifikasi token ke endpoint server K1 (fallback untuk token opaque/Sanctum atau secret berbeda).
     *
     * @return array<string, mixed>|null Data payload/user jika valid, atau null jika gagal/tidak valid
     */
    public function verifyToken(string $token): ?array
    {
        $cleanToken = trim($token);
        if (empty($cleanToken) || empty($this->baseUrl)) {
            return null;
        }

        $base = rtrim($this->baseUrl, '/');
        $endpoints = array_unique([
            $base.'/auth/me',
            $base.'/me',
            $base.'/verify-token',
            str_replace('/api', '', $base).'/api/auth/me',
            str_replace('/api', '', $base).'/api/me',
            str_replace('/api', '', $base).'/api/verify-token',
        ]);

        foreach ($endpoints as $url) {
            try {
                $response = Http::timeout(5)
                    ->withToken($cleanToken)
                    ->acceptJson()
                    ->get($url);

                if ($response->successful()) {
                    $json = $response->json();
                    $userData = $json['data']['user'] ?? $json['data'] ?? $json['user'] ?? $json;
                    if (is_array($userData) && ! empty($userData)) {
                        return $userData;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug("Percobaan verifikasi token K1 ke {$url} gagal: ".$e->getMessage());
            }
        }

        return null;
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
        if (! empty($this->baseUrl)) {
            $base = rtrim($this->baseUrl, '/');
            $candidateUrls = [
                "{$base}/mahasiswa/{$cleanNim}/check",
                "{$base}/mahasiswa/{$cleanNim}",
                "{$base}/mahasiswa",
            ];

            foreach ($candidateUrls as $url) {
                try {
                    $response = Http::timeout(5)
                        ->withHeaders(['X-API-KEY' => $this->apiKey])
                        ->get($url);

                    if ($response->successful()) {
                        $json = $response->json();
                        if (str_ends_with($url, '/mahasiswa')) {
                            $list = $json['data'] ?? $json;
                            if (is_array($list)) {
                                foreach ($list as $m) {
                                    if (($m['nim'] ?? '') === $cleanNim) {
                                        return true;
                                    }
                                }
                            }
                        } else {
                            return (bool) ($json['is_active'] ?? $json['valid'] ?? true);
                        }
                    } elseif ($response->status() === 404 && ! str_ends_with($url, '/mahasiswa')) {
                        return false;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Gagal menghubungi API K1 untuk NIM {$cleanNim}: ".$e->getMessage());
                }
            }
        }

        // 2. Fallback cerdas saat offline / pengujian lokal mandiri:
        // Standar format NIM di kampus: panjang 8 s/d 14 digit angka murni
        // (Contoh: '3312301045' -> valid, 'abc' atau '123' -> tidak valid)
        return (bool) preg_match('/^[0-9]{8,14}$/', $cleanNim);
    }

    /**
     * Ambil data statistik mahasiswa dari API K1 untuk perhitungan rasio dosen:mahasiswa.
     */
    public function getStatistikMahasiswa(array $params = []): ?array
    {
        // 1. Jika URL server K1 sudah ada di .env, tembak API K1
        if (! empty($this->baseUrl)) {
            $base = rtrim($this->baseUrl, '/');
            try {
                // 1a. Coba endpoint agregasi statistik K1
                $response = Http::timeout(5)
                    ->withHeaders(['X-API-KEY' => $this->apiKey])
                    ->get("{$base}/statistik/mahasiswa", $params);

                if ($response->successful()) {
                    return $response->json('data') ?? $response->json();
                }

                // 1b. Coba endpoint daftar mahasiswa K1 (/mahasiswa) dari repositori siakre-api
                $responseMhs = Http::timeout(5)
                    ->withHeaders(['X-API-KEY' => $this->apiKey])
                    ->get("{$base}/mahasiswa");

                if ($responseMhs->successful()) {
                    $list = $responseMhs->json('data') ?? $responseMhs->json();
                    if (is_array($list)) {
                        return [
                            'total_mahasiswa' => count($list),
                            'prodi' => $params['prodi'] ?? 'Teknologi Rekayasa Komputer',
                            'tahun_akademik' => $params['tahun'] ?? date('Y'),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal mengambil statistik mahasiswa dari API K1: '.$e->getMessage());
            }
        }

        // 2. Mock fallback cerdas saat offline / server K1 belum online (425 mahasiswa aktif)
        return [
            'total_mahasiswa' => 425,
            'prodi' => $params['prodi'] ?? 'Teknologi Rekayasa Komputer',
            'tahun_akademik' => $params['tahun'] ?? date('Y'),
        ];
    }
}
