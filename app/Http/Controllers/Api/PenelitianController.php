<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penelitian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PenelitianController extends Controller
{
    /**
     * Relasi yang selalu ikut dimuat (eager loading) agar tidak terjadi N+1 query.
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
     * Aturan validasi. $isUpdate = true -> field utama boleh tidak dikirim (partial update).
     */
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            // Data utama penelitian
            'id_dosen' => [$req, 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'id_jenis_penelitian' => [$req, 'integer', Rule::exists('jenis_penelitian', 'id_jenis_penelitian')],
            'id_tahun_akademik' => [$req, 'integer', Rule::exists('tahun_akademik', 'id_tahun_akademik')],
            'judul_penelitian' => "{$req}|string|max:255",
            'tahun' => "{$req}|integer|digits:4",
            'status' => 'sometimes|string|in:Berjalan,Selesai',

            // Anggota penelitian (opsional, array)
            'anggota' => 'sometimes|array',
            'anggota.*.jenis_anggota' => 'required_with:anggota|string|in:Dosen,Mahasiswa',
            'anggota.*.id_dosen' => [
                'nullable',
                'required_if:anggota.*.jenis_anggota,Dosen',
                'integer',
                Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at'),
            ],
            'anggota.*.mahasiswa' => 'nullable|required_if:anggota.*.jenis_anggota,Mahasiswa|string|max:255',

            // Pendanaan penelitian (opsional, array)
            'pendanaan' => 'sometimes|array',
            'pendanaan.*.sumber_dana' => 'required_with:pendanaan|string|max:255',
            'pendanaan.*.nominal' => 'required_with:pendanaan|numeric|min:0',
            'pendanaan.*.tahun' => 'required_with:pendanaan|integer|digits:4',
        ];
    }

    /**
     * GET /api/penelitian
     * Query opsional: search, id_dosen, id_jenis_penelitian, id_tahun_akademik, tahun, status, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = Penelitian::with($this->relations);

        if ($search = $request->query('search')) {
            $query->where('judul_penelitian', 'like', "%{$search}%");
        }

        foreach (['id_dosen', 'id_jenis_penelitian', 'id_tahun_akademik', 'tahun', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $penelitian = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data penelitian berhasil diambil.', $penelitian);
    }

    /**
     * POST /api/penelitian
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            $penelitian = DB::transaction(function () use ($validated) {
                // 1. Simpan data utama (tanpa array anak)
                $penelitian = Penelitian::create(
                    collect($validated)->except(['anggota', 'pendanaan'])->toArray()
                );

                // 2. Simpan anggota & pendanaan lewat relasi hasMany
                if (! empty($validated['anggota'])) {
                    $penelitian->anggota()->createMany($validated['anggota']);
                }

                if (! empty($validated['pendanaan'])) {
                    $penelitian->pendanaan()->createMany($validated['pendanaan']);
                }

                return $penelitian;
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data penelitian: ' . $e->getMessage());
        }

        return $this->respond(true, 201, 'Data penelitian berhasil ditambahkan.', $penelitian->load($this->relations));
    }

    /**
     * GET /api/penelitian/{id}
     */
    public function show(int $id): JsonResponse
    {
        $penelitian = Penelitian::with($this->relations)->find($id);

        if (! $penelitian) {
            return $this->respond(false, 404, 'Data penelitian tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail penelitian berhasil diambil.', $penelitian);
    }

    /**
     * PUT/PATCH /api/penelitian/{id}
     * Jika array 'anggota' / 'pendanaan' dikirim -> data lama diganti total (replace strategy).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $penelitian = Penelitian::find($id);

        if (! $penelitian) {
            return $this->respond(false, 404, 'Data penelitian tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($penelitian, $validated) {
                $penelitian->update(
                    collect($validated)->except(['anggota', 'pendanaan'])->toArray()
                );

                if (array_key_exists('anggota', $validated)) {
                    $penelitian->anggota()->delete();
                    $penelitian->anggota()->createMany($validated['anggota']);
                }

                if (array_key_exists('pendanaan', $validated)) {
                    $penelitian->pendanaan()->delete();
                    $penelitian->pendanaan()->createMany($validated['pendanaan']);
                }
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data penelitian: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data penelitian berhasil diperbarui.', $penelitian->fresh($this->relations));
    }

    /**
     * DELETE /api/penelitian/{id}
     * Anggota & pendanaan ikut terhapus otomatis (cascadeOnDelete di migration).
     */
    public function destroy(int $id): JsonResponse
    {
        $penelitian = Penelitian::find($id);

        if (! $penelitian) {
            return $this->respond(false, 404, 'Data penelitian tidak ditemukan.');
        }

        $penelitian->delete();

        return $this->respond(true, 200, 'Data penelitian berhasil dihapus.');
    }
}
