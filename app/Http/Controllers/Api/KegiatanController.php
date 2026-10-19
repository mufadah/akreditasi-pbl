<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KegiatanController extends Controller
{
    /**
     * Relasi yang selalu dimuat untuk mencegah N+1 query.
     */
    private array $relations = [
        'jenisKegiatan',
        'anggota.dosen',
        'pendanaan',
        'luaran',
        'evidence',
    ];

    /**
     * Helper format response JSON standar.
     */
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Aturan validasi data kegiatan, anggota, dan pendanaan.
     */
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            // Data utama Kegiatan
            'id_jenis_kegiatan' => [...$req, 'integer', Rule::exists('jenis_kegiatan', 'id_jenis_kegiatan')],
            'judul_kegiatan' => [...$req, 'string', 'max:255'],
            'lokasi_kegiatan' => ['nullable', 'string', 'max:255'],
            'tahun_usulan' => ['nullable', 'integer', 'digits:4'],
            'tahun_kegiatan' => ['nullable', 'integer', 'digits:4'],
            'tahun_pelaksanaan' => ['nullable', 'integer', 'digits:4'],
            'lama_kegiatan' => ['nullable', 'string', 'max:100'],
            'status_kegiatan' => ['sometimes', 'string', 'max:50'],
            'in_kind' => ['nullable', 'string', 'max:255'],

            // Validasi anggota kegiatan (opsional/nested)
            'anggota' => ['sometimes', 'array'],
            'anggota.*.jenis_anggota' => ['required_with:anggota', 'string', 'in:dosen,mahasiswa,Dosen,Mahasiswa'],
            'anggota.*.id_dosen' => [
                'nullable',
                'required_if:anggota.*.jenis_anggota,dosen,Dosen',
                'integer',
                Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at'),
            ],
            'anggota.*.id_mahasiswa' => ['nullable'],
            'anggota.*.peran' => ['required_with:anggota', 'string', 'max:50'],

            // Validasi pendanaan kegiatan (opsional/nested)
            'pendanaan' => ['sometimes', 'array'],
            'pendanaan.*.sumber_dana' => ['required_with:pendanaan', 'string', 'max:255'],
            'pendanaan.*.jumlah_dana' => ['required_with:pendanaan', 'numeric', 'min:0'],
            'pendanaan.*.tahun_pendanaan' => ['required_with:pendanaan', 'integer', 'digits:4'],
            'pendanaan.*.jenis_pendanaan' => ['nullable', 'string', 'max:100'],
            'pendanaan.*.keterangan' => ['nullable', 'string'],
        ];
    }

    /**
     * Ambil daftar kegiatan dengan filter pencarian dan paginasi.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Kegiatan::with($this->relations);

        // Filter pencarian judul atau lokasi kegiatan
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_kegiatan', 'like', "%{$search}%")
                    ->orWhere('lokasi_kegiatan', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan jenis kegiatan, tahun, dan status
        foreach (['id_jenis_kegiatan', 'tahun_usulan', 'tahun_kegiatan', 'tahun_pelaksanaan', 'status_kegiatan'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $kegiatan = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data kegiatan berhasil diambil.', $kegiatan);
    }

    /**
     * Tambah data kegiatan baru beserta nested anggota dan pendanaan.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            $kegiatan = DB::transaction(function () use ($validated) {
                $kegiatan = Kegiatan::create(
                    collect($validated)->except(['anggota', 'pendanaan'])->toArray()
                );

                if (! empty($validated['anggota'])) {
                    // Normalisasi jenis_anggota ke lowercase
                    $anggotaData = array_map(function ($item) {
                        if (isset($item['jenis_anggota'])) {
                            $item['jenis_anggota'] = strtolower($item['jenis_anggota']);
                        }

                        return $item;
                    }, $validated['anggota']);

                    $kegiatan->anggotaKegiatan()->createMany($anggotaData);
                }

                if (! empty($validated['pendanaan'])) {
                    $kegiatan->pendanaanKegiatan()->createMany($validated['pendanaan']);
                }

                return $kegiatan;
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data kegiatan: '.$e->getMessage());
        }

        return $this->respond(true, 201, 'Data kegiatan berhasil ditambahkan.', $kegiatan->load($this->relations));
    }

    /**
     * Ambil detail satu kegiatan.
     */
    public function show(int $id): JsonResponse
    {
        $kegiatan = Kegiatan::with($this->relations)->find($id);

        if (! $kegiatan) {
            return $this->respond(false, 404, 'Data kegiatan tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail kegiatan berhasil diambil.', $kegiatan);
    }

    /**
     * Update data kegiatan beserta anggota dan pendanaan.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $kegiatan = Kegiatan::find($id);

        if (! $kegiatan) {
            return $this->respond(false, 404, 'Data kegiatan tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($kegiatan, $validated) {
                $kegiatan->update(
                    collect($validated)->except(['anggota', 'pendanaan'])->toArray()
                );

                if (array_key_exists('anggota', $validated)) {
                    $kegiatan->anggotaKegiatan()->delete();
                    $anggotaData = array_map(function ($item) {
                        if (isset($item['jenis_anggota'])) {
                            $item['jenis_anggota'] = strtolower($item['jenis_anggota']);
                        }

                        return $item;
                    }, $validated['anggota'] ?? []);

                    if (! empty($anggotaData)) {
                        $kegiatan->anggotaKegiatan()->createMany($anggotaData);
                    }
                }

                if (array_key_exists('pendanaan', $validated)) {
                    $kegiatan->pendanaanKegiatan()->delete();
                    if (! empty($validated['pendanaan'])) {
                        $kegiatan->pendanaanKegiatan()->createMany($validated['pendanaan']);
                    }
                }
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data kegiatan: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data kegiatan berhasil diperbarui.', $kegiatan->fresh($this->relations));
    }

    /**
     * Hapus data kegiatan (anggota dan pendanaan cascade).
     */
    public function destroy(int $id): JsonResponse
    {
        $kegiatan = Kegiatan::find($id);

        if (! $kegiatan) {
            return $this->respond(false, 404, 'Data kegiatan tidak ditemukan.');
        }

        try {
            $kegiatan->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data kegiatan: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data kegiatan berhasil dihapus.');
    }
}
