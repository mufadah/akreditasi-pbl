<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Publikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Controller transaksi Publikasi Ilmiah dan luaran kegiatan Tridharma.
 * Mendukung asosiasi polimorfik semu terhadap proyek Penelitian maupun PkM secara opsional.
 */
class PublikasiController extends Controller
{
    /**
     * Relasi yang selalu ikut dimuat (eager loading) agar terhindar dari N+1 query.
     *
     * @var array<int, string>
     */
    private array $relations = [
        'dosen',
        'jenisPublikasi',
        'penelitian',
        'pkm',
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
     * Menyuplai skema validasi publikasi; referensi penelitian dan PKM bersifat nullable.
     *
     * @param  bool  $isUpdate  Flag mode partial update (PATCH).
     * @return array<string, mixed>
     */
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            // Penulis utama dosen wajib aktif (tidak soft-deleted)
            'id_dosen'           => [
                ...$req,
                'integer',
                Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at'),
            ],
            // Kategori jenis publikasi wajib ada di tabel master
            'id_jenis_publikasi' => [
                ...$req,
                'integer',
                Rule::exists('jenis_publikasi', 'id_jenis_publikasi'),
            ],
            // Keterkaitan luaran kegiatan bersifat opsional (nullable)
            'id_penelitian'      => [
                'nullable',
                'integer',
                Rule::exists('penelitian', 'id_penelitian'),
            ],
            'id_pkm'             => [
                'nullable',
                'integer',
                Rule::exists('pkm', 'id_pkm'),
            ],
            'judul_publikasi'    => [...$req, 'string', 'max:255'],
            'nama_jurnal'        => ['nullable', 'string', 'max:255'],
            'tahun'              => [...$req, 'integer', 'digits:4'],
            'tautan'             => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Mengambil daftar publikasi terpaginasi dengan pencarian komposit judul dan nama jurnal.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Inisialisasi query dengan eager loading seluruh entitas terkait
        $query = Publikasi::with($this->relations);

        // 2. Pencarian komposit yang dikelompokkan (WHERE (...) AND ...) agar tidak merusak filter lainnya
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_publikasi', 'like', "%{$search}%")
                  ->orWhere('nama_jurnal', 'like', "%{$search}%");
            });
        }

        // 3. Filter eksak dinamis berdasarkan dosen, jenis, induk penelitian/pkm, atau tahun
        foreach (['id_dosen', 'id_jenis_publikasi', 'id_penelitian', 'id_pkm', 'tahun'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // 4. Pengurutan data terbaru dan paginasi data
        $publikasi = $query->latest()->paginate($request->integer('per_page', 10));

        // 5. Kembalikan respon standar
        return $this->respond(true, 200, 'Data publikasi berhasil diambil.', $publikasi);
    }

    /**
     * Menyimpan data publikasi baru baik sebagai luaran kegiatan maupun publikasi mandiri.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Jalankan validasi input
        $validator = Validator::make($request->all(), $this->rules());

        // 2. Kembalikan 422 jika validasi gagal
        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // 3. Persistensi data dalam transaksi database
            $publikasi = DB::transaction(function () use ($validated) {
                return Publikasi::create($validated);
            });
        } catch (\Throwable $e) {
            // 4. Tangani error jika terjadi kegagalan basis data
            return $this->respond(false, 500, 'Gagal menyimpan data publikasi: ' . $e->getMessage());
        }

        // 5. Kembalikan respon 201 Created beserta relasinya
        return $this->respond(true, 201, 'Data publikasi berhasil ditambahkan.', $publikasi->load($this->relations));
    }

    /**
     * Menampilkan rincian publikasi beserta identitas penulis dosen dan proyek pengaitnya.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Ambil record publikasi beserta data relasi penulis dan kegiatannya
        $publikasi = Publikasi::with($this->relations)->find($id);

        // 2. Kembalikan 404 jika tidak ditemukan
        if (! $publikasi) {
            return $this->respond(false, 404, 'Data publikasi tidak ditemukan.');
        }

        // 3. Sajikan respon detail publikasi
        return $this->respond(true, 200, 'Detail publikasi berhasil diambil.', $publikasi);
    }

    /**
     * Memperbarui atribut publikasi dengan validasi fleksibel terhadap tautan kegiatan asal.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Pastikan record publikasi ada sebelum divalidasi
        $publikasi = Publikasi::find($id);

        if (! $publikasi) {
            return $this->respond(false, 404, 'Data publikasi tidak ditemukan.');
        }

        // 2. Validasi input dengan mode pembaruan parsial
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // 3. Terapkan pembaruan data dalam transaksi database
            DB::transaction(function () use ($publikasi, $validated) {
                $publikasi->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data publikasi: ' . $e->getMessage());
        }

        // 4. Muat ulang data terbaru (fresh) dari database
        return $this->respond(true, 200, 'Data publikasi berhasil diperbarui.', $publikasi->fresh($this->relations));
    }

    /**
     * Menghapus record publikasi ilmiah secara permanen dari database.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record publikasi
        $publikasi = Publikasi::find($id);

        if (! $publikasi) {
            return $this->respond(false, 404, 'Data publikasi tidak ditemukan.');
        }

        try {
            // 2. Eksekusi hard delete pada baris publikasi
            $publikasi->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data publikasi: ' . $e->getMessage());
        }

        // 3. Kembalikan konfirmasi penghapusan sukses
        return $this->respond(true, 200, 'Data publikasi berhasil dihapus.');
    }
}
