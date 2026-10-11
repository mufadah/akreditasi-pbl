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
        $validated['id_kerja_sama'] = $id;

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
            'id_kerja_sama' => $id,
            'judul_aktivitas' => 'Perpanjangan Masa Berlaku Kerja Sama',
            'tanggal_pelaksanaan' => now()->toDateString(),
            'deskripsi' => 'Diperpanjang dari '.$oldDate.' sampai '.$request->input('tanggal_selesai_baru').'. '.($request->input('keterangan') ?? ''),
        ]);

        AuditLogService::log('UPDATE', 'KerjaSama', $id, ['tanggal_selesai' => $oldDate], ['tanggal_selesai' => $kerjaSama->tanggal_selesai]);

        return $this->respond(true, 200, 'Masa berlaku kerja sama berhasil diperpanjang.', $kerjaSama->fresh($this->relations));
    }

    // Relasi yang selalu dimuat untuk mencegah N+1 query
    private array $relations = [
        'mitra',
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

    // Aturan validasi data kerja sama
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            // Validasi mitra, judul, tingkat, bentuk kegiatan, dan tanggal
            'id_mitra' => [...$req, 'integer', Rule::exists('mitra', 'id_mitra')],
            'judul_kerja_sama' => [...$req, 'string', 'max:255'],
            'tingkat' => [...$req, 'string', 'max:100'],
            'bentuk_kegiatan' => [...$req, 'string', 'max:255'],
            'tanggal_mulai' => [...$req, 'date'],
            'tanggal_selesai' => [...$req, 'date', 'after_or_equal:tanggal_mulai'],
            'bukti_dokumen' => ['nullable', 'string', 'max:255'],
        ];
    }

    // Ambil daftar kerja sama dengan filter pencarian
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi mitra
        $query = KerjaSama::with($this->relations);

        // Filter pencarian judul kerja sama
        if ($search = $request->query('search')) {
            $query->where('judul_kerja_sama', 'like', "%{$search}%");
        }

        // Filter berdasarkan mitra dan tingkat
        foreach (['id_mitra', 'tingkat'] as $filter) {
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
            return $this->respond(false, 500, 'Gagal menyimpan data kerja sama: '.$e->getMessage());
        }

        return $this->respond(true, 201, 'Data kerja sama berhasil ditambahkan.', $kerjaSama->load($this->relations));
    }

    // Ambil detail satu kerja sama beserta profil mitra
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
            return $this->respond(false, 500, 'Gagal memperbarui data kerja sama: '.$e->getMessage());
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
            return $this->respond(false, 500, 'Gagal menghapus data kerja sama: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data kerja sama berhasil dihapus.');
    }
}
