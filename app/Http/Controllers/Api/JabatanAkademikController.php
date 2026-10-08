<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JabatanAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller pengelolaan data master Jabatan Akademik (Fungsional Dosen).
 * Menyediakan referensi tingkatan jabatan fungsional (Asisten Ahli, Lektor, dsb.) untuk instrumen akreditasi.
 */
class JabatanAkademikController extends Controller
{
    /**
     * Mengambil seluruh daftar master data jabatan akademik.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        // 1. Ambil seluruh record jabatan akademik dari database
        return response()->json([
            'success' => true,
            'data' => JabatanAkademik::all(),
        ]);
    }

    /**
     * Menyimpan data jabatan akademik baru dengan validasi keunikan nama.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi: nama jabatan wajib diisi, maksimal 100 karakter, dan tidak boleh duplikat
        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100|unique:jabatan_akademik,nama_jabatan',
        ]);

        // 2. Simpan record jabatan akademik baru
        $jabatan = JabatanAkademik::create($validated);

        // 3. Kembalikan respon 201 Created
        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil ditambahkan.',
            'data' => $jabatan,
        ], 201);
    }

    /**
     * Menampilkan detail satu record jabatan akademik.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Cari record jabatan akademik berdasarkan primary key
        $jabatan = JabatanAkademik::find($id);

        // 2. Proteksi 404 jika ID tidak terdaftar
        if (! $jabatan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Kembalikan detail data jabatan akademik
        return response()->json(['success' => true, 'data' => $jabatan]);
    }

    /**
     * Memperbarui nama jabatan akademik dengan mengabaikan ID aktif pada pengecekan unique.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Cari record jabatan akademik yang hendak diperbarui
        $jabatan = JabatanAkademik::find($id);

        // 2. Proteksi 404 jika data tidak ditemukan
        if (! $jabatan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Validasi keunikan nama jabatan dengan mengecualikan ID yang sedang diperbarui
        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100|unique:jabatan_akademik,nama_jabatan,'.$id.',id_jabatan',
        ]);

        // 4. Perbarui data di basis data
        $jabatan->update($validated);

        // 5. Kembalikan respon konfirmasi sukses
        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil diperbarui.',
            'data' => $jabatan,
        ]);
    }

    /**
     * Menghapus record jabatan akademik dari database.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record jabatan akademik
        $jabatan = JabatanAkademik::find($id);

        // 2. Proteksi 404 jika record tidak ditemukan
        if (! $jabatan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Eksekusi penghapusan record
        $jabatan->delete();

        // 4. Kembalikan respon konfirmasi penghapusan
        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil dihapus.',
        ]);
    }
}
