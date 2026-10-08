<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisPenelitian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller pengelolaan data master Jenis Penelitian.
 * Mengklasifikasikan skema penelitian (Dasar, Terapan, Pengembangan, dsb.) untuk evaluasi Tridharma.
 */
class JenisPenelitianController extends Controller
{
    /**
     * Mengambil seluruh daftar master data jenis penelitian.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        // 1. Ambil seluruh record jenis penelitian dari database
        return response()->json([
            'success' => true,
            'data' => JenisPenelitian::all(),
        ]);
    }

    /**
     * Menyimpan data skema jenis penelitian baru dengan validasi keunikan nama.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi: nama jenis penelitian wajib diisi, maksimal 255 karakter, dan tidak boleh duplikat
        $validated = $request->validate([
            'nama_jenis_penelitian' => 'required|string|max:255|unique:jenis_penelitian,nama_jenis_penelitian',
        ]);

        // 2. Simpan record jenis penelitian baru
        $jenis = JenisPenelitian::create($validated);

        // 3. Kembalikan respon 201 Created
        return response()->json([
            'success' => true,
            'message' => 'Jenis penelitian berhasil ditambahkan.',
            'data' => $jenis,
        ], 201);
    }

    /**
     * Menampilkan detail satu record jenis penelitian.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Cari record jenis penelitian berdasarkan ID
        $jenis = JenisPenelitian::find($id);

        // 2. Proteksi 404 jika ID tidak ditemukan
        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Kembalikan detail data jenis penelitian
        return response()->json(['success' => true, 'data' => $jenis]);
    }

    /**
     * Memperbarui nama jenis penelitian dengan mengabaikan ID aktif pada pengecekan unique.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Cari record jenis penelitian yang hendak diperbarui
        $jenis = JenisPenelitian::find($id);

        // 2. Proteksi 404 jika data tidak ditemukan
        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Validasi keunikan nama jenis penelitian dengan mengecualikan ID aktif
        $validated = $request->validate([
            'nama_jenis_penelitian' => 'required|string|max:255|unique:jenis_penelitian,nama_jenis_penelitian,'.$id.',id_jenis_penelitian',
        ]);

        // 4. Perbarui data di basis data
        $jenis->update($validated);

        // 5. Kembalikan respon sukses pembaruan data
        return response()->json([
            'success' => true,
            'message' => 'Jenis penelitian berhasil diperbarui.',
            'data' => $jenis,
        ]);
    }

    /**
     * Menghapus record jenis penelitian dari database.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record jenis penelitian
        $jenis = JenisPenelitian::find($id);

        // 2. Proteksi 404 jika record tidak ditemukan
        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Eksekusi penghapusan record
        $jenis->delete();

        // 4. Kembalikan respon konfirmasi penghapusan
        return response()->json([
            'success' => true,
            'message' => 'Jenis penelitian berhasil dihapus.',
        ]);
    }
}
