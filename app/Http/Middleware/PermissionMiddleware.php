<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     * Memeriksa apakah user yang sedang login memiliki permission yang sesuai dari token JWT SSO K1.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$permissions  Daftar permission yang diizinkan (misal: dosen.read, dosen.create)
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user() ?: auth()->user();
        $userRole = strtoupper(trim((string) ($user->role ?? $request->attributes->get('jwt_role') ?? '')));

        // 1. Role ADMINISTRATOR memiliki akses penuh (superadmin bypass)
        if ($userRole === 'ADMINISTRATOR' || $userRole === 'ADMIN') {
            return $next($request);
        }

        // 2. Ambil permissions dari request attributes, user object, atau session
        $userPermissions = $request->attributes->get('jwt_permissions')
            ?? ($user->permissions ?? null);

        if (empty($userPermissions) && function_exists('session') && session()->has('permissions')) {
            $userPermissions = session('permissions');
        }

        if (is_string($userPermissions)) {
            $userPermissions = preg_split('/[\s,]+/', trim($userPermissions));
        }
        $userPermissions = is_array($userPermissions) ? $userPermissions : [];

        // Simpan ke session jika session aktif
        if (function_exists('session')) {
            session(['permissions' => $userPermissions]);
        }

        // Jika rute tidak menspesifikasikan permission khusus
        if (empty($permissions)) {
            return $next($request);
        }

        // Normalisasi daftar permission yang diminta (mendukung pemisah koma: "dosen.read,dosen.view")
        $requiredList = [];
        foreach ($permissions as $p) {
            foreach (explode(',', $p) as $item) {
                $trimmed = trim($item);
                if (! empty($trimmed)) {
                    $requiredList[] = $trimmed;
                }
            }
        }

        // 3. Pengecekan izin: wildcard '*' atau match exact/prefix (contoh: 'dosen.*')
        if (in_array('*', $userPermissions, true)) {
            return $next($request);
        }

        $hasMatch = false;
        foreach ($requiredList as $required) {
            if (in_array($required, $userPermissions, true)) {
                $hasMatch = true;
                break;
            }

            // Cek prefix wildcard jika user punya 'dosen.*' untuk kebutuhan 'dosen.create'
            $prefix = explode('.', $required)[0] ?? '';
            if (! empty($prefix) && in_array("{$prefix}.*", $userPermissions, true)) {
                $hasMatch = true;
                break;
            }
        }

        // 4. Jika tidak memiliki permission, kembalikan HTTP 403 Forbidden
        if (! $hasMatch) {
            $neededStr = implode(' atau ', $requiredList);

            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => "Forbidden: Anda tidak memiliki permission '{$neededStr}' untuk melakukan aksi ini.",
                'error' => [
                    'required_permissions' => $requiredList,
                    'user_permissions' => $userPermissions,
                ],
            ], 403);
        }

        return $next($request);
    }
}
