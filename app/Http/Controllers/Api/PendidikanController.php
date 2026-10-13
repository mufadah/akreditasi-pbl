<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pendidikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendidikanController extends Controller
{
    // Ambil semua data jenjang pendidikan
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Pendidikan::all(),
        ]);
    }

    // Tambah data jenjang pendidikan baru
    public function store(Request $request): JsonResponse
    {
        // Validasi nama jenjang wajib dan unik
        $validated = $request->validate([
            'jenjang' => 'required|string|max:50|unique:pendidikan,jenjang',
        ]);

        // Simpan ke database
        $pendidikan = Pendidikan::create($validated);

        // Kembalikan respon sukses
        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil ditambahkan.',
            'data' => $pendidikan,
        ], 201);
    }

    // Ambil detail satu jenjang pendidikan
    public function show(int $id): JsonResponse
    {
        $pendidikan = Pendidikan::find($id);

        // Cek data ada atau tidak
        if (! $pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $pendidikan]);
    }

    // Update data jenjang pendidikan
    public function update(Request $request, int $id): JsonResponse
    {
        $pendidikan = Pendidikan::find($id);

        // Cek data ada atau tidak
        if (! $pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Validasi jenjang unik kecuali untuk id ini
        $validated = $request->validate([
            'jenjang' => 'required|string|max:50|unique:pendidikan,jenjang,'.$id.',id_pendidikan',
        ]);

        // Update data di database
        $pendidikan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil diperbarui.',
            'data' => $pendidikan,
        ]);
    }

    // Hapus data jenjang pendidikan
    public function destroy(int $id): JsonResponse
    {
        $pendidikan = Pendidikan::find($id);

        // Cek data ada atau tidak
        if (! $pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Hapus dari database
        $pendidikan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil dihapus.',
        ]);
    }
}
