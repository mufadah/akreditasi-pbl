<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            $userRole = $payload->get('role');

            if (! empty($roles) && ! in_array($userRole, $roles, true)) {
                return response()->json([
                    'success' => false,
                    'code'    => 403,
                    'message' => 'Anda tidak memiliki hak akses (Role tidak sesuai).',
                    'data'    => null,
                ], 403);
            }

            $request->attributes->set('jwt_user_id', $payload->get('sub'));
            $request->attributes->set('jwt_role', $userRole);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code'    => 401,
                'message' => 'Token Authorization tidak valid, kedaluwarsa, atau tidak ditemukan.',
                'data'    => null,
            ], 401);
        }

        return $next($request);
    }
}