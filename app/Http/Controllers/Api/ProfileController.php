<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Introspeksi profil dan hak akses (permissions) pengguna aktif.
     * Murni membaca identitas yang telah divalidasi & diekstrak oleh Middleware dari JWT token SSO K1.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user() ?: auth()->user();
        $authUser = $request->attributes->get('auth_user') ?? $request->input('auth_user');
        $jwtPayload = $request->attributes->get('jwt_payload');
        $permissions = $request->attributes->get('jwt_permissions') ?? ($user->permissions ?? []);

        $profileData = [
            'id' => $user?->id ?? (is_array($authUser) ? ($authUser['id'] ?? null) : null),
            'nama' => $user?->name ?? (is_array($authUser) ? ($authUser['nama'] ?? $authUser['name'] ?? null) : null),
            'email' => $user?->email ?? (is_array($authUser) ? ($authUser['email'] ?? null) : null),
            'role' => $user?->role ?? (is_array($authUser) ? ($authUser['role'] ?? null) : null),
            'permissions' => $permissions,
        ];

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'Profil dan hak akses pengguna berhasil diekstrak dari token JWT SSO K1.',
            'data' => [
                'user' => $profileData,
                'role' => $profileData['role'],
                'permissions' => $permissions,
                'auth_info' => $authUser,
                'token_payload' => $jwtPayload,
            ],
        ]);
    }

    /**
     * Informasi Resource Server atau Proxy Login ke server SSO Kelompok 1 (http://akademik.fayra.my.id/api/auth/login).
     */
    public function resourceServerInfo(Request $request): JsonResponse
    {
        $loginUrl = config('services.k1.login_url', 'http://akademik.fayra.my.id/api/auth/login');

        // Jika request membawa email dan password, teruskan autentikasi ke API SSO K1
        if ($request->filled('email') && $request->filled('password')) {
            $k1Service = app(\App\Services\K1ApiService::class);
            $k1Response = $k1Service->login($request->input('email'), $request->input('password'));

            if ($k1Response && ! empty($k1Response['success'])) {
                $status = $k1Response['code'] ?? 200;

                return response()->json($k1Response, $status);
            }
        }

        return response()->json([
            'success' => false,
            'code' => 401,
            'message' => 'Layanan ini adalah Resource Server (Kelompok 2). Autentikasi dan penerbitan token dilakukan terpusat melalui portal SSO Kelompok 1.',
            'login_url' => $loginUrl,
        ], 401);
    }
}
