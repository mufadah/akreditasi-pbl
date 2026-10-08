<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisPenelitian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisPenelitianController extends Controller
{
    // Ambil semua data jenis penelitian
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => JenisPenelitian::all(),
        ]);
    }

    // Tambah data jenis penelitian baru
    public function store(Request $request): JsonResponse
    {
        // Validasi nama jenis penelitian wajib dan unik
        $validated = $request->validate([
            'nama_jenis_penelitian' => 'required|string|max:255|unique:jenis_penelitian,nama_jenis_penelitian',
        ]);

        // Simpan ke database
        $jenis = JenisPenelitian::create($validated);

        // Kembalikan respon sukses
        return response()->json([
            'success' => true,
            'message' => 'Jenis penelitian berhasil ditambahkan.',
            'data' => $jenis,
        ], 201);
    }

    // Ambil detail satu jenis penelitian
    public function show(int $id): JsonResponse
    {
        $jenis = JenisPenelitian::find($id);

        // Cek data ada atau tidak
        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $jenis]);
    }

    // Update data jenis penelitian
    public function update(Request $request, int $id): JsonResponse
    {
        $jenis = JenisPenelitian::find($id);

        // Cek data ada atau tidak
        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Validasi nama jenis unik kecuali untuk id ini
        $validated = $request->validate([
            'nama_jenis_penelitian' => 'required|string|max:255|unique:jenis_penelitian,nama_jenis_penelitian,'.$id.',id_jenis_penelitian',
        ]);

        // Update data di database
        $jenis->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis penelitian berhasil diperbarui.',
            'data' => $jenis,
        ]);
    }

    // Hapus data jenis penelitian
    public function destroy(int $id): JsonResponse
    {
        $jenis = JenisPenelitian::find($id);

        // Cek data ada atau tidak
        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Hapus dari database
        $jenis->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jenis penelitian berhasil dihapus.',
        ]);
    }
}
