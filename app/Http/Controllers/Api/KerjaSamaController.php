<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KerjaSama;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KerjaSamaController extends Controller
{
    // Relasi yang selalu ikut dimuat (eager loading) agar terhindar dari N+1 query.
    private array $relations = [
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
            'id_mitra'          => [...$req, 'integer', Rule::exists('mitra', 'id_mitra')],
            'judul_kerja_sama'  => [...$req, 'string', 'max:255'],
            'tingkat'           => [...$req, 'string', 'max:100'],
            'bentuk_kegiatan'   => [...$req, 'string', 'max:255'],
            'tanggal_mulai'     => [...$req, 'date'],
            'tanggal_selesai'   => [...$req, 'date', 'after_or_equal:tanggal_mulai'],
            'bukti_dokumen'     => ['nullable', 'string', 'max:255'],
        ];
    }

    // GET /api/kerja-sama
    public function index(Request $request): JsonResponse
    {
        $query = KerjaSama::with($this->relations);

        // Filter pencarian berdasarkan judul kerja sama
        if ($search = $request->query('search')) {
            $query->where('judul_kerja_sama', 'like', "%{$search}%");
        }

        // Filter persis (exact match)
        foreach (['id_mitra', 'tingkat'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $kerjaSama = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data kerja sama berhasil diambil.', $kerjaSama);
    }

    // POST /api/kerja-sama
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            $kerjaSama = DB::transaction(function () use ($validated) {
                return KerjaSama::create($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data kerja sama: ' . $e->getMessage());
        }

        return $this->respond(true, 201, 'Data kerja sama berhasil ditambahkan.', $kerjaSama->load($this->relations));
    }

    // GET /api/kerja-sama/{id}
    public function show(int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::with($this->relations)->find($id);

        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail kerja sama berhasil diambil.', $kerjaSama);
    }

    // PUT/PATCH /api/kerja-sama/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::find($id);

        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($kerjaSama, $validated) {
                $kerjaSama->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data kerja sama: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data kerja sama berhasil diperbarui.', $kerjaSama->fresh($this->relations));
    }

    // DELETE /api/kerja-sama/{id}
    public function destroy(int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::find($id);

        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        try {
            $kerjaSama->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data kerja sama: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data kerja sama berhasil dihapus.');
    }
}
