<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validasi masukan dasar: format email harus valid dan kata sandi wajib terisi
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        // Verifikasi kredensial akun administrator (pendekatan Dummy Auth untuk simulasi)
        if ($request->email !== 'admin@pbl.com' || $request->password !== 'password123') {
            return response()->json([
                'success' => false,
                'code'    => 401,
                'message' => 'Email atau password salah.',
                'data'    => null
            ], 401);
        }

        // Susun payload standar RFC 7519: penerbit, waktu rilis, kedaluwarsa, subjek, peran, dan hak akses
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

        // Ambil kunci rahasia dari environment (.env) dan enkripsi payload menggunakan algoritma HMAC-SHA256 (HS256)
        $key = env('JWT_SECRET', 'secret-key-default');
        $jwt = JWT::encode($payload, $key, 'HS256');

        // Kembalikan respon 200 OK dengan format envelope terstandarisasi beserta token otorisasi Bearer
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