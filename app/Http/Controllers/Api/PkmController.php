<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pkm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Controller transaksi kegiatan Pengabdian Kepada Masyarakat (PkM).
 * Menjamin integritas referensial multi-mitra serta penanganan relasi luaran publikasi.
 */
class PkmController extends Controller
{
    /**
     * Relasi yang selalu ikut dimuat (eager loading) agar terhindar dari N+1 query.
     *
     * @var array<int, string>
     */
    private array $relations = [
        'dosen',
        'tahunAkademik',
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
     * Aturan validasi masukan kegiatan PKM dengan filter soft delete pada dosen penanggung jawab.
     *
     * @param  bool  $isUpdate  Flag mode partial update (PATCH).
     * @return array<string, mixed>
     */
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            // Validasi foreign key: dosen wajib aktif (tidak soft-deleted), tahun akademik, dan mitra wajib ada
            'id_dosen'          => [...$req, 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'id_tahun_akademik' => [...$req, 'integer', Rule::exists('tahun_akademik', 'id_tahun_akademik')],
            'id_mitra'          => [...$req, 'integer', Rule::exists('mitra', 'id_mitra')],
            'judul_pkm'         => [...$req, 'string', 'max:255'],
            'lokasi'            => ['nullable', 'string', 'max:255'],
            'tahun'             => ['nullable', 'integer', 'digits:4'],
            'status'            => ['sometimes', 'string', 'in:Berjalan,Selesai'],
        ];
    }

    /**
     * Mengambil daftar kegiatan PKM terpaginasi dengan filter judul, dosen, mitra, dan status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Inisialisasi query dengan eager loading dosen, tahun akademik, dan mitra
        $query = Pkm::with($this->relations);

        // 2. Pencarian fleksibel berdasarkan judul PKM
        if ($search = $request->query('search')) {
            $query->where('judul_pkm', 'like', "%{$search}%");
        }

        // 3. Filter eksak dinamis berdasarkan atribut PKM
        foreach (['id_dosen', 'id_tahun_akademik', 'id_mitra', 'tahun', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // 4. Pengurutan data terbaru dan paginasi data (default 10)
        $pkm = $query->latest()->paginate($request->integer('per_page', 10));

        // 5. Kembalikan respon standar
        return $this->respond(true, 200, 'Data PKM berhasil diambil.', $pkm);
    }

    /**
     * Menyimpan data kegiatan PKM baru dalam transaksi database yang aman dan terisolasi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi input request menggunakan rules terpusat
        $validator = Validator::make($request->all(), $this->rules());

        // 2. Kembalikan respon 422 jika data input tidak valid
        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        // 3. Ambil data yang lolos validasi untuk menghindari mass-assignment vulnerability
        $validated = $validator->validated();

        try {
            // 4. Eksekusi penyimpanan data di dalam transaksi database
            $pkm = DB::transaction(function () use ($validated) {
                return Pkm::create($validated);
            });
        } catch (\Throwable $e) {
            // 5. Tangkap error jika terjadi kegagalan basis data
            return $this->respond(false, 500, 'Gagal menyimpan data PKM: ' . $e->getMessage());
        }

        // 6. Muat relasi lengkap dan kembalikan respon 201 Created
        return $this->respond(true, 201, 'Data PKM berhasil ditambahkan.', $pkm->load($this->relations));
    }

    /**
     * Menampilkan detail kegiatan PKM beserta objek dosen pelaksana, mitra, dan tahun akademik.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Cari record PKM beserta data relasinya
        $pkm = Pkm::with($this->relations)->find($id);

        // 2. Kembalikan 404 jika data tidak ditemukan
        if (! $pkm) {
            return $this->respond(false, 404, 'Data PKM tidak ditemukan.');
        }

        // 3. Sajikan respon detail PKM
        return $this->respond(true, 200, 'Detail PKM berhasil diambil.', $pkm);
    }

    /**
     * Memperbarui atribut PKM secara parsial tanpa merusak integritas foreign key yang ada.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Pastikan record PKM ada sebelum divalidasi
        $pkm = Pkm::find($id);

        if (! $pkm) {
            return $this->respond(false, 404, 'Data PKM tidak ditemukan.');
        }

        // 2. Validasi input dengan mode pembaruan parsial (sometimes)
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // 3. Terapkan pembaruan data dalam transaksi database
            DB::transaction(function () use ($pkm, $validated) {
                $pkm->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data PKM: ' . $e->getMessage());
        }

        // 4. Muat ulang data terbaru (fresh) dari database
        return $this->respond(true, 200, 'Data PKM berhasil diperbarui.', $pkm->fresh($this->relations));
    }

    /**
     * Menghapus record PKM; referensi pada tabel publikasi otomatis di-null-kan (nullOnDelete).
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record PKM
        $pkm = Pkm::find($id);

        if (! $pkm) {
            return $this->respond(false, 404, 'Data PKM tidak ditemukan.');
        }

        try {
            // 2. Eksekusi penghapusan data (relasi publikasi otomatis nullOnDelete di database)
            $pkm->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data PKM: ' . $e->getMessage());
        }

        // 3. Kembalikan konfirmasi penghapusan sukses
        return $this->respond(true, 200, 'Data PKM berhasil dihapus.');
    }
}