<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BidangKeahlian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidangKeahlianController extends Controller
{
    // Ambil semua data bidang keahlian
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => BidangKeahlian::all(),
        ]);
    }

    // Tambah data bidang keahlian baru
    public function store(Request $request): JsonResponse
    {
        // Validasi nama bidang wajib dan tidak boleh duplikat
        $validated = $request->validate([
            'nama_bidang' => 'required|string|max:100|unique:bidang_keahlian,nama_bidang',
        ]);

        // Simpan ke database
        $keahlian = BidangKeahlian::create($validated);

        // Kembalikan respon sukses
        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil ditambahkan.',
            'data' => $keahlian,
        ], 201);
    }

    // Ambil detail satu bidang keahlian
    public function show(int $id): JsonResponse
    {
        $keahlian = BidangKeahlian::find($id);

        // Cek data ada atau tidak
        if (! $keahlian) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $keahlian]);
    }

    // Update data bidang keahlian
    public function update(Request $request, int $id): JsonResponse
    {
        $keahlian = BidangKeahlian::find($id);

        // Cek data ada atau tidak
        if (! $keahlian) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Validasi nama bidang unik kecuali untuk id ini
        $validated = $request->validate([
            'nama_bidang' => 'required|string|max:100|unique:bidang_keahlian,nama_bidang,'.$id.',id_bidang_keahlian',
        ]);

        // Update data di database
        $keahlian->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil diperbarui.',
            'data' => $keahlian,
        ]);
    }

    // Hapus data bidang keahlian
    public function destroy(int $id): JsonResponse
    {
        $keahlian = BidangKeahlian::find($id);

        // Cek data ada atau tidak
        if (! $keahlian) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Hapus dari database
        $keahlian->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil dihapus.',
        ]);
    }
}
