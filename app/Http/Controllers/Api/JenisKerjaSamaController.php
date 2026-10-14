<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisKerjaSama;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisKerjaSamaController extends Controller
{
    /**
     * Ambil semua data jenis kerja sama.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => JenisKerjaSama::all(),
        ]);
    }

    /**
     * Tambah data jenis kerja sama baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_jenis_kerjasama' => 'required|string|max:255|unique:jenis_kerja_sama,nama_jenis_kerjasama',
        ]);

        $jenis = JenisKerjaSama::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis kerja sama berhasil ditambahkan.',
            'data' => $jenis,
        ], 201);
    }

    /**
     * Ambil detail satu jenis kerja sama.
     */
    public function show(int $id): JsonResponse
    {
        $jenis = JenisKerjaSama::find($id);

        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $jenis]);
    }

    /**
     * Update data jenis kerja sama.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $jenis = JenisKerjaSama::find($id);

        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'nama_jenis_kerjasama' => 'required|string|max:255|unique:jenis_kerja_sama,nama_jenis_kerjasama,'.$id.',id_jenis_kerjasama',
        ]);

        $jenis->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis kerja sama berhasil diperbarui.',
            'data' => $jenis,
        ]);
    }

    /**
     * Hapus data jenis kerja sama.
     */
    public function destroy(int $id): JsonResponse
    {
        $jenis = JenisKerjaSama::find($id);

        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $jenis->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jenis kerja sama berhasil dihapus.',
        ]);
    }
}
