<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $apiKey = $request->header('X-API-KEY') ?? $request->header('x-api-key');
        $validApiKey = config('services.k2.api_key', env('K2_API_KEY', 'k2-secret-key-akreditasi'));

        // 1. Cek autentikasi via API Key (Interoperabilitas K1/K3 - Fitur 15)
        if ($apiKey && $apiKey === $validApiKey) {
            $userData = [
                'id' => 1,
                'role' => 'ADMINISTRATOR',
            ];
            $request->attributes->set('auth_user', (object) $userData);
            $request->merge(['auth_user' => $userData]);

            return $next($request);
        }

        // 2. Cek autentikasi via Bearer Token (Fitur 15)
        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: API Key atau Bearer Token tidak disertakan pada header.',
            ], 401);
        }

        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Format JWT tidak valid.',
            ], 401);
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $segments[1])), true);

        if (! $payload) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Gagal memproses payload token.',
            ], 401);
        }

        // Opsional: aktifkan jika sudah menggunakan token aktif
        // if (isset($payload['exp']) && time() >= $payload['exp']) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Unauthorized: Token sudah kedaluwarsa.'
        //     ], 401);
        // }

        // Simpan sebagai array di merge dan simpan ke request attributes
        $userData = [
            'id' => $payload['sub'] ?? null,
            'role' => strtoupper($payload['role'] ?? ''),
        ];

        $request->attributes->set('auth_user', (object) $userData);
        $request->merge(['auth_user' => $userData]);

        return $next($request);
    }
}
