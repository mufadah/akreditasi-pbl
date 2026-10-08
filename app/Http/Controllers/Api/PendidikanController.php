<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pendidikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller pengelolaan data master Jenjang Pendidikan Dosen dan Tendik.
 * Menyediakan referensi kualifikasi akademik (S2, S3, dsb.) untuk perhitungan indikator akreditasi.
 */
class PendidikanController extends Controller
{
    /**
     * Mengambil seluruh daftar master data jenjang pendidikan.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        // 1. Ambil seluruh record jenjang pendidikan dari database
        return response()->json([
            'success' => true,
            'data' => Pendidikan::all(),
        ]);
    }

    /**
     * Menyimpan data jenjang pendidikan baru dengan validasi keunikan nama jenjang.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi: jenjang pendidikan wajib diisi, maksimal 50 karakter, dan tidak boleh duplikat
        $validated = $request->validate([
            'jenjang' => 'required|string|max:50|unique:pendidikan,jenjang',
        ]);

        // 2. Simpan record jenjang pendidikan baru
        $pendidikan = Pendidikan::create($validated);

        // 3. Kembalikan respon 201 Created
        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil ditambahkan.',
            'data' => $pendidikan,
        ], 201);
    }

    /**
     * Menampilkan detail satu record jenjang pendidikan.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Cari record jenjang pendidikan berdasarkan ID
        $pendidikan = Pendidikan::find($id);

        // 2. Proteksi 404 jika ID tidak ditemukan
        if (! $pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Kembalikan detail data jenjang pendidikan
        return response()->json(['success' => true, 'data' => $pendidikan]);
    }

    /**
     * Memperbarui nama jenjang pendidikan dengan mengabaikan ID aktif pada pengecekan unique.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Cari record jenjang pendidikan yang hendak diperbarui
        $pendidikan = Pendidikan::find($id);

        // 2. Proteksi 404 jika record tidak ditemukan
        if (! $pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Validasi keunikan jenjang dengan mengecualikan ID yang sedang diperbarui
        $validated = $request->validate([
            'jenjang' => 'required|string|max:50|unique:pendidikan,jenjang,'.$id.',id_pendidikan',
        ]);

        // 4. Perbarui data di basis data
        $pendidikan->update($validated);

        // 5. Kembalikan respon sukses pembaruan data
        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil diperbarui.',
            'data' => $pendidikan,
        ]);
    }

    /**
     * Menghapus record jenjang pendidikan dari database.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record jenjang pendidikan
        $pendidikan = Pendidikan::find($id);

        // 2. Proteksi 404 jika record tidak ditemukan
        if (! $pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Eksekusi penghapusan record
        $pendidikan->delete();

        // 4. Kembalikan respon konfirmasi penghapusan
        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil dihapus.',
        ]);
    }
}
