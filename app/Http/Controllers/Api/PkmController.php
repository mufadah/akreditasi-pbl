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
    // Relasi yang selalu dimuat untuk mencegah N+1 query
    private array $relations = [
        'dosen',
        'tahunAkademik',
        'mitra',
        'jenisPkm',
        'anggotaPkm',
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

    // Aturan validasi data PKM dan anggotanya
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            // Dosen aktif, tahun akademik, dan mitra wajib ada
            'id_dosen'          => [...$req, 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'id_tahun_akademik' => [...$req, 'integer', Rule::exists('tahun_akademik', 'id_tahun_akademik')],
            'id_mitra'          => [...$req, 'integer', Rule::exists('mitra', 'id_mitra')],
            'id_jenis_pkm'      => ['nullable', 'integer', Rule::exists('jenis_pkm', 'id_jenis_pkm')],
            'judul_pkm'         => [...$req, 'string', 'max:255'],
            'lokasi'            => ['nullable', 'string', 'max:255'],
            'tahun'             => ['nullable', 'integer', 'digits:4'],
            'status'            => ['sometimes', 'string', 'in:Berjalan,Selesai'],
            'jenis_pelaksana'   => ['nullable', 'string', 'max:255'],

            // Validasi data anggota mahasiswa PKM (opsional)
            'anggota'                => ['sometimes', 'array'],
            'anggota.*.id_mahasiswa' => ['required_with:anggota', 'integer'],
            'anggota.*.peran'        => ['required_with:anggota', 'string', 'in:Ketua,Anggota'],
            'anggota.*.semester'     => ['nullable', 'string', 'max:50'],
        ];
    }

    // Ambil daftar kegiatan PKM dengan filter pencarian
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi lengkap
        $query = Pkm::with($this->relations);

        // Filter pencarian judul PKM
        if ($search = $request->query('search')) {
            $query->where('judul_pkm', 'like', "%{$search}%");
        }

        // Filter berdasarkan dosen, tahun akademik, mitra, jenis PKM, tahun, dan status
        foreach (['id_dosen', 'id_tahun_akademik', 'id_mitra', 'id_jenis_pkm', 'tahun', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // Paginasi data 10 per halaman
        $pkm = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data PKM berhasil diambil.', $pkm);
    }

    // Tambah data kegiatan PKM baru beserta anggotanya
    public function store(Request $request): JsonResponse
    {
        // Validasi input data
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // Simpan data utama dan anggota PKM dalam transaksi DB
            $pkm = DB::transaction(function () use ($validated) {
                $pkm = Pkm::create(
                    collect($validated)->except(['anggota'])->toArray()
                );

                if (! empty($validated['anggota'])) {
                    $pkm->anggotaPkm()->createMany($validated['anggota']);
                }

                return $pkm;
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data PKM: ' . $e->getMessage());
        }

        return $this->respond(true, 201, 'Data PKM berhasil ditambahkan.', $pkm->load($this->relations));
    }

    // Ambil detail satu kegiatan PKM
    public function show(int $id): JsonResponse
    {
        $pkm = Pkm::with($this->relations)->find($id);

        // Cek data ada atau tidak
        if (! $pkm) {
            return $this->respond(false, 404, 'Data PKM tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail PKM berhasil diambil.', $pkm);
    }

    // Update data kegiatan PKM beserta anggotanya
    public function update(Request $request, int $id): JsonResponse
    {
        $pkm = Pkm::find($id);

        // Cek data ada atau tidak
        if (! $pkm) {
            return $this->respond(false, 404, 'Data PKM tidak ditemukan.');
        }

        // Validasi input update parsial
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // Update data utama dan ganti data anggota dalam transaksi DB
            DB::transaction(function () use ($pkm, $validated) {
                $pkm->update(
                    collect($validated)->except(['anggota'])->toArray()
                );

                // Ganti data anggota jika dikirimkan (replace strategy)
                if (array_key_exists('anggota', $validated)) {
                    $pkm->anggotaPkm()->delete();
                    if (! empty($validated['anggota'])) {
                        $pkm->anggotaPkm()->createMany($validated['anggota']);
                    }
                }
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data PKM: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data PKM berhasil diperbarui.', $pkm->fresh($this->relations));
    }

    // Hapus data kegiatan PKM
    public function destroy(int $id): JsonResponse
    {
        $pkm = Pkm::find($id);

        // Cek data ada atau tidak
        if (! $pkm) {
            return $this->respond(false, 404, 'Data PKM tidak ditemukan.');
        }

        try {
            // Hapus data dari database (relasi anggota otomatis cascade)
            $pkm->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data PKM: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data PKM berhasil dihapus.');
    }
}