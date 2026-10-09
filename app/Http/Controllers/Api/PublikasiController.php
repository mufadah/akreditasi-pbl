<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Publikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PublikasiController extends Controller
{
    // Relasi yang selalu dimuat untuk mencegah N+1 query
    private array $relations = [
        'dosen',
        'jenisPublikasi',
        'penelitian',
        'pkm',
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

    // Aturan validasi data publikasi
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            // Penulis dosen aktif dan jenis publikasi wajib valid
            'id_dosen'           => [
                ...$req,
                'integer',
                Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at'),
            ],
            'id_jenis_publikasi' => [
                ...$req,
                'integer',
                Rule::exists('jenis_publikasi', 'id_jenis_publikasi'),
            ],
            // Relasi ke penelitian atau PKM bersifat opsional
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
            'jumlah_sitasi'      => ['nullable', 'integer', 'min:0'],
        ];
    }

    // Ambil daftar publikasi dengan filter pencarian
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi dosen, jenis publikasi, penelitian, dan PKM
        $query = Publikasi::with($this->relations);

        // Filter pencarian judul atau nama jurnal
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_publikasi', 'like', "%{$search}%")
                  ->orWhere('nama_jurnal', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan dosen, jenis, penelitian, PKM, dan tahun
        foreach (['id_dosen', 'id_jenis_publikasi', 'id_penelitian', 'id_pkm', 'tahun'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // Paginasi data 10 per halaman
        $publikasi = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data publikasi berhasil diambil.', $publikasi);
    }

    // Tambah data publikasi baru
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
            $publikasi = DB::transaction(function () use ($validated) {
                return Publikasi::create($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data publikasi: ' . $e->getMessage());
        }

        return $this->respond(true, 201, 'Data publikasi berhasil ditambahkan.', $publikasi->load($this->relations));
    }

    // Ambil detail satu publikasi
    public function show(int $id): JsonResponse
    {
        $publikasi = Publikasi::with($this->relations)->find($id);

        // Cek data ada atau tidak
        if (! $publikasi) {
            return $this->respond(false, 404, 'Data publikasi tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail publikasi berhasil diambil.', $publikasi);
    }

    // Update data publikasi
    public function update(Request $request, int $id): JsonResponse
    {
        $publikasi = Publikasi::find($id);

        // Cek data ada atau tidak
        if (! $publikasi) {
            return $this->respond(false, 404, 'Data publikasi tidak ditemukan.');
        }

        // Validasi input update parsial
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            // Update data dalam transaksi DB
            DB::transaction(function () use ($publikasi, $validated) {
                $publikasi->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data publikasi: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data publikasi berhasil diperbarui.', $publikasi->fresh($this->relations));
    }

    // Hapus data publikasi
    public function destroy(int $id): JsonResponse
    {
        $publikasi = Publikasi::find($id);

        // Cek data ada atau tidak
        if (! $publikasi) {
            return $this->respond(false, 404, 'Data publikasi tidak ditemukan.');
        }

        try {
            // Hapus data dari database
            $publikasi->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data publikasi: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data publikasi berhasil dihapus.');
    }
}
