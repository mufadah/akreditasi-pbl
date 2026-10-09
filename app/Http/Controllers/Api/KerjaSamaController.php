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
    // Relasi yang selalu dimuat untuk mencegah N+1 query
    private array $relations = [
        'mitra',
        'jenisKerjaSama',
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

    // Aturan validasi data kerja sama
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            // Validasi mitra, judul, tingkat, bentuk kegiatan, dan tanggal
            'id_mitra'            => [...$req, 'integer', Rule::exists('mitra', 'id_mitra')],
            'id_jenis_kerjasama'  => ['nullable', 'integer', Rule::exists('jenis_kerja_sama', 'id_jenis_kerjasama')],
            'id_dosen'            => ['nullable', 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'judul_kerja_sama'    => [...$req, 'string', 'max:255'],
            'tingkat'             => [...$req, 'string', 'max:100'],
            'bentuk_kegiatan'     => [...$req, 'string', 'max:255'],
            'tanggal_mulai'       => [...$req, 'date'],
            'tanggal_selesai'     => [...$req, 'date', 'after_or_equal:tanggal_mulai'],
            'bukti_dokumen'       => ['nullable', 'string', 'max:255'],
            'nomor_dokumen'       => ['nullable', 'string', 'max:255'],
            'jenis_dokumen'       => ['nullable', 'string', 'max:100'],
        ];
    }

    // Ambil daftar kerja sama dengan filter pencarian
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi mitra, jenis kerja sama, dan dosen
        $query = KerjaSama::with($this->relations);

        // Filter pencarian judul atau nomor dokumen kerja sama
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_kerja_sama', 'like', "%{$search}%")
                  ->orWhere('nomor_dokumen', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan mitra, tingkat, jenis kerja sama, dan dosen
        foreach (['id_mitra', 'tingkat', 'id_jenis_kerjasama', 'id_dosen'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // Paginasi data 10 per halaman
        $kerjaSama = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data kerja sama berhasil diambil.', $kerjaSama);
    }

    // Tambah data kerja sama baru
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
            $kerjaSama = DB::transaction(function () use ($validated) {
                return KerjaSama::create($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data kerja sama: ' . $e->getMessage());
        }

        return $this->respond(true, 201, 'Data kerja sama berhasil ditambahkan.', $kerjaSama->load($this->relations));
    }

    // Ambil detail satu kerja sama beserta profil mitra, jenis, dan dosen
    public function show(int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::with($this->relations)->find($id);

        // Cek data ada atau tidak
        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail kerja sama berhasil diambil.', $kerjaSama);
    }

    // Update data kerja sama
    public function update(Request $request, int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::find($id);

        // Cek data ada atau tidak
        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        // Validasi input update parsial
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // Update data dalam transaksi DB
            DB::transaction(function () use ($kerjaSama, $validated) {
                $kerjaSama->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data kerja sama: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data kerja sama berhasil diperbarui.', $kerjaSama->fresh($this->relations));
    }

    // Hapus data kerja sama
    public function destroy(int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::find($id);

        // Cek data ada atau tidak
        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        try {
            // Hapus data dari database
            $kerjaSama->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data kerja sama: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data kerja sama berhasil dihapus.');
    }
}
