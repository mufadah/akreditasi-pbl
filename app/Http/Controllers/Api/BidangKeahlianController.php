<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BidangKeahlian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller pengelolaan data master Bidang Keahlian (Kompetensi Dosen).
 * Menyediakan referensi keilmuan yang terhubung secara many-to-many dengan profil Dosen.
 */
class BidangKeahlianController extends Controller
{
    /**
     * Mengambil seluruh daftar master data bidang keahlian.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        // 1. Ambil seluruh record bidang keahlian dari database
        return response()->json([
            'success' => true,
            'data' => BidangKeahlian::all(),
        ]);
    }

    /**
     * Menyimpan data bidang keahlian baru dengan validasi keunikan nama.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi: nama bidang wajib ada, string maksimal 100 karakter, dan tidak boleh duplikat
        $validated = $request->validate([
            'nama_bidang' => 'required|string|max:100|unique:bidang_keahlian,nama_bidang',
        ]);

        // 2. Simpan record bidang keahlian baru
        $keahlian = BidangKeahlian::create($validated);

        // 3. Kembalikan respon 201 Created
        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil ditambahkan.',
            'data' => $keahlian,
        ], 201);
    }

    /**
     * Menampilkan detail satu record bidang keahlian.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Cari record bidang keahlian berdasarkan ID
        $keahlian = BidangKeahlian::find($id);

        // 2. Proteksi 404 jika ID tidak ditemukan
        if (! $keahlian) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Kembalikan data bidang keahlian
        return response()->json(['success' => true, 'data' => $keahlian]);
    }

    /**
     * Memperbarui nama bidang keahlian dengan mengabaikan ID aktif pada pengecekan unique.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Cari record bidang keahlian yang hendak diperbarui
        $keahlian = BidangKeahlian::find($id);

        // 2. Proteksi 404 jika record tidak ada
        if (! $keahlian) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Validasi keunikan nama bidang dengan mengecualikan ID yang sedang diperbarui
        $validated = $request->validate([
            'nama_bidang' => 'required|string|max:100|unique:bidang_keahlian,nama_bidang,'.$id.',id_bidang_keahlian',
        ]);

        // 4. Perbarui data pada basis data
        $keahlian->update($validated);

        // 5. Kembalikan respon sukses pembaruan data
        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil diperbarui.',
            'data' => $keahlian,
        ]);
    }

    /**
     * Menghapus record bidang keahlian dari database.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record bidang keahlian
        $keahlian = BidangKeahlian::find($id);

        // 2. Proteksi 404 jika data tidak ditemukan
        if (! $keahlian) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Eksekusi penghapusan record
        $keahlian->delete();

        // 4. Kembalikan respon konfirmasi penghapusan sukses
        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil dihapus.',
        ]);
    }
}
