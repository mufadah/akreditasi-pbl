<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penelitian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Controller transaksi kegiatan Penelitian, relasi tim peneliti, dan sumber pendanaan.
 * Mengimplementasikan transaksi database atomik untuk menjamin konsistensi multi-tabel.
 */
class PenelitianController extends Controller
{
    /**
     * Relasi yang selalu ikut dimuat (eager loading) agar tidak terjadi N+1 query.
     *
     * @var array<int, string>
     */
    private array $relations = [
        'dosen',
        'jenisPenelitian',
        'tahunAkademik',
        'anggota.dosen',
        'pendanaan',
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
     * Aturan validasi masukan transaksi penelitian, anggota tim, dan rincian pendanaan.
     * Mengamankan integritas foreign key dengan filter soft deletes pada tabel dosen.
     *
     * @param  bool  $isUpdate  Flag mode partial update (PATCH).
     * @return array<string, mixed>
     */
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            // Validasi data tabel induk penelitian
            'id_dosen'            => [$req, 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'id_jenis_penelitian' => [$req, 'integer', Rule::exists('jenis_penelitian', 'id_jenis_penelitian')],
            'id_tahun_akademik'   => [$req, 'integer', Rule::exists('tahun_akademik', 'id_tahun_akademik')],
            'judul_penelitian'    => "{$req}|string|max:255",
            'tahun'               => "{$req}|integer|digits:4",
            'status'              => 'sometimes|string|in:Berjalan,Selesai',

            // Validasi array relasi anak: anggota tim penelitian (dosen / mahasiswa)
            'anggota'                 => 'sometimes|array',
            'anggota.*.jenis_anggota' => 'required_with:anggota|string|in:Dosen,Mahasiswa',
            'anggota.*.id_dosen'      => [
                'nullable',
                'required_if:anggota.*.jenis_anggota,Dosen',
                'integer',
                Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at'),
            ],
            'anggota.*.mahasiswa'     => 'nullable|required_if:anggota.*.jenis_anggota,Mahasiswa|string|max:255',

            // Validasi array relasi anak: pos pendanaan penelitian
            'pendanaan'               => 'sometimes|array',
            'pendanaan.*.sumber_dana' => 'required_with:pendanaan|string|max:255',
            'pendanaan.*.nominal'     => 'required_with:pendanaan|numeric|min:0',
            'pendanaan.*.tahun'       => 'required_with:pendanaan|integer|digits:4',
        ];
    }

    /**
     * Mengambil daftar penelitian terpaginasi dengan filtering multi-kolom dinamis.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Inisialisasi query builder dengan eager loading seluruh relasi hierarkis
        $query = Penelitian::with($this->relations);

        // 2. Filter pencarian parsial berdasarkan judul penelitian
        if ($search = $request->query('search')) {
            $query->where('judul_penelitian', 'like', "%{$search}%");
        }

        // 3. Filter eksak dinamis berdasarkan atribut kunci penelitian
        foreach (['id_dosen', 'id_jenis_penelitian', 'id_tahun_akademik', 'tahun', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // 4. Pengurutan data terbaru dan paginasi data
        $penelitian = $query->latest()->paginate($request->integer('per_page', 10));

        // 5. Kembalikan format respon standar 200 OK
        return $this->respond(true, 200, 'Data penelitian berhasil diambil.', $penelitian);
    }

    /**
     * Menyimpan data penelitian, anggota tim, dan pendanaan secara atomik dalam satu transaksi.
     * Mencegah data yatim (orphaned records) melalui rollback otomatis jika terjadi kegagalan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi input request
        $validator = Validator::make($request->all(), $this->rules());

        // 2. Gagalkan operasi dengan status 422 jika ada format data yang tidak valid
        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        // 3. Ekstrak data yang telah lolos sensor validasi
        $validated = $validator->validated();

        try {
            // 4. Bungkus dalam database transaction guna menjaga kepatuhan prinsip ACID
            $penelitian = DB::transaction(function () use ($validated) {
                // a. Simpan record induk penelitian terlebih dahulu tanpa data array anak
                $penelitian = Penelitian::create(
                    collect($validated)->except(['anggota', 'pendanaan'])->toArray()
                );

                // b. Simpan daftar anggota penelitian jika disertakan
                if (! empty($validated['anggota'])) {
                    $penelitian->anggota()->createMany($validated['anggota']);
                }

                // c. Simpan rincian sumber dana jika disertakan
                if (! empty($validated['pendanaan'])) {
                    $penelitian->pendanaan()->createMany($validated['pendanaan']);
                }

                return $penelitian;
            });
        } catch (\Throwable $e) {
            // 5. Tangkap exception dan otomatis rollback transaksi
            return $this->respond(false, 500, 'Gagal menyimpan data penelitian: ' . $e->getMessage());
        }

        // 6. Muat ulang relasi dan kembalikan respon 201 Created
        return $this->respond(true, 201, 'Data penelitian berhasil ditambahkan.', $penelitian->load($this->relations));
    }

    /**
     * Menampilkan detail satu kegiatan penelitian beserta seluruh data relasi anak.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Ambil record penelitian beserta relasi lengkapnya
        $penelitian = Penelitian::with($this->relations)->find($id);

        // 2. Proteksi 404 jika ID penelitian tidak ditemukan
        if (! $penelitian) {
            return $this->respond(false, 404, 'Data penelitian tidak ditemukan.');
        }

        // 3. Kembalikan detail data penelitian lengkap
        return $this->respond(true, 200, 'Detail penelitian berhasil diambil.', $penelitian);
    }

    /**
     * Memperbarui data penelitian dan mengganti penuh data anak (replace strategy) dalam DB transaction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Cari record penelitian yang hendak diperbarui
        $penelitian = Penelitian::find($id);

        // 2. Gagalkan pembaruan dengan 404 jika data tidak ditemukan
        if (! $penelitian) {
            return $this->respond(false, 404, 'Data penelitian tidak ditemukan.');
        }

        // 3. Validasi request dengan mode pembaruan parsial (sometimes)
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // 4. Eksekusi pembaruan dalam transaksi database atomik
            DB::transaction(function () use ($penelitian, $validated) {
                // a. Perbarui kolom data induk penelitian
                $penelitian->update(
                    collect($validated)->except(['anggota', 'pendanaan'])->toArray()
                );

                // b. Terapkan strategi penggantian total (replace) pada relasi anak anggota jika array dikirim
                if (array_key_exists('anggota', $validated)) {
                    $penelitian->anggota()->delete();
                    $penelitian->anggota()->createMany($validated['anggota']);
                }

                // c. Terapkan strategi penggantian total pada relasi pendanaan jika array dikirim
                if (array_key_exists('pendanaan', $validated)) {
                    $penelitian->pendanaan()->delete();
                    $penelitian->pendanaan()->createMany($validated['pendanaan']);
                }
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data penelitian: ' . $e->getMessage());
        }

        // 5. Muat ulang data terbaru (fresh) dari database untuk disajikan dalam respon
        return $this->respond(true, 200, 'Data penelitian berhasil diperbarui.', $penelitian->fresh($this->relations));
    }

    /**
     * Menghapus record penelitian beserta data anak secara otomatis melalui DB cascade delete.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record penelitian
        $penelitian = Penelitian::find($id);

        // 2. Proteksi 404 jika data tidak ditemukan
        if (! $penelitian) {
            return $this->respond(false, 404, 'Data penelitian tidak ditemukan.');
        }

        // 3. Hapus data induk; relasi anak terhapus otomatis di level basis data (cascadeOnDelete)
        $penelitian->delete();

        // 4. Kembalikan konfirmasi penghapusan data
        return $this->respond(true, 200, 'Data penelitian berhasil dihapus.');
    }
}
