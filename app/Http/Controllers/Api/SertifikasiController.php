<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\Sertifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SertifikasiController extends Controller
{
    /**
     * Relasi yang selalu dimuat untuk mencegah N+1 query.
     */
    private array $relations = [
        'dosen',
        'tendik',
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
     * Aturan validasi data sertifikasi.
     */
    private function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'id_dosen' => ['nullable', 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
            'id_tendik' => ['nullable', 'integer', Rule::exists('tenaga_kependidikan', 'id_tendik')],
            'jenis_sertifikasi' => [...$req, 'string', 'max:255'],
            'bidang_studi' => ['nullable', 'string', 'max:255'],
            'nomor_registrasi_pendidik' => ['nullable', 'string', 'max:100'],
            'nomor_sk' => ['nullable', 'string', 'max:100'],
            'nomor_sertifikat' => ['nullable', 'string', 'max:100'],
            'lembaga_penerbit' => ['nullable', 'string', 'max:255'],
            'tahun_sertifikasi' => [
                ...$req,
                function ($attribute, $value, $fail) {
                    if (is_numeric($value) && strlen((string) $value) === 4) {
                        return;
                    }
                    if (is_string($value) && strtotime($value) !== false) {
                        return;
                    }
                    $fail("Kolom {$attribute} harus berupa tahun 4 digit (YYYY) atau format tanggal valid (YYYY-MM-DD).");
                },
            ],
            'tanggal_kedaluwarsa' => ['nullable', 'date'],
            'status_sertifikasi' => ['sometimes', 'string', 'max:50'],
            'url_sertifikasi' => [...$req, 'string', 'max:2048'],
            'evidence_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'url_drive' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Ambil daftar sertifikasi dengan filter pencarian dan paginasi.
     * Mendukung filter: search, tipe_pemilik (dosen/tendik), status_masa_berlaku,
     * tahun_sertifikasi, tahun_mulai, tahun_selesai, id_dosen, id_tendik, dan status_sertifikasi.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sertifikasi::with($this->relations);

        // 1. Filter pencarian teks luas (jenis, bidang studi, no. sertifikat, penerbit, serta nama/identitas pemilik)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('jenis_sertifikasi', 'like', "%{$search}%")
                    ->orWhere('bidang_studi', 'like', "%{$search}%")
                    ->orWhere('nomor_sertifikat', 'like', "%{$search}%")
                    ->orWhere('nomor_sk', 'like', "%{$search}%")
                    ->orWhere('nomor_registrasi_pendidik', 'like', "%{$search}%")
                    ->orWhere('lembaga_penerbit', 'like', "%{$search}%")
                    ->orWhereHas('dosen', function ($qd) use ($search) {
                        $qd->where('nama_dosen', 'like', "%{$search}%")
                            ->orWhere('nidn', 'like', "%{$search}%");
                    })
                    ->orWhereHas('tendik', function ($qt) use ($search) {
                        $qt->where('nama_tendik', 'like', "%{$search}%")
                            ->orWhere('nip', 'like', "%{$search}%");
                    });
            });
        }

        // 2. Filter tipe pemilik (dosen vs tendik)
        if ($tipePemilik = $request->query('tipe_pemilik')) {
            $tipeLower = strtolower($tipePemilik);
            if ($tipeLower === 'dosen') {
                $query->whereNotNull('id_dosen');
            } elseif ($tipeLower === 'tendik') {
                $query->whereNotNull('id_tendik');
            }
        }

        // 3. Filter status masa berlaku (aktif, kedaluwarsa, segera_berakhir)
        $today = now()->toDateString();
        if ($statusMasaBerlaku = $request->query('status_masa_berlaku', $request->query('status'))) {
            $statusVal = strtolower($statusMasaBerlaku);
            if ($statusVal === 'aktif') {
                $query->where(function ($q) use ($today) {
                    $q->whereNull('tanggal_kedaluwarsa')
                        ->orWhere('tanggal_kedaluwarsa', '>=', $today);
                })->where(function ($q) {
                    $q->whereNull('status_sertifikasi')
                        ->orWhere('status_sertifikasi', '!=', 'Kedaluwarsa');
                });
            } elseif (in_array($statusVal, ['kedaluwarsa', 'expired'])) {
                $query->where(function ($q) use ($today) {
                    $q->where(function ($sq) use ($today) {
                        $sq->whereNotNull('tanggal_kedaluwarsa')
                            ->where('tanggal_kedaluwarsa', '<', $today);
                    })->orWhere('status_sertifikasi', 'Kedaluwarsa');
                });
            } elseif (in_array($statusVal, ['segera_berakhir', 'expiring_soon'])) {
                $hari = $request->integer('hari_kedaluwarsa', 30);
                $targetDate = now()->addDays($hari)->toDateString();
                $query->whereNotNull('tanggal_kedaluwarsa')
                    ->whereBetween('tanggal_kedaluwarsa', [$today, $targetDate]);
            }
        }

        // 4. Filter tahun dan rentang tahun sertifikasi
        if ($tahun = $request->query('tahun_sertifikasi', $request->query('tahun'))) {
            if (is_numeric($tahun) && strlen((string) $tahun) === 4) {
                $query->whereYear('tahun_sertifikasi', $tahun);
            } else {
                $query->where('tahun_sertifikasi', $tahun);
            }
        }
        if ($tahunMulai = $request->query('tahun_mulai')) {
            if (is_numeric($tahunMulai) && strlen((string) $tahunMulai) === 4) {
                $query->whereYear('tahun_sertifikasi', '>=', $tahunMulai);
            } else {
                $query->where('tahun_sertifikasi', '>=', $tahunMulai);
            }
        }
        if ($tahunSelesai = $request->query('tahun_selesai')) {
            if (is_numeric($tahunSelesai) && strlen((string) $tahunSelesai) === 4) {
                $query->whereYear('tahun_sertifikasi', '<=', $tahunSelesai);
            } else {
                $query->where('tahun_sertifikasi', '<=', $tahunSelesai);
            }
        }

        // 5. Filter spesifik berdasarkan foreign key & status eksplisit
        foreach (['id_dosen', 'id_tendik', 'status_sertifikasi'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $sertifikasi = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data sertifikasi berhasil diambil.', $sertifikasi);
    }

    /**
     * Trigger sinkronisasi data sertifikasi ke profil Dosen dan Tendik secara massal.
     */
    public function syncProfil(Request $request): JsonResponse
    {
        $hasil = Sertifikasi::syncAllProfil();

        return $this->respond(true, 200, 'Sinkronisasi sertifikasi ke profil Dosen & Tendik berhasil.', $hasil);
    }

    /**
     * Tambah data sertifikasi baru beserta evidence pendukung.
     */
    public function store(Request $request): JsonResponse
    {
        // Normalisasi: dukung url_drive maupun url_sertifikasi secara fleksibel
        if ($request->filled('url_drive') && ! $request->filled('url_sertifikasi')) {
            $request->merge(['url_sertifikasi' => $request->input('url_drive')]);
        } elseif ($request->filled('url_sertifikasi') && ! $request->filled('url_drive')) {
            $request->merge(['url_drive' => $request->input('url_sertifikasi')]);
        }

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        if (empty($validated['status_sertifikasi'])) {
            $validated['status_sertifikasi'] = 'Aktif';
        }

        try {
            $sertifikasi = DB::transaction(function () use ($request, $validated) {
                $sertifikasiData = collect($validated)->except(['evidence_file', 'url_drive'])->toArray();
                if (empty($sertifikasiData['url_sertifikasi']) && ! empty($validated['url_drive'])) {
                    $sertifikasiData['url_sertifikasi'] = $validated['url_drive'];
                }
                if (! Schema::hasColumn('sertifikasi', 'url_sertifikasi')) {
                    unset($sertifikasiData['url_sertifikasi']);
                }

                $sertifikasi = Sertifikasi::create($sertifikasiData);

                // Lampirkan dokumen evidence jika ada (Fitur 9 Flowchart)
                // Baik melalui upload file, url_drive, maupun url_sertifikasi
                $urlBukti = ! empty($validated['url_sertifikasi']) ? $validated['url_sertifikasi'] : ($validated['url_drive'] ?? null);
                if ($request->hasFile('evidence_file') || ! empty($urlBukti)) {
                    $pathFile = null;
                    $namaFile = null;

                    if ($request->hasFile('evidence_file')) {
                        $file = $request->file('evidence_file');
                        $namaFile = $file->getClientOriginalName();
                        $pathFile = $file->store('evidence/sertifikasi', 'public');
                    }

                    $evidenceData = [
                        'id_sertifikasi' => $sertifikasi->id_sertifikasi,
                        'jenis_dokumen' => 'Sertifikat',
                        'nama_file' => $namaFile ?? ($sertifikasi->jenis_sertifikasi ?: 'Bukti Sertifikasi'),
                        'path_file' => $pathFile,
                        'tanggal_upload' => now()->toDateString(),
                        'status_validasi' => 'Menunggu Validasi',
                    ];

                    if (Schema::hasColumn('evidence', 'url_drive')) {
                        $evidenceData['url_drive'] = $validated['url_drive'] ?? $urlBukti;
                    }
                    if (Schema::hasColumn('evidence', 'url_evidence')) {
                        $evidenceData['url_evidence'] = $urlBukti;
                    }
                    if (! Schema::hasColumn('evidence', 'url_drive') && ! Schema::hasColumn('evidence', 'url_evidence') && empty($pathFile) && ! empty($urlBukti)) {
                        $evidenceData['nama_file'] = $urlBukti;
                    }

                    Evidence::create($evidenceData);
                }

                return $sertifikasi;
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data sertifikasi: '.$e->getMessage());
        }

        return $this->respond(true, 201, 'Data sertifikasi berhasil ditambahkan.', $sertifikasi->load($this->relations));
    }

    /**
     * Ambil detail satu sertifikasi.
     */
    public function show(int $id): JsonResponse
    {
        $sertifikasi = Sertifikasi::with($this->relations)->find($id);

        if (! $sertifikasi) {
            return $this->respond(false, 404, 'Data sertifikasi tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail sertifikasi berhasil diambil.', $sertifikasi);
    }

    /**
     * Update data sertifikasi.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sertifikasi = Sertifikasi::find($id);

        if (! $sertifikasi) {
            return $this->respond(false, 404, 'Data sertifikasi tidak ditemukan.');
        }

        // Normalisasi: dukung url_drive maupun url_sertifikasi secara fleksibel
        if ($request->filled('url_drive') && ! $request->filled('url_sertifikasi')) {
            $request->merge(['url_sertifikasi' => $request->input('url_drive')]);
        } elseif ($request->filled('url_sertifikasi') && ! $request->filled('url_drive')) {
            $request->merge(['url_drive' => $request->input('url_sertifikasi')]);
        }

        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($sertifikasi, $validated) {
                $sertifikasiData = collect($validated)->except(['evidence_file', 'url_drive'])->toArray();
                if (empty($sertifikasiData['url_sertifikasi']) && ! empty($validated['url_drive'])) {
                    $sertifikasiData['url_sertifikasi'] = $validated['url_drive'];
                }
                if (! Schema::hasColumn('sertifikasi', 'url_sertifikasi')) {
                    unset($sertifikasiData['url_sertifikasi']);
                }

                $sertifikasi->update($sertifikasiData);

                $urlBukti = ! empty($validated['url_sertifikasi']) ? $validated['url_sertifikasi'] : ($validated['url_drive'] ?? null);
                if ($urlBukti) {
                    $evidence = $sertifikasi->evidence()->first();
                    if ($evidence) {
                        $updateData = [];
                        if (Schema::hasColumn('evidence', 'url_drive')) {
                            $updateData['url_drive'] = $validated['url_drive'] ?? $evidence->url_drive ?? $urlBukti;
                        }
                        if (Schema::hasColumn('evidence', 'url_evidence')) {
                            $updateData['url_evidence'] = $urlBukti;
                        }
                        if (! Schema::hasColumn('evidence', 'url_drive') && ! Schema::hasColumn('evidence', 'url_evidence') && empty($evidence->path_file)) {
                            $updateData['nama_file'] = $urlBukti;
                        }

                        if (! empty($updateData)) {
                            $evidence->update($updateData);
                        }
                    } else {
                        $evidenceData = [
                            'id_sertifikasi' => $sertifikasi->id_sertifikasi,
                            'jenis_dokumen' => 'Sertifikat',
                            'nama_file' => $sertifikasi->jenis_sertifikasi ?: 'Bukti Sertifikasi',
                            'path_file' => null,
                            'tanggal_upload' => now()->toDateString(),
                            'status_validasi' => 'Menunggu Validasi',
                        ];

                        if (Schema::hasColumn('evidence', 'url_drive')) {
                            $evidenceData['url_drive'] = $validated['url_drive'] ?? $urlBukti;
                        }
                        if (Schema::hasColumn('evidence', 'url_evidence')) {
                            $evidenceData['url_evidence'] = $urlBukti;
                        }
                        if (! Schema::hasColumn('evidence', 'url_drive') && ! Schema::hasColumn('evidence', 'url_evidence') && ! empty($urlBukti)) {
                            $evidenceData['nama_file'] = $urlBukti;
                        }

                        Evidence::create($evidenceData);
                    }
                }
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui data sertifikasi: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data sertifikasi berhasil diperbarui.', $sertifikasi->fresh($this->relations));
    }

    /**
     * Hapus data sertifikasi.
     */
    public function destroy(int $id): JsonResponse
    {
        $sertifikasi = Sertifikasi::find($id);

        if (! $sertifikasi) {
            return $this->respond(false, 404, 'Data sertifikasi tidak ditemukan.');
        }

        // Cek keterkaitan ke evidence yang sudah divalidasi (Fitur 9 Flowchart: "Luaran Masih Dipakai")
        if ($sertifikasi->evidence()->where('status_validasi', 'Valid')->exists()) {
            return $this->respond(false, 422, 'Luaran Masih Dipakai: Data sertifikasi tidak dapat dihapus karena masih terkait dengan dokumen evidence yang telah divalidasi.');
        }

        try {
            $sertifikasi->evidence()->delete();
            $sertifikasi->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data sertifikasi: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data sertifikasi berhasil dihapus.');
    }
}
