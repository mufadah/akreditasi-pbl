<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenagaKependidikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller pengelolaan data master Tenaga Kependidikan (Tendik).
 * Mendukung pencarian multi-kolom dan eager loading terhadap profil kualifikasi pendidikan.
 */
class TenagaKependidikanController extends Controller
{
    /**
     * Mengambil daftar tenaga kependidikan terpaginasi dengan pencarian nama, NIP, atau jabatan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Inisialisasi query dengan eager loading relasi pendidikan untuk mengeliminasi N+1 query
        $query = TenagaKependidikan::with('pendidikan');

        // 2. Filter pencarian parsial (LIKE) pada atribut nama, NIP, atau jabatan tendik
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_tendik', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('jabatan_tendik', 'like', "%{$search}%");
            });
        }

        // 3. Batasi data per halaman dengan paginasi
        $tendik = $query->paginate($request->integer('per_page', 10));

        // 4. Sajikan respon JSON sukses beserta data tendik
        return response()->json([
            'success' => true,
            'data' => $tendik,
        ]);
    }

    /**
     * Menyimpan profil data tenaga kependidikan baru.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi input: foreign key pendidikan valid, nama dan jabatan wajib, serta NIP bersifat unik
        $validated = $request->validate([
            'id_pendidikan' => 'required|exists:pendidikan,id_pendidikan',
            'nama_tendik' => 'required|string|max:255',
            'jabatan_tendik' => 'required|string|max:255',
            'nip' => 'required|string|max:50|unique:tenaga_kependidikan,nip',
        ]);

        // 2. Simpan record tendik baru ke database
        $tendik = TenagaKependidikan::create($validated);

        // 3. Kembalikan respon 201 Created dengan relasi pendidikan termuat
        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil ditambahkan.',
            'data' => $tendik->load('pendidikan'),
        ], 201);
    }

    /**
     * Menampilkan detail informasi tenaga kependidikan beserta kualifikasi pendidikannya.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Ambil data tendik beserta relasi pendidikannya
        $tendik = TenagaKependidikan::with('pendidikan')->find($id);

        // 2. Proteksi 404 jika record tidak ditemukan
        if (! $tendik) {
            return response()->json(['success' => false, 'message' => 'Data tendik tidak ditemukan.'], 404);
        }

        // 3. Kembalikan data tendik
        return response()->json(['success' => true, 'data' => $tendik]);
    }

    /**
     * Memperbarui profil tenaga kependidikan secara parsial.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Cari record tendik yang hendak diperbarui
        $tendik = TenagaKependidikan::find($id);

        // 2. Proteksi 404 jika ID tidak terdaftar
        if (! $tendik) {
            return response()->json(['success' => false, 'message' => 'Data tendik tidak ditemukan.'], 404);
        }

        // 3. Validasi parsial (sometimes) dengan pengecualian ID saat pengecekan keunikan NIP
        $validated = $request->validate([
            'id_pendidikan' => 'sometimes|required|exists:pendidikan,id_pendidikan',
            'nama_tendik' => 'sometimes|required|string|max:255',
            'jabatan_tendik' => 'sometimes|required|string|max:255',
            'nip' => 'sometimes|required|string|max:50|unique:tenaga_kependidikan,nip,'.$id.',id_tendik',
        ]);

        // 4. Perbarui data tendik di database
        $tendik->update($validated);

        // 5. Kembalikan respon sukses dengan data fresh beserta relasinya
        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil diperbarui.',
            'data' => $tendik->fresh('pendidikan'),
        ]);
    }

    /**
     * Menghapus record tenaga kependidikan dari database.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record tendik
        $tendik = TenagaKependidikan::find($id);

        // 2. Proteksi 404 jika data tidak ditemukan
        if (! $tendik) {
            return response()->json(['success' => false, 'message' => 'Data tendik tidak ditemukan.'], 404);
        }

        // 3. Eksekusi penghapusan data
        $tendik->delete();

        // 4. Kembalikan respon konfirmasi penghapusan
        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil dihapus.',
        ]);
    }
}
