<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisPublikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller pengelolaan data master Jenis Publikasi Ilmiah.
 * Mengklasifikasikan medium luaran (Jurnal Internasional Bereputasi, Nasional Terakreditasi, Prosiding, dsb.).
 */
class JenisPublikasiController extends Controller
{
    /**
     * Mengambil seluruh daftar master data jenis publikasi ilmiah.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        // 1. Ambil seluruh record jenis publikasi dari database
        return response()->json([
            'success' => true,
            'data' => JenisPublikasi::all(),
        ]);
    }

    /**
     * Menyimpan data kategori jenis publikasi baru beserta indeks pengindeksnya.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi: nama jenis publikasi wajib dan unik; indeks (Scopus, SINTA, dll.) bersifat opsional
        $validated = $request->validate([
            'nama_jenis_publikasi' => 'required|string|max:255|unique:jenis_publikasi,nama_jenis_publikasi',
            'indeks' => 'nullable|string|max:100',
        ]);

        // 2. Simpan record jenis publikasi baru
        $publikasi = JenisPublikasi::create($validated);

        // 3. Kembalikan respon 201 Created
        return response()->json([
            'success' => true,
            'message' => 'Jenis publikasi berhasil ditambahkan.',
            'data' => $publikasi,
        ], 201);
    }

    /**
     * Menampilkan detail satu record jenis publikasi.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Cari record jenis publikasi berdasarkan ID
        $publikasi = JenisPublikasi::find($id);

        // 2. Proteksi 404 jika ID tidak ditemukan
        if (! $publikasi) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Kembalikan detail data jenis publikasi
        return response()->json(['success' => true, 'data' => $publikasi]);
    }

    /**
     * Memperbarui informasi jenis publikasi dengan pengecualian ID aktif pada pengecekan unik.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Cari record jenis publikasi yang hendak diperbarui
        $publikasi = JenisPublikasi::find($id);

        // 2. Proteksi 404 jika record tidak ditemukan
        if (! $publikasi) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Validasi keunikan nama jenis publikasi dengan mengecualikan ID yang sedang diperbarui
        $validated = $request->validate([
            'nama_jenis_publikasi' => 'required|string|max:255|unique:jenis_publikasi,nama_jenis_publikasi,'.$id.',id_jenis_publikasi',
            'indeks' => 'nullable|string|max:100',
        ]);

        // 4. Perbarui data di basis data
        $publikasi->update($validated);

        // 5. Kembalikan respon sukses pembaruan data
        return response()->json([
            'success' => true,
            'message' => 'Jenis publikasi berhasil diperbarui.',
            'data' => $publikasi,
        ]);
    }

    /**
     * Menghapus record jenis publikasi dari database.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record jenis publikasi
        $publikasi = JenisPublikasi::find($id);

        // 2. Proteksi 404 jika record tidak ditemukan
        if (! $publikasi) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // 3. Eksekusi penghapusan record
        $publikasi->delete();

        // 4. Kembalikan respon konfirmasi penghapusan
        return response()->json([
            'success' => true,
            'message' => 'Jenis publikasi berhasil dihapus.',
        ]);
    }
}
