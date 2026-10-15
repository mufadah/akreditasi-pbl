<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validasi input email dan password
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $k1Presets = [
            'kaprodi@example.com' => ['name' => 'Kaprodi User', 'role' => 'KAPRODI', 'id' => 1],
            'dosen@example.com' => ['name' => 'Dosen User', 'role' => 'DOSEN', 'id' => 2],
            'administrator@example.com' => ['name' => 'Administrator User', 'role' => 'ADMINISTRATOR', 'id' => 3],
            'operator@example.com' => ['name' => 'Operator User', 'role' => 'OPERATOR', 'id' => 4],
            'reviewer@example.com' => ['name' => 'Reviewer User', 'role' => 'REVIEWER', 'id' => 5],
            'pimpinan@example.com' => ['name' => 'Pimpinan User', 'role' => 'PIMPINAN', 'id' => 6],
        ];

        if (! isset($k1Presets[$request->email]) || $request->password !== 'password') {
            return response()->json([
                'success' => false,
                'code' => 401,
                'message' => 'Email atau password salah.',
                'data' => null,
            ], 401);
        }

        $preset = $k1Presets[$request->email];
        $userId = $preset['id'];
        $userName = $preset['name'];
        $userRole = $preset['role'];

        // Siapkan data payload JWT
        $payload = [
            'iss' => url('/api/login'),
            'iat' => time(),
            'exp' => time() + 86400,
            'nbf' => time(),
            'sub' => (string) $userId,
            'id' => (int) $userId,
            'email' => $request->email,
            'nama' => $userName,
            'role' => $userRole,
            'permissions' => [
                'dosen.create', 'dosen.read', 'dosen.update', 'dosen.delete',
                'kegiatan.create', 'kegiatan.read', 'kegiatan.update', 'kegiatan.delete',
                'luaran.create', 'luaran.read', 'luaran.update', 'luaran.delete',
                'kerjasama.create', 'kerjasama.read', 'kerjasama.update', 'kerjasama.delete',
                'evidence.create', 'evidence.read', 'evidence.update', 'evidence.delete',
            ],
        ];

        $key = config('jwt.secret', env('JWT_SECRET', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes'));
        $jwt = JWT::encode($payload, $key, config('jwt.algo', 'HS256'));

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $userId,
                    'nama' => $userName,
                    'email' => $request->email,
                    'role' => $userRole,
                ],
                'authorization' => [
                    'access_token' => $jwt,
                    'token_type' => 'Bearer',
                    'expires_in' => 86400,
                ],
            ],
        ]);
    }

    public function register(Request $request)
    {
        return response()->json([
            'success' => false,
            'code' => 403,
            'message' => 'Registrasi mandiri dinonaktifkan. Pengelolaan akun pengguna dikelola secara terpusat melalui portal SSO K1.',
            'login_url' => config('services.k1.login_url', 'https://akademik.fayra.my.id/login'),
        ], 403);
    }

    public function me(Request $request)
    {
        $user = $request->user() ?: auth()->user();
        $authUser = $request->attributes->get('auth_user') ?? $request->input('auth_user');
        $jwtPayload = $request->attributes->get('jwt_payload');

        $profileData = [
            'id' => $user?->id ?? (is_array($authUser) ? ($authUser['id'] ?? null) : null),
            'nama' => $user?->nama ?? $user?->name ?? (is_array($authUser) ? ($authUser['nama'] ?? null) : null),
            'email' => $user?->email ?? (is_array($authUser) ? ($authUser['email'] ?? null) : null),
            'role' => $user?->role ?? (is_array($authUser) ? ($authUser['role'] ?? null) : null),
        ];

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'Profil pengguna berhasil diambil.',
            'data' => [
                'user' => $profileData,
                'role' => $profileData['role'],
                'auth_info' => $authUser,
                'token_payload' => $jwtPayload,
            ],
        ]);
    }

    public function logout()
    {
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'Logout berhasil. Token telah dinonaktifkan di sisi klien.',
            'data' => null,
        ]);
    }
}
