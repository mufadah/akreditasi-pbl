<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penelitian;
use App\Services\K1ApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PenelitianController extends Controller
{
    // Relasi yang selalu dimuat untuk mencegah N+1 query
    private array $relations = [
        'dosen',
        'jenisPenelitian',
        'tahunAkademik',
        'anggota.dosen',
        'pendanaan',
    ];

    // Helper format response JSON standar
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    // Aturan validasi data penelitian, anggota, dan pendanaan
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            // Validasi data utama penelitian
            'id_dosen' => [$req, 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'id_jenis_penelitian' => [$req, 'integer', Rule::exists('jenis_penelitian', 'id_jenis_penelitian')],
            'id_tahun_akademik' => [$req, 'integer', Rule::exists('tahun_akademik', 'id_tahun_akademik')],
            'judul_penelitian' => "{$req}|string|max:255",
            'tahun' => "{$req}|integer|digits:4",
            'status' => 'sometimes|string|in:Berjalan,Selesai',

            // Validasi anggota penelitian (opsional)
            'anggota' => 'sometimes|array',
            'anggota.*.jenis_anggota' => 'required_with:anggota|string|in:Dosen,Mahasiswa',
            'anggota.*.id_dosen' => [
                'nullable',
                'required_if:anggota.*.jenis_anggota,Dosen',
                'integer',
                Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at'),
            ],
            'anggota.*.mahasiswa' => 'nullable|required_if:anggota.*.jenis_anggota,Mahasiswa|string|max:255',

            // Validasi pendanaan penelitian (opsional)
            'pendanaan' => 'sometimes|array',
            'pendanaan.*.sumber_dana' => 'required_with:pendanaan|string|max:255',
            'pendanaan.*.nominal' => 'required_with:pendanaan|numeric|min:0',
            'pendanaan.*.tahun' => 'required_with:pendanaan|integer|digits:4',
        ];
    }

    // Ambil daftar penelitian dengan filter pencarian
    public function index(Request $request): JsonResponse
    {
        $query = Penelitian::with($this->relations);

        // Filter pencarian judul
        if ($search = $request->query('search')) {
            $query->where('judul_penelitian', 'like', "%{$search}%");
        }

        // Filter berdasarkan dosen, jenis, tahun akademik, tahun, dan status
        foreach (['id_dosen', 'id_jenis_penelitian', 'id_tahun_akademik', 'tahun', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // Paginasi data 10 per halaman
        $penelitian = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data penelitian berhasil diambil.', $penelitian);
    }

    // Tambah data penelitian, anggota, dan pendanaan baru
    public function store(Request $request): JsonResponse
    {
        // Validasi input request
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();
        // VALIDASI NIM MAHASISWA VIA API K1 (Tugas 1)
        if (! empty($validated['anggota'])) {
            $k1Service = app(K1ApiService::class);

            foreach ($validated['anggota'] as $anggota) {
                if (($anggota['jenis_anggota'] ?? '') === 'Mahasiswa' && ! empty($anggota['mahasiswa'])) {
                    if (! $k1Service->validateNim($anggota['mahasiswa'])) {
                        return $this->respond(false, 422, "NIM Tidak Valid: Mahasiswa dengan NIM '{$anggota['mahasiswa']}' tidak ditemukan atau tidak aktif di sistem K1.");
                    }
                }
            }
        }
        try {
            // Bungkus dalam transaksi database agar tersimpan utuh
            $penelitian = DB::transaction(function () use ($validated) {
                // Simpan data utama penelitian
                $penelitian = Penelitian::create(
                    collect($validated)->except(['anggota', 'pendanaan'])->toArray()
                );

                // Simpan data anggota jika ada
                if (! empty($validated['anggota'])) {
                    $penelitian->anggota()->createMany($validated['anggota']);
                }

                // Simpan data pendanaan jika ada
                if (! empty($validated['pendanaan'])) {
                    $penelitian->pendanaan()->createMany($validated['pendanaan']);
                }

                return $penelitian;
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data penelitian: '.$e->getMessage());
        }

        return $this->respond(true, 201, 'Data penelitian berhasil ditambahkan.', $penelitian->load($this->relations));
    }

    // Ambil detail satu penelitian
    public function show(int $id): JsonResponse
    {
        $penelitian = Penelitian::with($this->relations)->find($id);

        // Cek data ada atau tidak
        if (! $penelitian) {
            return $this->respond(false, 404, 'Data penelitian tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail penelitian berhasil diambil.', $penelitian);
    }

    // Update data penelitian beserta anggota dan pendanaannya
    public function update(Request $request, int $id): JsonResponse
    {
        $penelitian = Penelitian::find($id);

        // Cek data ada atau tidak
        if (! $penelitian) {
            return $this->respond(false, 404, 'Data penelitian tidak ditemukan.');
        }

        // Validasi input request
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();
        // VALIDASI NIM MAHASISWA VIA API K1 (Tugas 1)
        if (! empty($validated['anggota'])) {
            $k1Service = app(K1ApiService::class);

            foreach ($validated['anggota'] as $anggota) {
                if (($anggota['jenis_anggota'] ?? '') === 'Mahasiswa' && ! empty($anggota['mahasiswa'])) {
                    if (! $k1Service->validateNim($anggota['mahasiswa'])) {
                        return $this->respond(false, 422, "NIM Tidak Valid: Mahasiswa dengan NIM '{$anggota['mahasiswa']}' tidak ditemukan atau tidak aktif di sistem K1.");
                    }
                }
            }
        }

        try {
            // Update dalam transaksi database
            DB::transaction(function () use ($penelitian, $validated) {
                // Update data utama penelitian
                $penelitian->update(
                    collect($validated)->except(['anggota', 'pendanaan'])->toArray()
                );

                // Ganti data anggota jika dikirimkan
                if (array_key_exists('anggota', $validated)) {
                    $penelitian->anggota()->delete();
                    $penelitian->anggota()->createMany($validated['anggota']);
                }

                // Ganti data pendanaan jika dikirimkan
                if (array_key_exists('pendanaan', $validated)) {
                    $penelitian->pendanaan()->delete();
                    $penelitian->pendanaan()->createMany($validated['pendanaan']);
                }
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data penelitian: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data penelitian berhasil diperbarui.', $penelitian->fresh($this->relations));
    }

    // Hapus data penelitian (anggota dan pendanaan ikut terhapus otomatis)
    public function destroy(int $id): JsonResponse
    {
        $penelitian = Penelitian::find($id);

        // Cek data ada atau tidak
        if (! $penelitian) {
            return $this->respond(false, 404, 'Data penelitian tidak ditemukan.');
        }

        // Hapus dari database
        $penelitian->delete();

        return $this->respond(true, 200, 'Data penelitian berhasil dihapus.');
    }
}
