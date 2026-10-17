<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
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
     * Ambil daftar notifikasi in-app pengguna (Fitur 16 Flowchart).
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->input('auth_user');
        $userId = is_array($authUser) ? ($authUser['id'] ?? null) : ($authUser->id ?? auth()->id());

        $query = Notifikasi::query();

        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereNull('user_id');
            });
        }

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $notifikasi = $query->latest('id')->paginate($request->integer('per_page', 15));

        return $this->respond(true, 200, 'Daftar notifikasi berhasil diambil.', $notifikasi);
    }

    /**
     * Tandai satu notifikasi telah dibaca.
     */
    public function markAsRead(int $id): JsonResponse
    {
        $notifikasi = Notifikasi::find($id);

        if (! $notifikasi) {
            return $this->respond(false, 404, 'Notifikasi tidak ditemukan.');
        }

        $notifikasi->update(['is_read' => true]);

        return $this->respond(true, 200, 'Notifikasi telah ditandai sebagai dibaca.', $notifikasi);
    }

    /**
     * Kirim notifikasi baru secara manual / API (Fitur 16 Flowchart).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'role' => 'nullable|string',
            'tipe' => 'required|string|max:50',
            'judul' => 'required|string|max:255',
            'pesan' => 'required|string',
            'link' => 'nullable|string|max:255',
        ]);

        if (! empty($validated['role'])) {
            $count = \App\Services\NotifikasiService::kirimKeRole(
                $validated['role'],
                $validated['tipe'],
                $validated['judul'],
                $validated['pesan'],
                $validated['link'] ?? null
            );

            return $this->respond(true, 201, "Notifikasi berhasil dikirimkan ke {$count} pengguna dengan role {$validated['role']}.");
        }

        $notifikasi = \App\Services\NotifikasiService::kirim(
            $validated['user_id'] ?? null,
            $validated['tipe'],
            $validated['judul'],
            $validated['pesan'],
            $validated['link'] ?? null
        );

        return $this->respond(true, 201, 'Notifikasi berhasil dikirim.', $notifikasi);
    }
}
