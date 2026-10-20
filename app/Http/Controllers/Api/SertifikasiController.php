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
            'url_sertifikasi' => $isUpdate
                ? ['sometimes', 'nullable', 'string', 'max:2048']
                : ['required_without_all:evidence_file,file,file_sertifikasi', 'nullable', 'string', 'max:2048'],
            'evidence_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'file_sertifikasi' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'url_drive' => ['nullable', 'string', 'max:2048'],
        ];
    }

    private function normalizeTahunSertifikasi(int|string $value): string
    {
        $value = (string) $value;

        if (preg_match('/^\d{4}$/', $value) === 1) {
            return $value.'-01-01';
        }

        return $value;
    }

    /**
     * Normalisasi alias input parameter request (misal: tenaga_kependidikan_id -> id_tendik, penerbit -> lembaga_penerbit).
     */
    private function normalizeRequestData(Request $request): array
    {
        $data = $request->all();

        // 1. Alias ID Tenaga Kependidikan
        if (empty($data['id_tendik'])) {
            if (! empty($data['tenaga_kependidikan_id'])) {
                $data['id_tendik'] = $data['tenaga_kependidikan_id'];
            } elseif (! empty($data['tendik_id'])) {
                $data['id_tendik'] = $data['tendik_id'];
            } elseif (! empty($data['id_tenaga_kependidikan'])) {
                $data['id_tendik'] = $data['id_tenaga_kependidikan'];
            }
        }
        if (isset($data['id_tendik']) && is_numeric($data['id_tendik'])) {
            $data['id_tendik'] = (int) $data['id_tendik'];
        }

        // 2. Alias ID Dosen
        if (empty($data['id_dosen'])) {
            if (! empty($data['dosen_id'])) {
                $data['id_dosen'] = $data['dosen_id'];
            }
        }
        if (isset($data['id_dosen']) && is_numeric($data['id_dosen'])) {
            $data['id_dosen'] = (int) $data['id_dosen'];
        }

        // 3. Alias Lembaga Penerbit
        if (empty($data['lembaga_penerbit'])) {
            if (! empty($data['penerbit'])) {
                $data['lembaga_penerbit'] = $data['penerbit'];
            }
        }

        // 4. Alias Jenis Sertifikasi / Nama Sertifikasi
        if (empty($data['jenis_sertifikasi'])) {
            if (! empty($data['nama_sertifikasi'])) {
                $data['jenis_sertifikasi'] = $data['nama_sertifikasi'];
            }
        }

        // 5. Alias Nomor Sertifikat
        if (empty($data['nomor_sertifikat'])) {
            if (! empty($data['no_sertifikat'])) {
                $data['nomor_sertifikat'] = $data['no_sertifikat'];
            }
        }

        // 6. Alias Nomor SK
        if (empty($data['nomor_sk'])) {
            if (! empty($data['no_sk'])) {
                $data['nomor_sk'] = $data['no_sk'];
            }
        }

        // 7. Alias Nomor Registrasi Pendidik
        if (empty($data['nomor_registrasi_pendidik'])) {
            if (! empty($data['no_registrasi_pendidik'])) {
                $data['nomor_registrasi_pendidik'] = $data['no_registrasi_pendidik'];
            }
        }

        // 8. Alias URL Sertifikasi
        if (empty($data['url_sertifikasi'])) {
            if (! empty($data['link_sertifikasi'])) {
                $data['url_sertifikasi'] = $data['link_sertifikasi'];
            } elseif (! empty($data['url_drive'])) {
                $data['url_sertifikasi'] = $data['url_drive'];
            } elseif (! empty($data['url'])) {
                $data['url_sertifikasi'] = $data['url'];
            }
        }

        // 9. Jika url_drive belum terisi namun url_sertifikasi ada
        if (empty($data['url_drive']) && ! empty($data['url_sertifikasi'])) {
            $data['url_drive'] = $data['url_sertifikasi'];
        }

        return $data;
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
                        $qt->where('nama', 'like', "%{$search}%")
                            ->orWhere('nip', 'like', "%{$search}%");
                    });
            });
        }

        // 2. Filter tipe pemilik (dosen vs tendik)
        if ($tipePemilik = $request->query('tipe_pemilik')) {
            if (strtolower($tipePemilik) === 'dosen') {
                $query->whereNotNull('id_dosen')->whereNull('id_tendik');
            } elseif (strtolower($tipePemilik) === 'tendik') {
                $query->whereNotNull('id_tendik')->whereNull('id_dosen');
            }
        }

        // 3. Filter status masa berlaku (aktif, kedaluwarsa, segera_berakhir)
        if ($statusMasa = $request->query('status_masa_berlaku')) {
            $today = now()->toDateString();
            $thresholdDays = (int) $request->query('hari_kedaluwarsa', 30);
            $thresholdDate = now()->addDays($thresholdDays)->toDateString();

            if ($statusMasa === 'aktif') {
                $query->where(function ($q) use ($today) {
                    $q->whereNull('tanggal_kedaluwarsa')
                        ->orWhere('tanggal_kedaluwarsa', '>=', $today);
                });
            } elseif ($statusMasa === 'kedaluwarsa') {
                $query->whereNotNull('tanggal_kedaluwarsa')
                    ->where('tanggal_kedaluwarsa', '<', $today);
            } elseif ($statusMasa === 'segera_berakhir') {
                $query->whereNotNull('tanggal_kedaluwarsa')
                    ->whereBetween('tanggal_kedaluwarsa', [$today, $thresholdDate]);
            }
        }

        // 4. Filter tahun sertifikasi spesifik atau rentang tahun
        if ($tahun = $request->query('tahun_sertifikasi')) {
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
        $data = $this->normalizeRequestData($request);
        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();
        $validated['tahun_sertifikasi'] = $this->normalizeTahunSertifikasi($validated['tahun_sertifikasi']);

        if (empty($validated['status_sertifikasi'])) {
            $validated['status_sertifikasi'] = 'Aktif';
        }

        try {
            $hasColumn = method_exists(Sertifikasi::class, 'hasUrlSertifikasiColumn')
                ? Sertifikasi::hasUrlSertifikasiColumn()
                : Schema::hasColumn('sertifikasi', 'url_sertifikasi');

            $sertifikasi = DB::transaction(function () use ($request, $validated, $hasColumn) {
                $file = $request->file('evidence_file') ?? $request->file('file') ?? $request->file('file_sertifikasi');
                $pathFile = null;
                $namaFile = null;

                if ($file) {
                    $namaFile = $file->getClientOriginalName();
                    $pathFile = $file->store('evidence/sertifikasi', 'public');
                    if (empty($validated['url_sertifikasi'])) {
                        $validated['url_sertifikasi'] = url('storage/'.$pathFile);
                    }
                }

                $urlBukti = ! empty($validated['url_sertifikasi']) ? $validated['url_sertifikasi'] : ($validated['url_drive'] ?? null);

                $createData = collect($validated)->except(['evidence_file', 'file', 'file_sertifikasi', 'url_drive'])->toArray();
                if (! $hasColumn) {
                    unset($createData['url_sertifikasi']);
                }

                $sertifikasi = Sertifikasi::create($createData);

                if (! empty($urlBukti)) {
                    $sertifikasi->setAttribute('url_sertifikasi', $urlBukti);
                }

                // Lampirkan dokumen evidence jika ada (Fitur 9 Flowchart)
                // Baik melalui upload file, url_drive, maupun url_sertifikasi
                if ($pathFile || ! empty($urlBukti)) {
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

        $data = $this->normalizeRequestData($request);
        $validator = Validator::make($data, $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();
        if (array_key_exists('tahun_sertifikasi', $validated)) {
            $validated['tahun_sertifikasi'] = $this->normalizeTahunSertifikasi($validated['tahun_sertifikasi']);
        }

        try {
            $hasColumn = method_exists(Sertifikasi::class, 'hasUrlSertifikasiColumn')
                ? Sertifikasi::hasUrlSertifikasiColumn()
                : Schema::hasColumn('sertifikasi', 'url_sertifikasi');

            DB::transaction(function () use ($request, $sertifikasi, $validated, $hasColumn) {
                $file = $request->file('evidence_file') ?? $request->file('file') ?? $request->file('file_sertifikasi');
                $pathFile = null;
                $namaFile = null;

                if ($file) {
                    $namaFile = $file->getClientOriginalName();
                    $pathFile = $file->store('evidence/sertifikasi', 'public');
                    if (empty($validated['url_sertifikasi'])) {
                        $validated['url_sertifikasi'] = url('storage/'.$pathFile);
                    }
                }

                $urlBukti = ! empty($validated['url_sertifikasi']) ? $validated['url_sertifikasi'] : ($validated['url_drive'] ?? null);

                $updateData = collect($validated)->except(['evidence_file', 'file', 'file_sertifikasi', 'url_drive'])->toArray();
                if (! $hasColumn) {
                    unset($updateData['url_sertifikasi']);
                }

                $sertifikasi->update($updateData);

                if (! empty($urlBukti)) {
                    $sertifikasi->setAttribute('url_sertifikasi', $urlBukti);
                }

                if ($pathFile || ! empty($urlBukti)) {
                    $evidence = $sertifikasi->evidence()->first();
                    if ($evidence) {
                        $evidenceData = [];
                        if (Schema::hasColumn('evidence', 'url_evidence')) {
                            $evidenceData['url_evidence'] = $urlBukti ?: $evidence->url_evidence;
                        }
                        if (Schema::hasColumn('evidence', 'url_drive')) {
                            $evidenceData['url_drive'] = $validated['url_drive'] ?? $evidence->url_drive ?? $urlBukti;
                        }
                        if ($pathFile) {
                            $evidenceData['path_file'] = $pathFile;
                            $evidenceData['nama_file'] = $namaFile;
                        } elseif (! Schema::hasColumn('evidence', 'url_drive') && ! Schema::hasColumn('evidence', 'url_evidence') && empty($evidence->path_file)) {
                            $evidenceData['nama_file'] = $urlBukti;
                        }

                        if (! empty($evidenceData)) {
                            $evidence->update($evidenceData);
                        }
                    } else {
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