<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisPenelitian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisPenelitianController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => JenisPenelitian::all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_jenis_penelitian' => 'required|string|max:255|unique:jenis_penelitian,nama_jenis_penelitian',
        ]);

        $jenis = JenisPenelitian::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis penelitian berhasil ditambahkan.',
            'data' => $jenis,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $jenis = JenisPenelitian::find($id);
        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $jenis]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $jenis = JenisPenelitian::find($id);
        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'nama_jenis_penelitian' => 'required|string|max:255|unique:jenis_penelitian,nama_jenis_penelitian,'.$id.',id_jenis_penelitian',
        ]);

        $jenis->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis penelitian berhasil diperbarui.',
            'data' => $jenis,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $jenis = JenisPenelitian::find($id);
        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $jenis->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jenis penelitian berhasil dihapus.',
        ]);
    }
}
