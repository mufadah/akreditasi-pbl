<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mitra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller pengelolaan data master Mitra kerjasama dan instansi eksternal.
 * Menerapkan proteksi integritas relasi referensial (restrict on delete) terhadap aktivitas PKM dan Kerja Sama.
 */
class MitraController extends Controller
{
    /**
     * Mengambil daftar mitra terpaginasi dengan pencarian multi-kolom (nama, email, alamat).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Inisialisasi query builder untuk entitas Mitra
        $query = Mitra::query();

        // 2. Pencarian komprehensif berbasis pencocokan parsial (LIKE) pada nama instansi, email, atau alamat
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_instansi', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%");
            });
        }

        // 3. Paginasikan data mitra (default 10 baris per halaman)
        $mitra = $query->paginate($request->integer('per_page', 10));

        // 4. Kembalikan payload data mitra
        return response()->json([
            'success' => true,
            'data' => $mitra,
        ]);
    }

    /**
     * Menyimpan profil instansi mitra baru ke dalam basis data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi input: nama instansi wajib diisi; alamat, email, dan telepon bersifat opsional
        $validated = $request->validate([
            'nama_instansi' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telepon' => 'nullable|string|max:50',
        ]);

        // 2. Simpan record mitra baru
        $mitra = Mitra::create($validated);

        // 3. Kembalikan respon 201 Created beserta data mitra yang baru dibuat
        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil ditambahkan.',
            'data' => $mitra,
        ], 201);
    }

    /**
     * Menampilkan detail profil mitra beserta daftar kegiatan PKM dan kerja sama yang terafiliasi.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Ambil data mitra dan eager load relasi histori aktivitas (pkm dan kerjaSama)
        $mitra = Mitra::with(['pkm', 'kerjaSama'])->find($id);

        // 2. Kembalikan 404 Not Found jika ID mitra tidak ditemukan
        if (! $mitra) {
            return response()->json([
                'success' => false,
                'message' => 'Data mitra tidak ditemukan.',
            ], 404);
        }

        // 3. Kembalikan rincian data mitra
        return response()->json([
            'success' => true,
            'data' => $mitra,
        ]);
    }

    /**
     * Memperbarui informasi profil instansi mitra secara parsial.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Cari record mitra yang hendak diperbarui
        $mitra = Mitra::find($id);

        // 2. Proteksi 404 jika ID tidak valid
        if (! $mitra) {
            return response()->json([
                'success' => false,
                'message' => 'Data mitra tidak ditemukan.',
            ], 404);
        }

        // 3. Validasi parsial (sometimes) untuk mendukung update fleksibel
        $validated = $request->validate([
            'nama_instansi' => 'sometimes|required|string|max:255',
            'alamat' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telepon' => 'nullable|string|max:50',
        ]);

        // 4. Perbarui data mitra di database
        $mitra->update($validated);

        // 5. Kembalikan respon konfirmasi sukses
        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil diperbarui.',
            'data' => $mitra,
        ]);
    }

    /**
     * Menghapus record mitra dengan validasi restriksi keterkaitan foreign key (restrict on delete).
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record mitra yang hendak dihapus
        $mitra = Mitra::find($id);

        // 2. Proteksi 404 jika mitra tidak ditemukan
        if (! $mitra) {
            return response()->json([
                'success' => false,
                'message' => 'Data mitra tidak ditemukan.',
            ], 404);
        }

        // 3. Penegakan integritas bisnis (restrict on delete): cegah penghapusan jika mitra memiliki relasi aktif di PKM atau Kerja Sama
        if ($mitra->pkm()->exists() || $mitra->kerjaSama()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Mitra tidak dapat dihapus karena masih terkait dengan data PKM atau kerja sama.',
            ], 422);
        }

        // 4. Eksekusi penghapusan record jika tidak memiliki relasi dependen
        $mitra->delete();

        // 5. Kembalikan konfirmasi penghapusan
        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil dihapus.',
        ]);
    }
}
