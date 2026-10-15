<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Ambil rekam jejak audit log seluruh aksi di sistem (Fitur 16 Flowchart).
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        if ($entity = $request->query('entity_type')) {
            $query->where('entity_type', $entity);
        }

        if ($action = $request->query('action')) {
            $query->where('action', strtoupper($action));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('entity_type', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest('id')->paginate($request->integer('per_page', 20));

        return $this->respond(true, 200, 'Daftar audit log berhasil diambil.', $logs);
    }

    /**
     * Detail satu catatan audit log beserta data lama dan data baru.
     */
    public function show(int $id): JsonResponse
    {
        $log = AuditLog::find($id);

        if (! $log) {
            return $this->respond(false, 404, 'Data audit log tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail audit log berhasil diambil.', $log);
    }
}
