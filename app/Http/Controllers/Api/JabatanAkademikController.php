<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JabatanAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JabatanAkademikController extends Controller
{
    // Ambil semua data jabatan akademik
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => JabatanAkademik::all(),
        ]);
    }

    // Tambah data jabatan akademik baru
    public function store(Request $request): JsonResponse
    {
        // Validasi nama jabatan wajib dan tidak boleh duplikat
        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100|unique:jabatan_akademik,nama_jabatan',
        ]);

        // Simpan ke database
        $jabatan = JabatanAkademik::create($validated);

        // Kembalikan respon sukses
        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil ditambahkan.',
            'data' => $jabatan,
        ], 201);
    }

    // Ambil detail satu jabatan akademik
    public function show(int $id): JsonResponse
    {
        $jabatan = JabatanAkademik::find($id);

        // Cek data ada atau tidak
        if (! $jabatan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $jabatan]);
    }

    // Update data jabatan akademik
    public function update(Request $request, int $id): JsonResponse
    {
        $jabatan = JabatanAkademik::find($id);

        // Cek data ada atau tidak
        if (! $jabatan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Validasi nama jabatan unik kecuali untuk id ini
        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100|unique:jabatan_akademik,nama_jabatan,'.$id.',id_jabatan',
        ]);

        // Update data di database
        $jabatan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil diperbarui.',
            'data' => $jabatan,
        ]);
    }

    // Hapus data jabatan akademik
    public function destroy(int $id): JsonResponse
    {
        $jabatan = JabatanAkademik::find($id);

        // Cek data ada atau tidak
        if (! $jabatan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Hapus dari database
        $jabatan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil dihapus.',
        ]);
    }
}
