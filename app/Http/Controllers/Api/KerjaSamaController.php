<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AktivitasKerjaSama;
use App\Models\KerjaSama;
use App\Services\AuditLogService;
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
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    // Ambil daftar aktivitas implementasi dari satu kerja sama
    public function getAktivitas(int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::find($id);

        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        $aktivitas = $kerjaSama->aktivitas()->latest('tanggal_pelaksanaan')->get();

        return $this->respond(true, 200, 'Daftar aktivitas implementasi berhasil diambil.', $aktivitas);
    }

    // Catat aktivitas implementasi baru untuk kerja sama
    public function storeAktivitas(Request $request, int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::find($id);

        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'judul_aktivitas' => 'required|string|max:255',
            'tanggal_pelaksanaan' => 'required|date',
            'deskripsi' => 'nullable|string',
            'bukti_dokumen' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();
        $validated['id_kerjasama'] = $id;

        $aktivitas = AktivitasKerjaSama::create($validated);

        // Catat ke audit log
        AuditLogService::log('CREATE', 'AktivitasKerjaSama', $aktivitas->id_aktivitas, null, $aktivitas->toArray());

        return $this->respond(true, 201, 'Aktivitas implementasi kerja sama berhasil dicatat.', $aktivitas);
    }

    // Perpanjang masa berlaku kerja sama
    public function perpanjang(Request $request, int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::find($id);

        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'tanggal_selesai_baru' => 'required|date|after:'.$kerjaSama->tanggal_selesai,
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Tanggal selesai baru harus setelah tanggal selesai sebelumnya.', $validator->errors());
        }

        $oldDate = $kerjaSama->tanggal_selesai;
        $kerjaSama->update([
            'tanggal_selesai' => $request->input('tanggal_selesai_baru'),
        ]);

        // Catat aktivitas perpanjangan otomatis
        AktivitasKerjaSama::create([
            'id_kerjasama' => $id,
            'judul_aktivitas' => 'Perpanjangan Masa Berlaku Kerja Sama',
            'tanggal_pelaksanaan' => now()->toDateString(),
            'deskripsi' => 'Diperpanjang dari '.$oldDate.' sampai '.$request->input('tanggal_selesai_baru').'. '.($request->input('keterangan') ?? ''),
        ]);

        AuditLogService::log('UPDATE', 'KerjaSama', $id, ['tanggal_selesai' => $oldDate], ['tanggal_selesai' => $kerjaSama->tanggal_selesai]);

        return $this->respond(true, 200, 'Masa berlaku kerja sama berhasil diperpanjang.', $kerjaSama->fresh($this->relations));
    }

    // Aturan validasi data kerja sama
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'id_mitra' => [...$req, 'integer', Rule::exists('mitra', 'id_mitra')],
            'id_jenis_kerjasama' => [...$req, 'integer', Rule::exists('jenis_kerja_sama', 'id_jenis_kerjasama')],
            'id_dosen' => [...$req, 'integer', Rule::exists('dosen', 'id_dosen')],
            'nomor_dokumen' => [...$req, 'string', 'max:255'],
            'jenis_dokumen' => [...$req, 'string', 'max:100'],
            'tanggal_mulai' => [...$req, 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }

    // Ambil daftar kerja sama dengan filter pencarian
    public function index(Request $request): JsonResponse
    {
        $query = KerjaSama::with($this->relations);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nomor_dokumen', 'like', "%{$search}%")
                  ->orWhere('jenis_dokumen', 'like', "%{$search}%");
            });
        }

        foreach (['id_mitra', 'id_jenis_kerjasama', 'id_dosen', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $kerjaSama = $query->latest('id_kerjasama')->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data kerja sama berhasil diambil.', $kerjaSama);
    }

    // Tambah data kerja sama baru
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
            AuditLogService::log('CREATE', 'KerjaSama', $kerjaSama->id_kerjasama, null, $kerjaSama->toArray());
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data kerja sama: '.$e->getMessage());
        }

        return $this->respond(true, 201, 'Data kerja sama berhasil ditambahkan.', $kerjaSama->load($this->relations));
    }

    // Ambil detail satu kerja sama beserta profil mitra
    public function show(int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::with($this->relations)->find($id);

        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail kerja sama berhasil diambil.', $kerjaSama);
    }

    // Update data kerja sama
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
        $oldValues = $kerjaSama->toArray();

        try {
            DB::transaction(function () use ($kerjaSama, $validated) {
                $kerjaSama->update($validated);
            });
            AuditLogService::log('UPDATE', 'KerjaSama', $id, $oldValues, $kerjaSama->fresh()->toArray());
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data kerja sama: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data kerja sama berhasil diperbarui.', $kerjaSama->fresh($this->relations));
    }

    // Hapus data kerja sama
    public function destroy(int $id): JsonResponse
    {
        $kerjaSama = KerjaSama::find($id);

        if (! $kerjaSama) {
            return $this->respond(false, 404, 'Data kerja sama tidak ditemukan.');
        }

        try {
            AuditLogService::log('DELETE', 'KerjaSama', $id, $kerjaSama->toArray(), null);
            $kerjaSama->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data kerja sama: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data kerja sama berhasil dihapus.');
    }
}
