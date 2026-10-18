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
     * Informasi Resource Server ketika ada client yang mencoba memanggil /api/login atau /api/register secara lokal ke K2.
     */
    public function resourceServerInfo(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 401,
            'message' => 'Layanan ini adalah Resource Server (Kelompok 2). Autentikasi dan penerbitan token dilakukan terpusat melalui portal SSO Kelompok 1.',
            'login_url' => config('services.k1.login_url', 'https://akademik.fayra.my.id/auth/login'),
        ], 401);
    }
}
