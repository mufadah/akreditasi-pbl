<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pkm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PkmController extends Controller
{
    // Relasi yang selalu ikut dimuat (eager loading) agar tidak terjadi N+1 query.
    private array $relations = [
        'dosen',
        'tahunAkademik',
        'mitra',
    ];

    // Helper response standar: {success, code, message, data}
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // Aturan validasi. $isUpdate = true -> field utama boleh tidak dikirim (partial update).
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'id_dosen'          => [...$req, 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'id_tahun_akademik' => [...$req, 'integer', Rule::exists('tahun_akademik', 'id_tahun_akademik')],
            'id_mitra'          => [...$req, 'integer', Rule::exists('mitra', 'id_mitra')],
            'judul_pkm'         => [...$req, 'string', 'max:255'],
            'lokasi'            => ['nullable', 'string', 'max:255'],
            'tahun'             => ['nullable', 'integer', 'digits:4'],
            'status'            => ['sometimes', 'string', 'in:Berjalan,Selesai'],
        ];
    }

    // GET /api/pkm
    // Query opsional: search, id_dosen, id_tahun_akademik, id_mitra, tahun, status, per_page
    public function index(Request $request): JsonResponse
    {
        $query = Pkm::with($this->relations);

        // Pencarian berdasarkan judul
        if ($search = $request->query('search')) {
            $query->where('judul_pkm', 'like', "%{$search}%");
        }

        // Filter persis (exact match) untuk kolom-kolom tertentu
        foreach (['id_dosen', 'id_tahun_akademik', 'id_mitra', 'tahun', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $pkm = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data PKM berhasil diambil.', $pkm);
    }

    // POST /api/pkm
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            $pkm = DB::transaction(function () use ($validated) {
                return Pkm::create($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data PKM: ' . $e->getMessage());
        }

        return $this->respond(true, 201, 'Data PKM berhasil ditambahkan.', $pkm->load($this->relations));
    }

    // GET /api/pkm/{id}
    public function show(int $id): JsonResponse
    {
        $pkm = Pkm::with($this->relations)->find($id);

        if (! $pkm) {
            return $this->respond(false, 404, 'Data PKM tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail PKM berhasil diambil.', $pkm);
    }

    // PUT/PATCH /api/pkm/{id}
    // Hanya field yang dikirim yang akan diubah (partial update).
    public function update(Request $request, int $id): JsonResponse
    {
        $pkm = Pkm::find($id);

        if (! $pkm) {
            return $this->respond(false, 404, 'Data PKM tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($pkm, $validated) {
                $pkm->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data PKM: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data PKM berhasil diperbarui.', $pkm->fresh($this->relations));
    }

    // DELETE /api/pkm/{id}
    // Publikasi yang terkait PKM ini tidak ikut terhapus; kolom id_pkm-nya menjadi NULL
    // (nullOnDelete di migration publikasi).
    public function destroy(int $id): JsonResponse
    {
        $pkm = Pkm::find($id);

        if (! $pkm) {
            return $this->respond(false, 404, 'Data PKM tidak ditemukan.');
        }

        try {
            $pkm->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data PKM: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data PKM berhasil dihapus.');
    }
}
?>