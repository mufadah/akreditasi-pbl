<?php

namespace App\Http\Middleware;

use App\Models\AnggotaKegiatan;
use App\Models\Buku;
use App\Models\Dosen;
use App\Models\Evidence;
use App\Models\Hki;
use App\Models\Kegiatan;
use App\Models\KerjaSama;
use App\Models\Luaran;
use App\Models\Penelitian;
use App\Models\Pkm;
use App\Models\Publikasi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles  Daftar role yang diizinkan (misal: ADMINISTRATOR, KAPRODI, DOSEN)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = null;

        // 1. Ambil role dari User model yang telah terautentikasi (Request resolver atau Auth guard)
        $user = $request->user() ?: auth()->user();
        if ($user && ! empty($user->role)) {
            $userRole = $user->role;
        }

        // 2. Ambil role dari request attributes (jwt_role atau auth_user)
        if (! $userRole) {
            $userRole = $request->attributes->get('jwt_role');
        }

        // 3. Ambil role langsung dari atribut klaim token JWT K1 (jwt_payload)
        if (! $userRole) {
            $payload = $request->attributes->get('jwt_payload');
            if (is_array($payload)) {
                $rawRole = $payload['role'] ?? $payload['roles'] ?? ($payload['user']['role'] ?? ($payload['user']['roles'] ?? null));
                if (is_array($rawRole)) {
                    $rawRole = $rawRole[0] ?? null;
                }
                $userRole = $rawRole;
            }
        }

        // 4. Ambil role dari data auth_user yang dimerge ke request
        if (! $userRole) {
            $authUser = $request->input('auth_user');
            if (is_object($authUser)) {
                $userRole = $authUser->role ?? null;
            } elseif (is_array($authUser)) {
                $userRole = $authUser['role'] ?? null;
            }
        }

        // 5. Jika role belum terdeteksi namun request memiliki Bearer Token, jalankan JwtAuthenticate
        if (! $userRole && $request->bearerToken()) {
            $jwtAuth = app(JwtAuthenticate::class);
            $authResponse = $jwtAuth->handle($request, fn ($req) => response()->json(['ok' => true]));

            if ($authResponse->getStatusCode() !== 200) {
                return $authResponse;
            }

            $user = $request->user() ?: auth()->user();
            $userRole = $user?->role ?? $request->attributes->get('jwt_role');
        }

        // Jika user sama sekali tidak terautentikasi
        if (! $userRole) {
            return response()->json([
                'success' => false,
                'code' => 401,
                'message' => 'Unauthorized: Token Authorization tidak ditemukan atau tidak valid.',
                'login_url' => config('services.k1.login_url', 'http://akademik.fayra.my.id/api/auth/login'),
            ], 401);
        }

        // Jika rute tidak membatasi role tertentu, izinkan akses
        if (empty($roles)) {
            return $next($request);
        }

        // 6. Pengecekan role secara case-insensitive
        $normalizeRole = function (?string $role): string {
            $r = strtoupper(trim((string) $role));
            if ($r === 'ADMIN') {
                return 'ADMINISTRATOR';
            }
            if ($r === 'REVIEWER') {
                return 'ASESOR';
            }

            return $r;
        };

        $currentUserRole = $normalizeRole((string) $userRole);

        // Decision: Role: admin, kaprodi, dosen, atau asesor?
        $validSystemRoles = ['ADMINISTRATOR', 'KAPRODI', 'DOSEN', 'ASESOR'];
        if (! in_array($currentUserRole, $validSystemRoles, true)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => 'Forbidden: Role tidak diizinkan mengakses sistem ini.',
                'error' => [
                    'current_role' => $userRole,
                    'allowed_system_roles' => ['admin', 'kaprodi', 'dosen', 'asesor'],
                ],
            ], 403);
        }

        // Decision: Role boleh melakukan aksi pada menu ini?
        if (! empty($roles)) {
            $allowedRoles = array_map($normalizeRole, $roles);
            if (! in_array($currentUserRole, $allowedRoles, true)) {
                return response()->json([
                    'success' => false,
                    'code' => 403,
                    'message' => 'Forbidden: Anda tidak memiliki hak akses untuk mengakses endpoint ini.',
                    'error' => [
                        'required_roles' => $roles,
                        'current_role' => $userRole,
                    ],
                ], 403);
            }
        }

        $request->attributes->set('jwt_role', $currentUserRole);
        if ($user?->id) {
            $request->attributes->set('jwt_user_id', $user->id);
        }

        // Decision: Role Dosen?
        // Jika Tidak (Admin / Kaprodi / Asesor) -> Proses request
        if ($currentUserRole !== 'DOSEN') {
            return $next($request);
        }

        // Decision: Data milik dosen tersebut?
        $isOwner = $this->verifyDosenDataOwnership($request, $user);
        if (! $isOwner) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => 'Forbidden: Data bukan milik dosen yang bersangkutan.',
                'error' => [
                    'reason' => 'Data hanya dapat diakses atau diubah oleh dosen pemilik data.',
                ],
            ], 403);
        }

        return $next($request);
    }

    /**
     * Memverifikasi kepemilikan data untuk Role Dosen (Decision 10 Flowchart Fitur 1)
     */
    private function verifyDosenDataOwnership(Request $request, $user): bool
    {
        $currentDosenId = $request->attributes->get('current_dosen_id');
        if (! $currentDosenId && $user) {
            if (! empty($user->email)) {
                $currentDosenId = Dosen::where('email', $user->email)->value('id_dosen');
            }
            if (! $currentDosenId && ! empty($user->id)) {
                $currentDosenId = Dosen::where('id_dosen', $user->id)->value('id_dosen');
            }
        }

        if (! $currentDosenId) {
            return false;
        }

        // 1. Validasi saat menambahkan data baru (POST)
        if ($request->isMethod('POST')) {
            if ($request->filled('id_dosen') && (int) $request->input('id_dosen') !== (int) $currentDosenId) {
                return false;
            }

            // Validasi evidence parent ownership
            if ($request->is('api/evidence*')) {
                if ($request->filled('id_luaran')) {
                    $ownerId = Luaran::where('id_luaran', $request->input('id_luaran'))->value('id_dosen');
                    if ($ownerId && (int) $ownerId !== (int) $currentDosenId) {
                        return false;
                    }
                }
                if ($request->filled('id_kegiatan')) {
                    $isMember = AnggotaKegiatan::where('id_kegiatan', $request->input('id_kegiatan'))->where('id_dosen', $currentDosenId)->exists();
                    if (! $isMember) {
                        return false;
                    }
                }
                $ksId = $request->input('id_kerja_sama') ?? $request->input('id_kerjasama');
                if ($ksId) {
                    $ownerId = KerjaSama::where('id_kerja_sama', $ksId)->value('id_dosen');
                    if ($ownerId && (int) $ownerId !== (int) $currentDosenId) {
                        return false;
                    }
                }
            }
        }

        // 2. Validasi filter query string ?id_dosen=X
        if ($request->query->has('id_dosen') && (int) $request->query('id_dosen') !== (int) $currentDosenId) {
            return false;
        }

        // 3. Validasi akses item spesifik via route parameters (detail, update, delete)
        $route = $request->route();
        if ($route) {
            $params = $route->parameters();

            if (isset($params['luaran'])) {
                $luaran = Luaran::find($params['luaran']);
                if ($luaran && (int) $luaran->id_dosen !== (int) $currentDosenId) {
                    return false;
                }
            }

            if (isset($params['kegiatan'])) {
                $kegiatan = Kegiatan::find($params['kegiatan']);
                if ($kegiatan && ! $kegiatan->anggotaKegiatan()->where('id_dosen', $currentDosenId)->exists()) {
                    return false;
                }
            }

            $ksId = $params['kerja_sama'] ?? $params['kerja-sama'] ?? null;
            if ($ksId) {
                $ks = KerjaSama::find($ksId);
                if ($ks && $ks->id_dosen && (int) $ks->id_dosen !== (int) $currentDosenId) {
                    return false;
                }
            }

            if (isset($params['penelitian'])) {
                $pen = Penelitian::find($params['penelitian']);
                if ($pen && (int) $pen->id_dosen !== (int) $currentDosenId && ! $pen->anggota()->where('id_dosen', $currentDosenId)->exists()) {
                    return false;
                }
            }

            if (isset($params['pkm'])) {
                $pkm = Pkm::find($params['pkm']);
                if ($pkm && (int) $pkm->id_dosen !== (int) $currentDosenId) {
                    return false;
                }
            }

            if (isset($params['publikasi'])) {
                $pub = Publikasi::find($params['publikasi']);
                if ($pub && (int) $pub->id_dosen !== (int) $currentDosenId) {
                    return false;
                }
            }

            if (isset($params['buku'])) {
                $buku = Buku::find($params['buku']);
                if ($buku && (int) $buku->id_dosen !== (int) $currentDosenId) {
                    return false;
                }
            }

            if (isset($params['hki'])) {
                $hki = Hki::find($params['hki']);
                if ($hki && (int) $hki->id_dosen !== (int) $currentDosenId) {
                    return false;
                }
            }

            $evId = $params['evidence'] ?? null;
            if (! $evId && $request->is('api/evidence/*')) {
                $evId = $params['id'] ?? null;
            }
            if ($evId) {
                $ev = Evidence::find($evId);
                if ($ev) {
                    if ($ev->id_luaran && (int) Luaran::where('id_luaran', $ev->id_luaran)->value('id_dosen') !== (int) $currentDosenId) {
                        return false;
                    }
                    if ($ev->id_kegiatan && ! AnggotaKegiatan::where('id_kegiatan', $ev->id_kegiatan)->where('id_dosen', $currentDosenId)->exists()) {
                        return false;
                    }
                    $evKsId = $ev->id_kerja_sama ?? $ev->id_kerjasama;
                    if ($evKsId && (int) KerjaSama::where('id_kerja_sama', $evKsId)->value('id_dosen') !== (int) $currentDosenId) {
                        return false;
                    }
                }
            }

            if (isset($params['id']) && $request->is('api/kerja-sama/*')) {
                $ks = KerjaSama::find($params['id']);
                if ($ks && $ks->id_dosen && (int) $ks->id_dosen !== (int) $currentDosenId) {
                    return false;
                }
            }
        }

        // Auto filter untuk index GET
        if ($request->isMethod('GET') && empty($route?->parameters())) {
            $request->query->set('id_dosen', (string) $currentDosenId);
            $request->merge(['id_dosen' => (int) $currentDosenId]);
        }

        return true;
    }
}
