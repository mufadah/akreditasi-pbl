<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BukuController extends Controller
{
    // Relasi yang selalu dimuat untuk mencegah N+1 query
    private array $relations = [
        'dosen',
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

    // Aturan validasi data buku
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'id_dosen'    => [...$req, 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'judul_buku'  => [...$req, 'string', 'max:255'],
            'isbn'        => ['nullable', 'string', 'max:255'],
            'penerbit'    => ['nullable', 'string', 'max:255'],
            'tahun'       => [...$req, 'integer', 'digits:4'],
            'anggota'     => ['nullable', 'string', 'max:255'],
            'bidang_ilmu' => [...$req, 'string', 'max:255'],
            'jenis_buku'  => [...$req, 'string', 'max:255'],
            'deskripsi'   => ['nullable', 'string'],
        ];
    }

    // Ambil daftar buku dengan filter pencarian
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi dosen
        $query = Buku::with($this->relations);

        // Filter pencarian judul buku, penerbit, atau ISBN
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_buku', 'like', "%{$search}%")
                  ->orWhere('penerbit', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan dosen, tahun, bidang ilmu, dan jenis buku
        foreach (['id_dosen', 'tahun', 'bidang_ilmu', 'jenis_buku'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // Paginasi data 10 per halaman
        $buku = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data buku berhasil diambil.', $buku);
    }

    // Tambah data buku baru
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
            $buku = DB::transaction(function () use ($validated) {
                return Buku::create($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data buku: ' . $e->getMessage());
        }

        return $this->respond(true, 201, 'Data buku berhasil ditambahkan.', $buku->load($this->relations));
    }

    // Ambil detail satu buku beserta data dosen
    public function show(int $id): JsonResponse
    {
        $buku = Buku::with($this->relations)->find($id);

        // Cek data ada atau tidak
        if (! $buku) {
            return $this->respond(false, 404, 'Data buku tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail buku berhasil diambil.', $buku);
    }

    // Update data buku
    public function update(Request $request, int $id): JsonResponse
    {
        $buku = Buku::find($id);

        // Cek data ada atau tidak
        if (! $buku) {
            return $this->respond(false, 404, 'Data buku tidak ditemukan.');
        }

        // Validasi input update parsial
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // Update data dalam transaksi DB
            DB::transaction(function () use ($buku, $validated) {
                $buku->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data buku: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data buku berhasil diperbarui.', $buku->fresh($this->relations));
    }

    // Hapus data buku
    public function destroy(int $id): JsonResponse
    {
        $buku = Buku::find($id);

        // Cek data ada atau tidak
        if (! $buku) {
            return $this->respond(false, 404, 'Data buku tidak ditemukan.');
        }

        try {
            // Hapus data dari database
            $buku->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data buku: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data buku berhasil dihapus.');
    }
}
