<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AktivitasKerjaSama;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AktivitasKerjaSamaController extends Controller
{
    // Relasi yang selalu dimuat untuk mencegah N+1 query
    private array $relations = [
        'kerjaSama',
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

    // Aturan validasi data aktivitas kerja sama
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'id_kerjasama'        => ['nullable', 'integer', Rule::exists('kerja_sama', 'id_kerja_sama')],
            'id_kerja_sama'       => ['nullable', 'integer', Rule::exists('kerja_sama', 'id_kerja_sama')],
            'judul_aktivitas'     => [...$req, 'string', 'max:255'],
            'tanggal_pelaksanaan' => [...$req, 'date'],
            'deskripsi'           => ['nullable', 'string'],
            'bukti_dokumen'       => ['nullable', 'string', 'max:255'],
        ];
    }

    // Ambil daftar aktivitas kerja sama dengan filter pencarian dan ID kerja sama
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi kerja sama
        $query = AktivitasKerjaSama::with($this->relations);

        // Filter kueri berdasarkan id_kerjasama atau id_kerja_sama
        if ($idKerjaSama = $request->query('id_kerjasama', $request->query('id_kerja_sama'))) {
            $query->where('id_kerja_sama', $idKerjaSama);
        }

        // Filter pencarian judul aktivitas
        if ($search = $request->query('search')) {
            $query->where('judul_aktivitas', 'like', "%{$search}%");
        }

        // Paginasi data 10 per halaman
        $aktivitas = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data aktivitas kerja sama berhasil diambil.', $aktivitas);
    }

    // Tambah data aktivitas kerja sama baru
    public function store(Request $request): JsonResponse
    {
        // Validasi input data
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        // Normalisasi foreign key kerja sama
        $idKerjaSama = $validated['id_kerja_sama'] ?? $validated['id_kerjasama'] ?? null;

        if (! $idKerjaSama) {
            return $this->respond(false, 422, 'Validasi gagal.', [
                'id_kerjasama' => ['Field id_kerjasama wajib diisi.'],
            ]);
        }

        $validated['id_kerja_sama'] = $idKerjaSama;
        unset($validated['id_kerjasama']);

        try {
            // Simpan data dalam transaksi DB
            $aktivitas = DB::transaction(function () use ($validated) {
                return AktivitasKerjaSama::create($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan aktivitas kerja sama: ' . $e->getMessage());
        }

        return $this->respond(true, 201, 'Data aktivitas kerja sama berhasil ditambahkan.', $aktivitas->load($this->relations));
    }

    // Ambil detail satu aktivitas kerja sama
    public function show(int $id): JsonResponse
    {
        $aktivitas = AktivitasKerjaSama::with($this->relations)->find($id);

        // Cek data ada atau tidak
        if (! $aktivitas) {
            return $this->respond(false, 404, 'Data aktivitas kerja sama tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail aktivitas kerja sama berhasil diambil.', $aktivitas);
    }

    // Update data aktivitas kerja sama
    public function update(Request $request, int $id): JsonResponse
    {
        $aktivitas = AktivitasKerjaSama::find($id);

        // Cek data ada atau tidak
        if (! $aktivitas) {
            return $this->respond(false, 404, 'Data aktivitas kerja sama tidak ditemukan.');
        }

        // Validasi input update parsial
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        // Normalisasi foreign key kerja sama jika dikirim
        if (array_key_exists('id_kerjasama', $validated) || array_key_exists('id_kerja_sama', $validated)) {
            $validated['id_kerja_sama'] = $validated['id_kerja_sama'] ?? $validated['id_kerjasama'];
            unset($validated['id_kerjasama']);
        }

        try {
            // Update data dalam transaksi DB
            DB::transaction(function () use ($aktivitas, $validated) {
                $aktivitas->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui aktivitas kerja sama: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data aktivitas kerja sama berhasil diperbarui.', $aktivitas->fresh($this->relations));
    }

    // Hapus data aktivitas kerja sama
    public function destroy(int $id): JsonResponse
    {
        $aktivitas = AktivitasKerjaSama::find($id);

        // Cek data ada atau tidak
        if (! $aktivitas) {
            return $this->respond(false, 404, 'Data aktivitas kerja sama tidak ditemukan.');
        }

        try {
            // Hapus data dari database
            $aktivitas->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus aktivitas kerja sama: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data aktivitas kerja sama berhasil dihapus.');
    }
}
