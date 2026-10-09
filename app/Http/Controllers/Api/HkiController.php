<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hki;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HkiController extends Controller
{
    // Relasi yang selalu dimuat untuk mencegah N+1 query
    private array $relations = [
        'dosen',
        'jenisHki',
        'penelitian',
        'evidence',
    ];

    // Helper format response JSON standar
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // Aturan validasi data HKI
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'id_dosen'      => [...$req, 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'id_jenis_hki'  => [...$req, 'integer', Rule::exists('jenis_hki', 'id_jenis_hki')],
            'id_penelitian' => ['nullable', 'integer', Rule::exists('penelitian', 'id_penelitian')],
            'id_evidence'   => ['nullable', 'integer', Rule::exists('evidence', 'id_evidence')],
            'judul_hki'     => [...$req, 'string', 'max:255'],
            'nomor_hki'     => ['nullable', 'string', 'max:255'],
            'tahun'         => [...$req, 'integer', 'digits:4'],
        ];
    }

    // Ambil daftar HKI dengan filter pencarian
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi dosen, jenis HKI, penelitian, dan evidence
        $query = Hki::with($this->relations);

        // Filter pencarian judul atau nomor HKI
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_hki', 'like', "%{$search}%")
                  ->orWhere('nomor_hki', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan dosen, jenis HKI, penelitian, dan tahun
        foreach (['id_dosen', 'id_jenis_hki', 'id_penelitian', 'tahun'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // Paginasi data 10 per halaman
        $hki = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data HKI berhasil diambil.', $hki);
    }

    // Tambah data HKI baru
    public function store(Request $request): JsonResponse
    {
        // Validasi input data
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // Simpan data dalam transaksi DB
            $hki = DB::transaction(function () use ($validated) {
                return Hki::create($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data HKI: ' . $e->getMessage());
        }

        return $this->respond(true, 201, 'Data HKI berhasil ditambahkan.', $hki->load($this->relations));
    }

    // Ambil detail satu HKI beserta relasinya
    public function show(int $id): JsonResponse
    {
        $hki = Hki::with($this->relations)->find($id);

        // Cek data ada atau tidak
        if (! $hki) {
            return $this->respond(false, 404, 'Data HKI tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail HKI berhasil diambil.', $hki);
    }

    // Update data HKI
    public function update(Request $request, int $id): JsonResponse
    {
        $hki = Hki::find($id);

        // Cek data ada atau tidak
        if (! $hki) {
            return $this->respond(false, 404, 'Data HKI tidak ditemukan.');
        }

        // Validasi input update parsial
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // Update data dalam transaksi DB
            DB::transaction(function () use ($hki, $validated) {
                $hki->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data HKI: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data HKI berhasil diperbarui.', $hki->fresh($this->relations));
    }

    // Hapus data HKI
    public function destroy(int $id): JsonResponse
    {
        $hki = Hki::find($id);

        // Cek data ada atau tidak
        if (! $hki) {
            return $this->respond(false, 404, 'Data HKI tidak ditemukan.');
        }

        try {
            // Hapus data dari database
            $hki->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data HKI: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data HKI berhasil dihapus.');
    }
}
