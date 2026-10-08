<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;

/**
 * Controller autentikasi kredensial pengguna dan penerbitan JSON Web Token (JWT).
 * Mengintegrasikan payload klaim peran (Role-Based Access Control) dan izin modular.
 */
class AuthController extends Controller
{
    /**
     * Memvalidasi kredensial pengguna dan menerbitkan Bearer Token JWT berbasis klaim peran.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // 1. Validasi masukan dasar: format email harus valid dan kata sandi wajib terisi
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        // 2. Verifikasi kredensial akun administrator (pendekatan Dummy Auth untuk simulasi)
        if ($request->email !== 'admin@pbl.com' || $request->password !== 'password123') {
            return response()->json([
                'success' => false,
                'code'    => 401,
                'message' => 'Email atau password salah.',
                'data'    => null
            ], 401);
        }

        // 3. Susun payload standar RFC 7519: penerbit (iss), waktu rilis (iat), kedaluwarsa (exp 1 jam), subjek, peran, dan hak akses
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

        // 4. Ambil kunci rahasia dari environment (.env) dan enkripsi payload menggunakan algoritma HMAC-SHA256 (HS256)
        $key = env('JWT_SECRET', 'secret-key-default');
        $jwt = JWT::encode($payload, $key, 'HS256');

        // 5. Kembalikan respon 200 OK dengan format envelope terstandarisasi beserta token otorisasi Bearer
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