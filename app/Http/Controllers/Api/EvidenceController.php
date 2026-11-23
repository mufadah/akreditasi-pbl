<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\EvidenceHistory;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EvidenceController extends Controller
{
    // Role yang berwenang mengubah status_validasi
    private const VALIDATOR_ROLES = ['ADMINISTRATOR', 'KAPRODI', 'ASESOR'];

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

    // Ambil role pengguna dari request (JWT middleware sudah menyuntikkan ke request)
    private function getUserRole(Request $request): ?string
    {
        $role = $request->attributes->get('jwt_role');
        if ($role) {
            return strtoupper(trim($role));
        }

        $authUser = $request->input('auth_user');
        if (is_array($authUser)) {
            return strtoupper(trim($authUser['role'] ?? '')) ?: null;
        }
        if (is_object($authUser)) {
            return strtoupper(trim($authUser->role ?? '')) ?: null;
        }

        return null;
    }

    // Ambil daftar repository evidence dengan filter dan pencarian
    public function index(Request $request): JsonResponse
    {
        $query = Evidence::with($this->relations);

        // Filter pencarian pada nama_file (nama dokumen) dan jenis_dokumen
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_file', 'like', "%{$search}%")
                    ->orWhere('jenis_dokumen', 'like', "%{$search}%");
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

    // Simpan data metadata evidence dan tautan bukti baru
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nama_file' => ['required', 'string', 'max:255'],
            'jenis_dokumen' => ['required', 'string', 'max:255'],
            'tanggal_upload' => ['nullable', 'date'],
            'url_drive' => ['nullable', 'url', 'max:2048'],
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

        // nama_file = nama dokumen (teks), url_drive = tautan bukti
        $namaFile = trim($validated['nama_file']);
        $urlBukti = ! empty($validated['url_drive']) ? trim($validated['url_drive']) : null;

        if (empty($urlBukti)) {
            return $this->respond(false, 422, 'Tautan bukti (url_drive) wajib diisi.', [
                'url_drive' => ['Tautan bukti dokumen evidence wajib disertakan.'],
            ]);
        }

        // Deteksi Duplikasi URL (domain-agnostic, hanya cek apakah URL sudah pernah digunakan)
        $dupQuery = Evidence::where(function ($q) use ($urlBukti) {
            if (Schema::hasColumn('evidence', 'url_drive')) {
                $q->where('url_drive', $urlBukti);
            }
            if (Schema::hasColumn('evidence', 'url_evidence')) {
                $q->orWhere('url_evidence', $urlBukti);
            }
        });

        if ($dupQuery->exists() && ! $request->boolean('konfirmasi_lanjut')) {
            return $this->respond(false, 409, 'Peringatan: Tautan ini sudah pernah digunakan sebelumnya. Kirim parameter "konfirmasi_lanjut": true jika ingin tetap menyimpan.', [
                'is_duplicate' => true,
            ]);
        }

        // Normalisasi relasi kerja sama
        $idKerjaSama = $validated['id_kerja_sama'] ?? $validated['id_kerjasama'] ?? null;

        try {
            $evidence = DB::transaction(function () use ($validated, $namaFile, $urlBukti, $idKerjaSama) {
                $createData = [
                    'nama_file' => $namaFile,   // nama dokumen, bukan URL
                    'jenis_dokumen' => $validated['jenis_dokumen'],
                    'tanggal_upload' => $validated['tanggal_upload'] ?? now()->toDateString(),
                    'status_validasi' => 'Menunggu Validasi',
                    'id_kegiatan' => $validated['id_kegiatan'] ?? null,
                    'id_luaran' => $validated['id_luaran'] ?? null,
                    'id_sertifikasi' => $validated['id_sertifikasi'] ?? null,
                    'id_kerja_sama' => $idKerjaSama,
                    'id_kerjasama' => $idKerjaSama,
                ];

                if (Schema::hasColumn('evidence', 'url_drive')) {
                    $createData['url_drive'] = $urlBukti;
                }
                if (Schema::hasColumn('evidence', 'url_evidence')) {
                    $createData['url_evidence'] = $urlBukti;
                }

                return Evidence::create($createData);
            });

            AuditLogService::log('CREATE', 'Evidence', $evidence->id_evidence, null, $evidence->toArray());
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menyimpan data evidence: '.$e->getMessage());
        }

        return $this->respond(true, 201, 'Evidence berhasil disimpan dan menunggu validasi.', $evidence->load($this->relations));
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

    // Update metadata evidence dan/atau tautan bukti secara lengkap
    public function update(Request $request, int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'nama_file' => ['sometimes', 'required', 'string', 'max:255'],
            'jenis_dokumen' => ['sometimes', 'required', 'string', 'max:255'],
            'tanggal_upload' => ['sometimes', 'nullable', 'date'],
            'url_drive' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'alasan_perubahan' => ['nullable', 'string', 'max:255'],
            'status_validasi' => ['sometimes', 'in:Menunggu Validasi,Valid,Ditolak,Perlu Perbaikan'],
            'catatan_validasi' => ['nullable', 'string'],
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

        // Tentukan apakah tautan bukti (url_drive) diganti
        $newUrl = array_key_exists('url_drive', $validated) ? (! empty($validated['url_drive']) ? trim($validated['url_drive']) : null) : null;
        $currentUrl = $evidence->url_drive ?? $evidence->url_evidence ?? null;
        $isUrlChanged = ($newUrl !== null && $newUrl !== $currentUrl);

        // Alasan perubahan wajib diisi jika tautan diganti
        if ($isUrlChanged && empty(trim((string) $request->input('alasan_perubahan')))) {
            return $this->respond(false, 422, 'Alasan perubahan wajib diisi jika tautan bukti diperbarui.', [
                'alasan_perubahan' => ['Alasan perubahan wajib disertakan saat mengganti tautan bukti.'],
            ]);
        }

        // Periksa hak akses untuk mengubah status_validasi
        if (array_key_exists('status_validasi', $validated)) {
            $userRole = $this->getUserRole($request);
            if (! in_array($userRole, self::VALIDATOR_ROLES, true)) {
                return $this->respond(false, 403, 'Forbidden: Hanya Administrator, Kaprodi, atau Asesor yang dapat mengubah status validasi.', [
                    'required_roles' => self::VALIDATOR_ROLES,
                    'current_role' => $userRole,
                ]);
            }
        }

        // Siapkan data update metadata
        $updateData = [];

        if (array_key_exists('nama_file', $validated)) {
            $updateData['nama_file'] = $validated['nama_file'];   // nama dokumen teks
        }

        if (array_key_exists('jenis_dokumen', $validated)) {
            $updateData['jenis_dokumen'] = $validated['jenis_dokumen'];
        }

        if (array_key_exists('tanggal_upload', $validated) && ! empty($validated['tanggal_upload'])) {
            $updateData['tanggal_upload'] = $validated['tanggal_upload'];
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

        // Update status_validasi hanya jika role berwenang (sudah diperiksa di atas)
        if (array_key_exists('status_validasi', $validated)) {
            $updateData['status_validasi'] = $validated['status_validasi'];
            if (array_key_exists('catatan_validasi', $validated)) {
                $updateData['catatan_validasi'] = $validated['catatan_validasi'];
            }
        }

        $authUser = $request->input('auth_user');
        $userId = is_array($authUser) ? ($authUser['id'] ?? null) : ($authUser->id ?? auth()->id());

        try {
            DB::transaction(function () use ($evidence, $updateData, $isUrlChanged, $newUrl, $currentUrl, $request, $userId) {
                // Jika tautan berubah, simpan riwayat & reset status ke Menunggu Validasi
                if ($isUrlChanged) {
                    EvidenceHistory::create([
                        'evidence_id' => $evidence->id_evidence,
                        'url_lama' => $currentUrl ?? '',
                        'url_baru' => $newUrl,
                        'alasan_perubahan' => $request->input('alasan_perubahan'),
                        'diubah_oleh' => $userId,
                    ]);

                    if (Schema::hasColumn('evidence', 'url_drive')) {
                        $updateData['url_drive'] = $newUrl;
                    }
                    if (Schema::hasColumn('evidence', 'url_evidence')) {
                        $updateData['url_evidence'] = $newUrl;
                    }

                    // Hanya reset status jika status_validasi tidak sedang diubah secara eksplisit
                    if (! array_key_exists('status_validasi', $updateData)) {
                        $updateData['status_validasi'] = 'Menunggu Validasi';
                        $updateData['catatan_validasi'] = null;
                        $updateData['divalidasi_oleh'] = null;
                        $updateData['tanggal_validasi'] = null;
                    }
                }

                $evidence->update($updateData);
            });

            AuditLogService::log('UPDATE', 'Evidence', $evidence->id_evidence, null, $evidence->toArray());
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal memperbarui evidence: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data evidence berhasil diperbarui.', $evidence->fresh($this->relations));
    }

    // Endpoint khusus pergantian tautan bukti dan pengarsipan riwayat
    public function gantiLink(Request $request, int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'url_drive_baru' => ['required', 'url', 'max:2048'],
            'alasan_perubahan' => 'required|string|max:255',
        ], [
            'url_drive_baru.required' => 'Tautan bukti baru wajib diisi.',
            'url_drive_baru.url' => 'Tautan bukti baru harus berupa URL yang valid.',
            'alasan_perubahan.required' => 'Alasan perubahan link wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $oldUrl = $evidence->url_drive ?? $evidence->url_evidence ?? null;
        $newUrl = trim($request->input('url_drive_baru'));

        // Jika URL sama persis, tidak perlu membuat riwayat baru
        if ($oldUrl === $newUrl) {
            return $this->respond(false, 422, 'Tautan baru sama dengan tautan saat ini.', [
                'url_drive_baru' => ['Tautan baru tidak boleh sama dengan tautan yang sedang aktif.'],
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

                // 2. Perbarui tautan dan reset status ke "Menunggu Validasi"
                $updateData = [
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
            return $this->respond(false, 500, 'Gagal mengganti tautan evidence: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Tautan bukti berhasil diganti, tautan lama diarsipkan, dan status di-reset ke Menunggu Validasi.', $evidence->fresh($this->relations));
    }

    // Workflow Validasi Evidence oleh Kaprodi / Asesor / Administrator
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

        // Catatan: Tabel evidence saat ini belum memiliki kolom identitas pengunggah (user_id/diunggah_oleh).
        // Notifikasi kepada pengunggah tidak dikirim karena identitasnya tidak dapat dipastikan.

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
