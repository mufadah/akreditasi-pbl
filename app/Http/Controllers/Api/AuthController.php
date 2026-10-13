<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validasi input email dan password
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        // Cek kecocokan email dan password admin
        if ($request->email !== 'admin@pbl.com' || $request->password !== 'password123') {
            return response()->json([
                'success' => false,
                'code'    => 401,
                'message' => 'Email atau password salah.',
                'data'    => null
            ], 401);
        }

        // Siapkan data payload JWT (info user, role, dan permission)
        $payload = [
            'iss'         => 'http://localhost:8000/api/auth/login',
            'iat'         => time(),
            'exp'         => time() + 3600,
            'nbf'         => time(),
            'sub'         => "3",
            'role'        => 'ADMINISTRATOR',
            'permissions' => [
                "dosen.create", "dosen.read", "dosen.update", "dosen.delete",
                "kriteria.create", "kriteria.read", "kriteria.update", "kriteria.delete",
                "penelitian.create", "penelitian.read", "penelitian.update", "penelitian.delete",
                "pengabdian.create", "pengabdian.read", "pengabdian.update", "pengabdian.delete",
                "led.create", "led.read", "led.update", "led.delete",
                "lkps.create", "lkps.read", "lkps.update", "lkps.delete",
                "user.create", "user.read", "user.update", "user.delete",
                "role.create", "role.read", "role.update", "role.delete",
                "permission.create", "permission.read", "permission.update", "permission.delete"
            ]
        ];

        // Generate token JWT pakai secret key
        $key = env('JWT_SECRET', 'secret-key-default');
        $jwt = JWT::encode($payload, $key, 'HS256');

        // Kembalikan respon login berhasil beserta token
        return response()->json([
            'success' => true,
            'code'    => 200,
            'message' => 'Login berhasil',
            'data'    => [
                'user' => [
                    'id'    => 3,
                    'nama'  => 'Administrator User',
                    'email' => 'admin@pbl.com',
                    'role'  => 'ADMINISTRATOR'
                ],
                'authorization' => [
                    'access_token' => $jwt,
                    'token_type'   => 'Bearer',
                    'expires_in'   => 3600
                ]
            ]
        ]);
    }
}