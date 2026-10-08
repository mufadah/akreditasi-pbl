<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisPublikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisPublikasiController extends Controller
{
    // Ambil semua data jenis publikasi
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => JenisPublikasi::all(),
        ]);
    }

    // Tambah data jenis publikasi baru
    public function store(Request $request): JsonResponse
    {
        // Validasi nama jenis publikasi wajib dan unik
        $validated = $request->validate([
            'nama_jenis_publikasi' => 'required|string|max:255|unique:jenis_publikasi,nama_jenis_publikasi',
            'indeks' => 'nullable|string|max:100',
        ]);

        // Simpan ke database
        $publikasi = JenisPublikasi::create($validated);

        // Kembalikan respon sukses
        return response()->json([
            'success' => true,
            'message' => 'Jenis publikasi berhasil ditambahkan.',
            'data' => $publikasi,
        ], 201);
    }

    // Ambil detail satu jenis publikasi
    public function show(int $id): JsonResponse
    {
        $publikasi = JenisPublikasi::find($id);

        // Cek data ada atau tidak
        if (! $publikasi) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $publikasi]);
    }

    // Update data jenis publikasi
    public function update(Request $request, int $id): JsonResponse
    {
        $publikasi = JenisPublikasi::find($id);

        // Cek data ada atau tidak
        if (! $publikasi) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Validasi nama jenis publikasi unik kecuali untuk id ini
        $validated = $request->validate([
            'nama_jenis_publikasi' => 'required|string|max:255|unique:jenis_publikasi,nama_jenis_publikasi,'.$id.',id_jenis_publikasi',
            'indeks' => 'nullable|string|max:100',
        ]);

        // Update data di database
        $publikasi->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis publikasi berhasil diperbarui.',
            'data' => $publikasi,
        ]);
    }

    // Hapus data jenis publikasi
    public function destroy(int $id): JsonResponse
    {
        $publikasi = JenisPublikasi::find($id);

        // Cek data ada atau tidak
        if (! $publikasi) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Hapus dari database
        $publikasi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jenis publikasi berhasil dihapus.',
        ]);
    }
}
