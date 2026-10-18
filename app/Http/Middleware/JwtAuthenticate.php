<?php

namespace App\Http\Middleware;

use App\Models\Dosen;
use App\Models\User;
use App\Services\K1ApiService;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
            $user = User::firstOrNew(['email' => 'system@akreditasi.internal']);
            $user->name = 'System Interoperability';
            $user->role = 'ADMINISTRATOR';
            $user->permissions = ['*'];

            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
            $request->attributes->set('auth_user', (object) [
                'id' => $user->id ?? 1,
                'role' => 'ADMINISTRATOR',
                'name' => 'System Interoperability',
            ]);
            $request->attributes->set('jwt_role', 'ADMINISTRATOR');
            $request->attributes->set('jwt_permissions', ['*']);
            $request->merge(['auth_user' => [
                'id' => $user->id ?? 1,
                'role' => 'ADMINISTRATOR',
            ]]);

            return $next($request);
        }

        $loginUrl = config('services.k1.login_url', 'https://akademik.fayra.my.id/auth/login');

        // 2. Validasi keberadaan Bearer Token
        if (! $token) {
            return response()->json([
                'success' => false,
                'code' => 401,
                'message' => 'Unauthorized: Token Bearer tidak disertakan. Silakan login melalui portal SSO K1.',
                'login_url' => $loginUrl,
            ], 401);
        }

        $payload = null;
        $secret = config('jwt.secret', env('JWT_SECRET', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes'));
        $algo = config('jwt.algo', 'HS256');

        // 2. Coba verifikasi dan decode JWT menggunakan shared secret (K1 <-> K2)
        if (! empty($secret)) {
            try {
                $decoded = JWT::decode($token, new Key($secret, $algo));
                $payload = json_decode(json_encode($decoded), true);
            } catch (ExpiredException $e) {
                return response()->json([
                    'success' => false,
                    'code' => 401,
                    'message' => 'Unauthorized: Token sudah kedaluwarsa. Silakan perbarui sesi login SSO K1.',
                    'login_url' => $loginUrl,
                ], 401);
            } catch (SignatureInvalidException $e) {
                Log::debug('Signature JWT lokal K2 tidak valid: '.$e->getMessage());
            } catch (\Throwable $e) {
                Log::debug('Verifikasi token lokal K2 dengan JWT_SECRET gagal, mencoba fallback K1: '.$e->getMessage());
            }
        }

        // 3. Fallback: Verifikasi token langsung ke server K1 (mendukung token opaque / Sanctum)
        if (! $payload) {
            $k1Service = app(K1ApiService::class);
            $k1User = $k1Service->verifyToken($token);
            if (is_array($k1User) && ! empty($k1User)) {
                $payload = $k1User;
            }
        }

        // Decision: Tanda tangan JWT valid?
        // Jika seluruh metode verifikasi signature gagal, tolak dengan 401
        if (! $payload || ! is_array($payload)) {
            return response()->json([
                'success' => false,
                'code' => 401,
                'message' => 'Unauthorized: Tanda tangan JWT tidak valid atau token tidak sah.',
                'login_url' => $loginUrl,
            ], 401);
        }

        // Decision: Token belum kedaluwarsa?
        if (isset($payload['exp']) && time() >= $payload['exp']) {
            return response()->json([
                'success' => false,
                'code' => 401,
                'message' => 'Unauthorized: Token sudah kedaluwarsa.',
                'login_url' => $loginUrl,
            ], 401);
        }

        // 4. Ekstraksi klaim payload fleksibel dari K1 maupun K2
        if ($payload && empty($payload['email']) && empty($payload['user']['email'])) {
            $k1Service = app(K1ApiService::class);
            $k1Profile = $k1Service->verifyToken($token);
            if (is_array($k1Profile) && ! empty($k1Profile)) {
                $payload = array_merge($payload, $k1Profile);
            }
        }

        $userId = $payload['sub'] ?? $payload['id'] ?? $payload['user_id'] ?? ($payload['user']['id'] ?? null);
        if (is_array($userId)) {
            $userId = $userId['id'] ?? null;
        }

        $email = $payload['email'] ?? $payload['user']['email'] ?? null;
        if (! $email && $userId) {
            $email = "user{$userId}@k1.polines.ac.id";
        }

        $name = $payload['name'] ?? $payload['nama'] ?? $payload['user']['name'] ?? $payload['user']['nama'] ?? null;
        if (! $name) {
            $name = $email ? explode('@', (string) $email)[0] : "K1 User {$userId}";
        }

        // Process: Baca role dari isi token
        $rawRole = $payload['role'] ?? $payload['roles'] ?? ($payload['user']['role'] ?? ($payload['user']['roles'] ?? 'DOSEN'));
        if (is_array($rawRole)) {
            $rawRole = $rawRole[0] ?? 'DOSEN';
        }
        $normalizedRole = strtoupper(trim((string) $rawRole));
        if ($normalizedRole === 'ADMIN') {
            $normalizedRole = 'ADMINISTRATOR';
        }
        if ($normalizedRole === 'REVIEWER') {
            $normalizedRole = 'ASESOR';
        }

        // Decision: Role: admin, kaprodi, dosen, atau asesor?
        $validSystemRoles = ['ADMINISTRATOR', 'KAPRODI', 'DOSEN', 'ASESOR'];
        if (! in_array($normalizedRole, $validSystemRoles, true)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => 'Forbidden: Role tidak diizinkan mengakses sistem ini.',
                'error' => [
                    'current_role' => $rawRole,
                    'allowed_system_roles' => ['admin', 'kaprodi', 'dosen', 'asesor'],
                ],
            ], 403);
        }

        // 5. Ekstraksi Hak Akses (Permissions) dari JWT token K1
        $rawPermissions = $payload['permissions'] ?? ($payload['user']['permissions'] ?? ($payload['scope'] ?? null));
        if (is_string($rawPermissions)) {
            $rawPermissions = preg_split('/[\s,]+/', trim($rawPermissions));
        }
        $permissions = is_array($rawPermissions) ? array_values(array_filter(array_map('trim', $rawPermissions))) : [];

        // Default permission jika token K1 hanya membawa claim role
        if (empty($permissions)) {
            $permissions = match ($normalizedRole) {
                'ADMINISTRATOR' => ['*'],
                'KAPRODI' => ['dosen.read', 'kriteria.read', 'penelitian.read', 'pengabdian.read', 'led.read', 'lkps.read'],
                'DOSEN' => ['dosen.read', 'penelitian.create', 'penelitian.read', 'penelitian.update', 'pengabdian.create', 'pengabdian.read', 'pengabdian.update'],
                'ASESOR' => ['dosen.read', 'kriteria.read', 'led.read', 'lkps.read'],
                default => [],
            };
        }

        // 6. Inisialisasi model User secara stateless di memory (tanpa query / simpan ke database)
        $user = new User([
            'name' => $name,
            'email' => $email ?: "user_{$userId}@k1.polines.ac.id",
            'role' => $normalizedRole,
        ]);
        if ($userId && is_numeric($userId)) {
            $user->id = (int) $userId;
        }
        $user->permissions = $permissions;
        $user->exists = true;

        if (function_exists('session')) {
            session(['permissions' => $permissions]);
        }

        // 7. Simpan autentikasi aktif ke Auth Guard dan Request Resolver
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        // Resolusi ID Dosen di database K2 (jika ada) untuk pengecekan kepemilikan data (Decision 10)
        $currentDosenId = null;
        if (! empty($user->email)) {
            $currentDosenId = Dosen::where('email', $user->email)->value('id_dosen');
        }
        if (! $currentDosenId && ! empty($user->id)) {
            $currentDosenId = Dosen::where('id_dosen', $user->id)->value('id_dosen');
        }

        // Siapkan struktur array dan object sesuai standar format K1 (id, nama, email, role, permissions)
        $userData = [
            'id' => $user->id ?? $userId,
            'nama' => $user->name ?? $name,
            'email' => $user->email ?? $email,
            'role' => $user->role ?? $normalizedRole,
            'permissions' => $permissions,
            'id_dosen' => $currentDosenId,
        ];

        $request->attributes->set('auth_user', $user ?: (object) $userData);
        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('jwt_user_id', $user->id ?? $userId);
        $request->attributes->set('jwt_role', $user->role ?? $normalizedRole);
        $request->attributes->set('jwt_permissions', $permissions);
        $request->attributes->set('current_dosen_id', $currentDosenId);

        // Merge untuk backward-compatibility request()->input('auth_user')
        $request->merge(['auth_user' => $userData]);

        return $next($request);
    }
}
