<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public static function log(
        string $action,
        string $entityType,
        mixed $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $user = request()->input('auth_user') ?? (object) ['id' => null, 'role' => null];

            DB::table('audit_logs')->insert([
                'user_id'     => $user->id ?? null,
                'role'        => $user->role ?? null,
                'action'      => strtoupper($action),
                'entity_type' => $entityType,
                'entity_id'   => (string) $entityId,
                'old_values'  => $oldValues ? json_encode($oldValues) : null,
                'new_values'  => $newValues ? json_encode($newValues) : null,
                'ip_address'  => Request::ip(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Diamkan agar tidak memutus alur utama jika audit log gagal insert
        }
    }
}
