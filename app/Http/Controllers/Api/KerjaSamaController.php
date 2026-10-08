<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KerjaSama;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Controller transaksi dokumen Kerja Sama kemitraan institusi/industri.
 * Mengelola validasi rentang tanggal efektif dan relasi restriktif ke entitas mitra.
 */
class KerjaSamaController extends Controller
{
    /**
     * Relasi yang selalu ikut dimuat (eager loading) agar terhindar dari N+1 query.
     *
     * @var array<int, string>
     */
    private array $relations = [
        'mitra',
    ];

    /**
     * Helper response standar: {success, code, message, data}
     *
     * @param  bool  $success
     * @param  int  $code
     * @param  string  $message
     * @param  mixed  $data
     * @return \Illuminate\Http\JsonResponse
     */
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Menyuplai aturan validasi kontrak kerja sama dengan validasi kronologis tanggal.
     *
     * @param  bool  $isUpdate  Flag mode partial update (PATCH).
     * @return array<string, mixed>
     */
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            // Validasi mitra valid, judul, tingkat (Nasional/Internasional), dan kegiatan
            'id_mitra'          => [...$req, 'integer', Rule::exists('mitra', 'id_mitra')],
            'judul_kerja_sama'  => [...$req, 'string', 'max:255'],
            'tingkat'           => [...$req, 'string', 'max:100'],
            'bentuk_kegiatan'   => [...$req, 'string', 'max:255'],
            // Validasi kronologis tanggal: tanggal selesai tidak boleh mendahului tanggal mulai
            'tanggal_mulai'     => [...$req, 'date'],
            'tanggal_selesai'   => [...$req, 'date', 'after_or_equal:tanggal_mulai'],
            'bukti_dokumen'     => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Mengambil daftar dokumen kerja sama terpaginasi dengan eager loading instansi mitra.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Inisialisasi query dengan eager loading mitra untuk optimasi performa query
        $query = KerjaSama::with($this->relations);

        // 2. Filter pencarian parsial berdasarkan judul kerja sama
        if ($search = $request->query('search')) {
            $query->where('judul_kerja_sama', 'like', "%{$search}%");
        }

        // 3. Filter eksak berdasarkan ID mitra dan tingkat kerja sama
        foreach (['id_mitra', 'tingkat'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // 4. Pengurutan data terbaru dan paginasi data
        $kerjaSama = $query->latest()->paginate($request->integer('per_page', 10));

        // 5. Kembalikan respon standar
        return $this->respond(true, 200, 'Data kerja sama berhasil diambil.', $kerjaSama);
    }

    /**
     * Menyimpan data kerja sama baru dan memastikan validitas foreign key mitra di database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Jalankan validasi input
        $validator = Validator::make($request->all(), $this->rules());

        // 2. Kembalikan respon 422 jika input tidak valid
        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // 3. Bungkus pembuatan data dalam transaksi database
            $kerjaSama = DB::transaction(function () use ($validated) {
                return KerjaSama::create($validated);
            });
        } catch (\Throwable $e) {
            // 4. Tangani error jika terjadi kegagalan basis data
            return $this->respond(false, 500, 'Gagal menyimpan data kerja sama: ' . $e->getMessage());
        }

        // 5. Kembalikan respon 201 Created dengan relasi mitra termuat
        return $this->respond(true, 201, 'Data kerja sama berhasil ditambahkan.', $kerjaSama->load($this->relations));
    }

    /**
     * Menampilkan rincian dokumen kerja sama beserta profil mitra yang terafiliasi.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Cari record kerja sama beserta relasi mitranya
        $kerjaSama = KerjaSama::with($this->relations)->find($id);

        // 2. Kembalikan respon 404 jika tidak ditemukan
        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        // 3. Kembalikan detail data kerja sama
        return $this->respond(true, 200, 'Detail kerja sama berhasil diambil.', $kerjaSama);
    }

    /**
     * Memperbarui dokumen kerja sama secara parsial dengan pengecekan integritas tanggal selesai.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Pastikan record kerja sama ada sebelum divalidasi
        $kerjaSama = KerjaSama::find($id);

        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        // 2. Validasi input dengan mode pembaruan parsial
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // 3. Eksekusi pembaruan dalam transaksi database
            DB::transaction(function () use ($kerjaSama, $validated) {
                $kerjaSama->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data kerja sama: ' . $e->getMessage());
        }

        // 4. Muat ulang data terbaru (fresh) dari database
        return $this->respond(true, 200, 'Data kerja sama berhasil diperbarui.', $kerjaSama->fresh($this->relations));
    }

    /**
     * Menghapus record kerja sama dari database setelah lolos verifikasi keberadaan data.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record kerja sama
        $kerjaSama = KerjaSama::find($id);

        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        try {
            // 2. Hapus baris data kerja sama
            $kerjaSama->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data kerja sama: ' . $e->getMessage());
        }

        // 3. Kembalikan konfirmasi penghapusan sukses
        return $this->respond(true, 200, 'Data kerja sama berhasil dihapus.');
    }
}
