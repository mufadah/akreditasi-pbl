<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisKegiatan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisKegiatanController extends Controller
{
    /**
     * Ambil semua data jenis kegiatan.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => JenisKegiatan::all(),
        ]);
    }

    /**
     * Tambah data jenis kegiatan baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_jenis_kegiatan' => 'required|string|max:255|unique:jenis_kegiatan,nama_jenis_kegiatan',
            'deskripsi' => 'nullable|string',
        ]);

        $jenis = JenisKegiatan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis kegiatan berhasil ditambahkan.',
            'data' => $jenis,
        ], 201);
    }

    /**
     * Ambil detail satu jenis kegiatan.
     */
    public function show(int $id): JsonResponse
    {
        $jenis = JenisKegiatan::find($id);

        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $jenis]);
    }

    /**
     * Update data jenis kegiatan.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $jenis = JenisKegiatan::find($id);

        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'nama_jenis_kegiatan' => 'required|string|max:255|unique:jenis_kegiatan,nama_jenis_kegiatan,'.$id.',id_jenis_kegiatan',
            'deskripsi' => 'nullable|string',
        ]);

        $jenis->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis kegiatan berhasil diperbarui.',
            'data' => $jenis,
        ]);
    }

    /**
     * Hapus data jenis kegiatan.
     */
    public function destroy(int $id): JsonResponse
    {
        $jenis = JenisKegiatan::find($id);

        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Cek keterkaitan data ke data kegiatan (Flowchart Fitur 5)
        if ($jenis->kegiatan()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Data Masih Dipakai: Jenis kegiatan tidak dapat dihapus karena masih digunakan oleh data kegiatan.',
            ], 422);
        }

        $jenis->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jenis kegiatan berhasil dihapus.',
        ]);
    }
}
