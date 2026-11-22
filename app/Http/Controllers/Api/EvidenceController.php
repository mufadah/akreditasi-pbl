<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\EvidenceHistory;
use App\Models\Notifikasi;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EvidenceController extends Controller
{
    // Relasi yang dimuat untuk mencegah N+1 query
    private array $relations = [
        'kerjaSama',
        'kegiatan',
        'luaran',
        'sertifikasi',
        'history',
        'validator',
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

    // Ambil daftar repository evidence dengan filter dan pencarian
    public function index(Request $request): JsonResponse
    {
        $query = Evidence::with($this->relations);

        // Filter pencarian nama file / judul dokumen atau jenis dokumen
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                if (Schema::hasColumn('evidence', 'judul_file')) {
                    $q->where('judul_file', 'like', "%{$search}%")
                        ->orWhere('jenis_dokumen', 'like', "%{$search}%")
                        ->orWhere('nama_file', 'like', "%{$search}%");
                } else {
                    $q->where('nama_file', 'like', "%{$search}%")
                        ->orWhere('jenis_dokumen', 'like', "%{$search}%");
                }
            });
        }

        // Filter jenis dokumen
        if ($jenis = $request->query('jenis_dokumen')) {
            $query->where('jenis_dokumen', 'like', "%{$jenis}%");
        }

        // Filter status validasi (Menunggu Validasi, Valid, Ditolak, Perlu Perbaikan)
        if ($status = $request->query('status_validasi')) {
            $query->where('status_validasi', $status);
        }

        // Filter entitas terkait (foreign key)
        foreach (['id_kegiatan', 'id_luaran', 'id_sertifikasi'] as $fk) {
            if ($request->filled($fk)) {
                $query->where($fk, $request->query($fk));
            }
        }

        if ($idKerjaSama = $request->query('id_kerja_sama', $request->query('id_kerjasama'))) {
            $query->where(function ($q) use ($idKerjaSama) {
                $q->where('id_kerja_sama', $idKerjaSama)
                    ->orWhere('id_kerjasama', $idKerjaSama);
            });
        }

        $evidence = $query->latest('id_evidence')->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data repository evidence berhasil diambil.', $evidence);
    }

    // Simpan data metadata evidence dan link Google Drive baru
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'judul_file' => ['nullable', 'string', 'max:255'],
            'jenis_dokumen' => ['required', 'string', 'max:255'],
            'tanggal_upload' => ['nullable', 'date'],
            'nama_file' => ['nullable', 'string', 'max:500'],
            'url_drive' => ['nullable', 'string', 'max:500'],
            'id_kegiatan' => ['nullable', 'integer', Rule::exists('kegiatan', 'id_kegiatan')],
            'id_luaran' => ['nullable', 'integer', Rule::exists('luaran', 'id_luaran')],
            'id_sertifikasi' => ['nullable', 'integer', Rule::exists('sertifikasi', 'id_sertifikasi')],
            'id_kerjasama' => ['nullable', 'integer', Rule::exists('kerja_sama', 'id_kerja_sama')],
            'id_kerja_sama' => ['nullable', 'integer', Rule::exists('kerja_sama', 'id_kerja_sama')],
            'konfirmasi_lanjut' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        // 1. Ekstraksi dan Validasi URL Google Drive
        $targetUrl = null;
        if (! empty($validated['url_drive']) && filter_var($validated['url_drive'], FILTER_VALIDATE_URL)) {
            $targetUrl = trim($validated['url_drive']);
        } elseif (! empty($validated['nama_file']) && filter_var($validated['nama_file'], FILTER_VALIDATE_URL)) {
            $targetUrl = trim($validated['nama_file']);
        }

        if (empty($targetUrl)) {
            return $this->respond(false, 422, 'Tautan Google Drive wajib diisi.', [
                'nama_file' => ['Tautan Google Drive dokumen evidence wajib disertakan.'],
            ]);
        }

        // URL hanya boleh berasal dari https://drive.google.com atau https://docs.google.com
        if (! preg_match('/^https:\/\/(drive\.google\.com|docs\.google\.com)\/.+/', $targetUrl)) {
            return $this->respond(false, 422, 'Tautan dokumen harus berupa URL resmi Google Drive atau Google Docs (diawali https://drive.google.com atau https://docs.google.com).', [
                'nama_file' => ['Format URL Google Drive tidak valid.'],
            ]);
        }

        // 2. Ekstraksi Judul Dokumen (Nama File yang ditampilkan)
        $judulFile = null;
        if (! empty($validated['judul_file'])) {
            $judulFile = trim($validated['judul_file']);
        } elseif (! empty($validated['nama_file']) && ! filter_var($validated['nama_file'], FILTER_VALIDATE_URL)) {
            $judulFile = trim($validated['nama_file']);
        } else {
            $judulFile = 'Dokumen '.$validated['jenis_dokumen'];
        }

        // 3. Deteksi Duplikasi URL Google Drive
        $dupQuery = Evidence::where(function ($q) use ($targetUrl) {
            $q->where('nama_file', $targetUrl);
            if (Schema::hasColumn('evidence', 'url_drive')) {
                $q->orWhere('url_drive', $targetUrl);
            }
            if (Schema::hasColumn('evidence', 'url_evidence')) {
                $q->orWhere('url_evidence', $targetUrl);
            }
        });

        if ($dupQuery->exists() && ! $request->boolean('konfirmasi_lanjut')) {
            return $this->respond(false, 409, 'Peringatan: Link ini sudah pernah digunakan sebelumnya. Kirim parameter "konfirmasi_lanjut": true jika ingin tetap menyimpan.', [
                'is_duplicate' => true,
            ]);
        }

        // 4. Normalisasi relasi kerja sama
        $idKerjaSama = $validated['id_kerja_sama'] ?? $validated['id_kerjasama'] ?? null;

        try {
            $evidence = DB::transaction(function () use ($validated, $judulFile, $targetUrl, $idKerjaSama) {
                $createData = [
                    'jenis_dokumen' => $validated['jenis_dokumen'],
                    'nama_file' => $targetUrl, // Tetap kompatibel dengan kontrak lama
                    'url_drive' => $targetUrl,
                    'tanggal_upload' => $validated['tanggal_upload'] ?? now()->toDateString(),
                    'status_validasi' => 'Menunggu Validasi',
                    'id_kegiatan' => $validated['id_kegiatan'] ?? null,
                    'id_luaran' => $validated['id_luaran'] ?? null,
                    'id_sertifikasi' => $validated['id_sertifikasi'] ?? null,
                    'id_kerja_sama' => $idKerjaSama,
                    'id_kerjasama' => $idKerjaSama,
                ];

                if (Schema::hasColumn('evidence', 'judul_file')) {
                    $createData['judul_file'] = $judulFile;
                }
                if (Schema::hasColumn('evidence', 'url_evidence')) {
                    $createData['url_evidence'] = $targetUrl;
                }

                return Evidence::create($createData);
            });

            AuditLogService::log('CREATE', 'Evidence', $evidence->id_evidence, null, $evidence->toArray());
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data evidence: '.$e->getMessage());
        }

        return $this->respond(true, 201, 'Link evidence berhasil disimpan dan menunggu validasi.', $evidence->load($this->relations));
    }

    // Ambil detail satu record evidence
    public function show(int $id): JsonResponse
    {
        $evidence = Evidence::with($this->relations)->find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail evidence berhasil diambil.', $evidence);
    }

    // Update metadata evidence secara lengkap dan pergantian URL Google Drive
    public function update(Request $request, int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'judul_file' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nama_file' => ['sometimes', 'nullable', 'string', 'max:500'],
            'url_drive' => ['sometimes', 'nullable', 'string', 'max:500'],
            'jenis_dokumen' => ['sometimes', 'required', 'string', 'max:255'],
            'tanggal_upload' => ['sometimes', 'nullable', 'date'],
            'alasan_perubahan' => ['nullable', 'string', 'max:255'],
            'id_kegiatan' => ['sometimes', 'nullable', 'integer', Rule::exists('kegiatan', 'id_kegiatan')],
            'id_luaran' => ['sometimes', 'nullable', 'integer', Rule::exists('luaran', 'id_luaran')],
            'id_sertifikasi' => ['sometimes', 'nullable', 'integer', Rule::exists('sertifikasi', 'id_sertifikasi')],
            'id_kerjasama' => ['sometimes', 'nullable', 'integer', Rule::exists('kerja_sama', 'id_kerja_sama')],
            'id_kerja_sama' => ['sometimes', 'nullable', 'integer', Rule::exists('kerja_sama', 'id_kerja_sama')],
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        // 1. Tentukan apakah URL Google Drive diganti
        $newUrl = null;
        if (! empty($validated['url_drive']) && filter_var($validated['url_drive'], FILTER_VALIDATE_URL)) {
            $newUrl = trim($validated['url_drive']);
        } elseif (! empty($validated['nama_file']) && filter_var($validated['nama_file'], FILTER_VALIDATE_URL)) {
            $newUrl = trim($validated['nama_file']);
        }

        $currentUrl = $evidence->url_drive ?: (filter_var($evidence->nama_file, FILTER_VALIDATE_URL) ? $evidence->nama_file : $evidence->url_evidence);
        $isUrlChanged = (! empty($newUrl) && $newUrl !== $currentUrl);

        if ($isUrlChanged) {
            // Validasi format URL Google Drive baru
            if (! preg_match('/^https:\/\/(drive\.google\.com|docs\.google\.com)\/.+/', $newUrl)) {
                return $this->respond(false, 422, 'Tautan Google Drive baru harus diawali dengan https://drive.google.com atau https://docs.google.com.', [
                    'url_drive' => ['Format URL Google Drive baru tidak valid.'],
                ]);
            }

            // Alasan perubahan wajib diisi jika URL Google Drive diganti
            if (empty(trim((string) $request->input('alasan_perubahan')))) {
                return $this->respond(false, 422, 'Alasan perubahan link wajib diisi jika tautan Google Drive diperbarui.', [
                    'alasan_perubahan' => ['Alasan perubahan wajib disertakan saat mengganti link Google Drive.'],
                ]);
            }
        }

        // 2. Siapkan data update metadata
        $updateData = [];

        if (array_key_exists('jenis_dokumen', $validated)) {
            $updateData['jenis_dokumen'] = $validated['jenis_dokumen'];
        }

        if (array_key_exists('tanggal_upload', $validated) && ! empty($validated['tanggal_upload'])) {
            $updateData['tanggal_upload'] = $validated['tanggal_upload'];
        }

        if (array_key_exists('judul_file', $validated)) {
            if (Schema::hasColumn('evidence', 'judul_file')) {
                $updateData['judul_file'] = $validated['judul_file'];
            }
        } elseif (array_key_exists('nama_file', $validated) && ! filter_var($validated['nama_file'], FILTER_VALIDATE_URL)) {
            if (Schema::hasColumn('evidence', 'judul_file')) {
                $updateData['judul_file'] = $validated['nama_file'];
            }
        }

        // Normalisasi foreign key kerja sama
        if (array_key_exists('id_kerjasama', $validated) || array_key_exists('id_kerja_sama', $validated)) {
            $idKerjaSama = $validated['id_kerja_sama'] ?? $validated['id_kerjasama'] ?? null;
            $updateData['id_kerja_sama'] = $idKerjaSama;
            $updateData['id_kerjasama'] = $idKerjaSama;
        }

        foreach (['id_kegiatan', 'id_luaran', 'id_sertifikasi'] as $fk) {
            if (array_key_exists($fk, $validated)) {
                $updateData[$fk] = $validated[$fk];
            }
        }

        // Pengguna tidak diizinkan mengubah status_validasi melalui form Edit biasa
        unset($updateData['status_validasi'], $updateData['catatan_validasi'], $updateData['divalidasi_oleh'], $updateData['tanggal_validasi']);

        $authUser = $request->input('auth_user');
        $userId = is_array($authUser) ? ($authUser['id'] ?? null) : ($authUser->id ?? auth()->id());

        try {
            DB::transaction(function () use ($evidence, $updateData, $isUrlChanged, $newUrl, $currentUrl, $request, $userId) {
                // Jika URL Google Drive berubah, simpan riwayat ke evidence_histories & reset status ke Menunggu Validasi
                if ($isUrlChanged) {
                    EvidenceHistory::create([
                        'evidence_id' => $evidence->id_evidence,
                        'url_lama' => $currentUrl ?? '',
                        'url_baru' => $newUrl,
                        'alasan_perubahan' => $request->input('alasan_perubahan'),
                        'diubah_oleh' => $userId,
                    ]);

                    $updateData['nama_file'] = $newUrl;
                    if (Schema::hasColumn('evidence', 'url_drive')) {
                        $updateData['url_drive'] = $newUrl;
                    }
                    if (Schema::hasColumn('evidence', 'url_evidence')) {
                        $updateData['url_evidence'] = $newUrl;
                    }

                    $updateData['status_validasi'] = 'Menunggu Validasi';
                    $updateData['catatan_validasi'] = null;
                    $updateData['divalidasi_oleh'] = null;
                    $updateData['tanggal_validasi'] = null;
                }

                $evidence->update($updateData);
            });

            AuditLogService::log('UPDATE', 'Evidence', $evidence->id_evidence, null, $evidence->toArray());
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui evidence: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data evidence berhasil diperbarui.', $evidence->fresh($this->relations));
    }

    // Endpoint khusus pergantian link Google Drive dan pengarsipan riwayat
    public function gantiLink(Request $request, int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'url_drive_baru' => [
                'required',
                'url',
                'regex:/^https:\/\/(drive\.google\.com|docs\.google\.com)\/.+/',
            ],
            'alasan_perubahan' => 'required|string|max:255',
        ], [
            'url_drive_baru.regex' => 'Format URL Google Drive baru harus diawali dengan https://drive.google.com atau https://docs.google.com.',
            'alasan_perubahan.required' => 'Alasan perubahan link wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $oldUrl = $evidence->url_drive ?: (filter_var($evidence->nama_file, FILTER_VALIDATE_URL) ? $evidence->nama_file : $evidence->url_evidence);
        $newUrl = trim($request->input('url_drive_baru'));

        // Jika URL sama persis, tidak perlu membuat riwayat baru
        if ($oldUrl === $newUrl) {
            return $this->respond(false, 422, 'URL Google Drive baru sama dengan URL saat ini.', [
                'url_drive_baru' => ['URL Google Drive baru tidak boleh sama dengan URL yang sedang aktif.'],
            ]);
        }

        $authUser = $request->input('auth_user');
        $userId = is_array($authUser) ? ($authUser['id'] ?? null) : ($authUser->id ?? auth()->id());

        try {
            DB::transaction(function () use ($evidence, $oldUrl, $newUrl, $request, $userId) {
                // 1. Simpan riwayat perubahan ke evidence_histories
                EvidenceHistory::create([
                    'evidence_id' => $evidence->id_evidence,
                    'url_lama' => $oldUrl ?? '',
                    'url_baru' => $newUrl,
                    'alasan_perubahan' => $request->input('alasan_perubahan'),
                    'diubah_oleh' => $userId,
                ]);

                // 2. Perbarui link dan reset status ke "Menunggu Validasi"
                $updateData = [
                    'nama_file' => $newUrl,
                    'status_validasi' => 'Menunggu Validasi',
                    'catatan_validasi' => null,
                    'divalidasi_oleh' => null,
                    'tanggal_validasi' => null,
                ];

                if (Schema::hasColumn('evidence', 'url_drive')) {
                    $updateData['url_drive'] = $newUrl;
                }
                if (Schema::hasColumn('evidence', 'url_evidence')) {
                    $updateData['url_evidence'] = $newUrl;
                }

                $evidence->update($updateData);
            });

            AuditLogService::log('UPDATE_LINK', 'Evidence', $evidence->id_evidence, ['old' => $oldUrl], ['new' => $newUrl]);
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal mengganti link evidence: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Link Google Drive berhasil diganti, link lama diarsipkan, dan status di-reset ke Menunggu Validasi.', $evidence->fresh($this->relations));
    }

    // Workflow Validasi Evidence oleh Kaprodi / Asesor / Validator
    public function validasi(Request $request, int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'status_validasi' => 'required|in:Valid,Ditolak,Perlu Perbaikan',
            'catatan_validasi' => 'required_if:status_validasi,Ditolak,Perlu Perbaikan|nullable|string',
        ], [
            'status_validasi.in' => 'Status validasi hanya boleh bernilai: Valid, Ditolak, atau Perlu Perbaikan.',
            'catatan_validasi.required_if' => 'Catatan validasi wajib diisi jika status Ditolak atau Perlu Perbaikan.',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $status = $request->input('status_validasi');
        $catatan = $request->input('catatan_validasi');

        $authUser = $request->input('auth_user');
        $userId = is_array($authUser) ? ($authUser['id'] ?? null) : ($authUser->id ?? auth()->id());

        $evidence->update([
            'status_validasi' => $status,
            'catatan_validasi' => $catatan,
            'divalidasi_oleh' => $userId,
            'tanggal_validasi' => now(),
        ]);

        // Catatan: Tabel evidence saat ini belum memiliki kolom identitas pengguna pengunggah (user_id/diunggah_oleh).
        // Sesuai batasan, notifikasi ditunda dan tidak dikirim kepada pengguna yang belum dapat dipastikan identitasnya.

        AuditLogService::log('VALIDATE', 'Evidence', $evidence->id_evidence, null, [
            'status_validasi' => $status,
            'catatan_validasi' => $catatan,
        ]);

        return $this->respond(true, 200, "Evidence berhasil divalidasi dengan status: {$status}.", $evidence->fresh($this->relations));
    }

    // Hapus record evidence
    public function destroy(int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        try {
            DB::transaction(function () use ($evidence, $id) {
                $oldData = $evidence->toArray();
                $evidence->delete();

                AuditLogService::log('DELETE', 'Evidence', $id, $oldData, null);
            });
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data evidence: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data evidence berhasil dihapus.');
    }
}
