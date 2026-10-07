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

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Token Bearer tidak disertakan.',
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
