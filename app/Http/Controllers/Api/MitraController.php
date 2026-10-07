<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mitra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MitraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Mitra::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_instansi', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%");
            });
        }

        $mitra = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $mitra,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_instansi' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telepon' => 'nullable|string|max:50',
        ]);

        $mitra = Mitra::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil ditambahkan.',
            'data' => $mitra,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $mitra = Mitra::with(['pkm', 'kerjaSama'])->find($id);

        if (! $mitra) {
            return response()->json([
                'success' => false,
                'message' => 'Data mitra tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $mitra,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $mitra = Mitra::find($id);

        if (! $mitra) {
            return response()->json([
                'success' => false,
                'message' => 'Data mitra tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'nama_instansi' => 'sometimes|required|string|max:255',
            'alamat' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telepon' => 'nullable|string|max:50',
        ]);

        $mitra->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil diperbarui.',
            'data' => $mitra,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $mitra = Mitra::find($id);

        if (! $mitra) {
            return response()->json([
                'success' => false,
                'message' => 'Data mitra tidak ditemukan.',
            ], 404);
        }

        if ($mitra->pkm()->exists() || $mitra->kerjaSama()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Mitra tidak dapat dihapus karena masih terkait dengan data PKM atau kerja sama.',
            ], 422);
        }

        $mitra->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil dihapus.',
        ]);
    }
}
