<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisLuaran;
use App\Models\Luaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LuaranController extends Controller
{
    /**
     * Relasi yang selalu dimuat untuk mencegah N+1 query.
     */
    private array $relations = [
        'kegiatan.jenisKegiatan',
        'dosen',
        'jenisLuaran',
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
     * Membentuk aturan validasi kondisional berdasarkan jenis luaran.
     */
    private function rules(Request $request, bool $isUpdate = false, ?Luaran $existing = null): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        $baseRules = [
            'id_kegiatan' => ['nullable', 'integer', Rule::exists('kegiatan', 'id_kegiatan')],
            'id_dosen' => [...$req, 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'id_jenis_luaran' => [...$req, 'integer', Rule::exists('jenis_luaran', 'id_jenis_luaran')],
        ];

        // Cari jenis luaran terpilih
        $idJenisLuaran = $request->input('id_jenis_luaran', $existing?->id_jenis_luaran);
        $jenisLuaran = $idJenisLuaran ? JenisLuaran::find($idJenisLuaran) : null;
        $namaJenis = strtolower($jenisLuaran?->nama_jenis_luaran ?? '');

        $conditionalRules = [];

        if (str_contains($namaJenis, 'buku')) {
            $conditionalRules = [
                'judul_buku' => [...$req, 'string', 'max:255'],
                'isbn' => ['nullable', 'string', 'max:100'],
                'penerbit_buku' => ['nullable', 'string', 'max:255'],
                'tahun_terbit' => [...$req, 'integer', 'digits:4'],
                'jenis_buku' => ['nullable', 'string', 'max:100'],
                'bidang_ilmu' => ['nullable', 'string', 'max:100'],
                'halaman_buku' => ['nullable', 'string', 'max:50'],
            ];
        } elseif (str_contains($namaJenis, 'jurnal')) {
            $conditionalRules = [
                'nama_jurnal' => [...$req, 'string', 'max:255'],
                'issn' => ['nullable', 'string', 'max:100'],
                'volume' => ['nullable', 'string', 'max:50'],
                'tanggal_terbit' => ['nullable', 'date'],
                'doi' => ['nullable', 'string', 'max:255'],
                'nomor_edisi' => ['nullable', 'string', 'max:50'],
                'halaman_jurnal' => ['nullable', 'string', 'max:50'],
                'indeksasi' => ['nullable', 'string', 'max:100'],
            ];
        } elseif (str_contains($namaJenis, 'prosiding')) {
            $conditionalRules = [
                'nama_seminar' => [...$req, 'string', 'max:255'],
                'nama_prosiding' => ['nullable', 'string', 'max:255'],
                'isbn_prosiding' => ['nullable', 'string', 'max:100'],
                'penyelenggara' => ['nullable', 'string', 'max:255'],
                'tanggal_seminar' => ['nullable', 'date'],
                'lokasi_seminar' => ['nullable', 'string', 'max:255'],
                'halaman_prosiding' => ['nullable', 'string', 'max:50'],
                'doi_prosiding' => ['nullable', 'string', 'max:255'],
            ];
        } elseif (str_contains($namaJenis, 'hki')) {
            $conditionalRules = [
                'judul_hak_cipta' => [...$req, 'string', 'max:255'],
                'jenis_hki' => ['nullable', 'string', 'max:100'],
                'nomor_pendaftaran_hki' => ['nullable', 'string', 'max:100'],
                'nomor_sertifikat_hki' => ['nullable', 'string', 'max:100'],
                'tanggal_pendaftaran_hki' => ['nullable', 'date'],
                'tanggal_terbit_sertifikat_hki' => ['nullable', 'date'],
                'pemegang_hki' => ['nullable', 'string', 'max:255'],
                'status_hki' => ['nullable', 'string', 'max:100'],
            ];
        } elseif (str_contains($namaJenis, 'produk')) {
            $conditionalRules = [
                'nama_produk' => [...$req, 'string', 'max:255'],
                'jenis_produk' => ['nullable', 'string', 'max:100'],
                'status_pengembangan' => ['nullable', 'string', 'max:100'],
                'tingkat_kesiapanterapan' => ['nullable', 'string', 'max:100'],
                'tanggal_pembuatan' => ['nullable', 'date'],
                'deskripsi_produk' => ['nullable', 'string'],
                'tautan_produk' => ['nullable', 'string', 'max:255'],
            ];
        } else {
            // Default rules jika jenis belum terspesifikasi spesifik
            $conditionalRules = [
                'judul_buku' => ['nullable', 'string', 'max:255'],
                'nama_jurnal' => ['nullable', 'string', 'max:255'],
                'nama_seminar' => ['nullable', 'string', 'max:255'],
                'judul_hak_cipta' => ['nullable', 'string', 'max:255'],
                'nama_produk' => ['nullable', 'string', 'max:255'],
            ];
        }

        return array_merge($baseRules, $conditionalRules);
    }

    /**
     * Ambil daftar luaran dengan filter pencarian dan paginasi.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Luaran::with($this->relations);

        // Filter pencarian berdasarkan judul dari berbagai varian luaran
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_buku', 'like', "%{$search}%")
                    ->orWhere('nama_jurnal', 'like', "%{$search}%")
                    ->orWhere('nama_prosiding', 'like', "%{$search}%")
                    ->orWhere('nama_seminar', 'like', "%{$search}%")
                    ->orWhere('judul_hak_cipta', 'like', "%{$search}%")
                    ->orWhere('nama_produk', 'like', "%{$search}%");
            });
        }

        // Filter spesifik berdasarkan relasi dan tahun
        foreach (['id_jenis_luaran', 'id_kegiatan', 'id_dosen', 'tahun_terbit'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $luaran = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data luaran berhasil diambil.', $luaran);
    }

    /**
     * Tambah data luaran baru dengan validasi kondisional.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($request));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        // Cek duplikasi DOI, Nomor Sertifikat, atau ISBN (Flowchart Fitur 8)
        $doi = $request->input('doi') ?? $request->input('doi_prosiding');
        $isbn = $request->input('isbn') ?? $request->input('isbn_prosiding');
        $nomorSertifikat = $request->input('nomor_sertifikat_hki');

        $isDuplicate = false;
        if (! empty($doi)) {
            $isDuplicate = Luaran::where('doi', $doi)->orWhere('doi_prosiding', $doi)->exists();
        }
        if (! $isDuplicate && ! empty($isbn)) {
            $isDuplicate = Luaran::where('isbn', $isbn)->orWhere('isbn_prosiding', $isbn)->exists();
        }
        if (! $isDuplicate && ! empty($nomorSertifikat)) {
            $isDuplicate = Luaran::where('nomor_sertifikat_hki', $nomorSertifikat)->exists();
        }

        if ($isDuplicate) {
            return $this->respond(false, 422, 'Data Duplikat: DOI, Nomor Sertifikat, atau ISBN sudah terdaftar dalam sistem.');
        }

        try {
            $luaran = DB::transaction(function () use ($validated) {
                return Luaran::create($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data luaran: '.$e->getMessage());
        }

        return $this->respond(true, 201, 'Data luaran berhasil ditambahkan.', $luaran->load($this->relations));
    }

    /**
     * Ambil detail satu luaran.
     */
    public function show(int $id): JsonResponse
    {
        $luaran = Luaran::with($this->relations)->find($id);

        if (! $luaran) {
            return $this->respond(false, 404, 'Data luaran tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail luaran berhasil diambil.', $luaran);
    }

    /**
     * Update data luaran.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $luaran = Luaran::find($id);

        if (! $luaran) {
            return $this->respond(false, 404, 'Data luaran tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), $this->rules($request, true, $luaran));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($luaran, $validated) {
                $luaran->update($validated);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data luaran: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data luaran berhasil diperbarui.', $luaran->fresh($this->relations));
    }

    /**
     * Hapus data luaran.
     */
    public function destroy(int $id): JsonResponse
    {
        $luaran = Luaran::find($id);

        if (! $luaran) {
            return $this->respond(false, 404, 'Data luaran tidak ditemukan.');
        }

        // Cek keterkaitan ke Evidence (Flowchart Fitur 8)
        if ($luaran->evidence()->exists()) {
            return $this->respond(false, 422, 'Luaran Masih Dipakai: Data luaran tidak dapat dihapus karena masih terkait dengan data evidence.');
        }

        try {
            $luaran->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data luaran: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data luaran berhasil dihapus.');
    }
}
