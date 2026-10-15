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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    // Relasi yang dimuat untuk mencegah N+1 query
    private array $relations = [
        'kerjaSama',
        'kegiatan',
        'luaran',
        'sertifikasi',
        'penelitian',
        'pkm',
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

    // Aturan validasi data evidence
    private function rules(bool $isUpdate = false): array
    {
        $fileRules = $isUpdate
            ? ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']
            : ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'file' => $fileRules,
            'nama_file' => ['nullable', 'string', 'max:255'],
            'url_drive' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^https:\/\/(drive\.google\.com|docs\.google\.com)\/.+/',
            ],
            'kode_indikator' => ['nullable', 'string', 'max:50'],
            'jenis_dokumen' => [...$req, 'string', 'max:255'],
            'tanggal_upload' => ['nullable', 'date'],
            'status_validasi' => ['nullable', 'string', 'max:50'],
            'id_kegiatan' => ['nullable', 'integer', Rule::exists('kegiatan', 'id_kegiatan')],
            'id_luaran' => ['nullable', 'integer', Rule::exists('luaran', 'id_luaran')],
            'id_sertifikasi' => ['nullable', 'integer', Rule::exists('sertifikasi', 'id_sertifikasi')],
            'id_penelitian' => ['nullable', 'integer', Rule::exists('penelitian', 'id_penelitian')],
            'id_pkm' => ['nullable', 'integer', Rule::exists('pkm', 'id_pkm')],
            'id_kerjasama' => ['nullable', 'integer', Rule::exists('kerja_sama', 'id_kerja_sama')],
            'id_kerja_sama' => ['nullable', 'integer', Rule::exists('kerja_sama', 'id_kerja_sama')],
        ];
    }

    // Ambil daftar evidence dengan filter pencarian dan relasi (Fitur 11 B Repository)
    public function index(Request $request): JsonResponse
    {
        $query = Evidence::with($this->relations);

        // Filter pencarian nama file atau jenis dokumen
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_file', 'like', "%{$search}%")
                    ->orWhere('jenis_dokumen', 'like', "%{$search}%")
                    ->orWhere('kode_indikator', 'like', "%{$search}%");
            });
        }

        if ($jenis = $request->query('jenis_dokumen')) {
            $query->where('jenis_dokumen', 'like', "%{$jenis}%");
        }

        if ($status = $request->query('status_validasi')) {
            $query->where('status_validasi', $status);
        }

        if ($kode = $request->query('kode_indikator')) {
            $query->where('kode_indikator', $kode);
        }

        if ($tahun = $request->query('tahun')) {
            $query->whereYear('tanggal_upload', $tahun);
        }

        // Filter foreign key
        foreach (['id_kegiatan', 'id_luaran', 'id_sertifikasi', 'id_penelitian', 'id_pkm'] as $fk) {
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

    // Upload berkas fisik atau simpan URL Drive metadata evidence baru
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        // Normalisasi foreign key kerja sama
        $idKerjaSama = $validated['id_kerja_sama'] ?? $validated['id_kerjasama'] ?? null;

        // Cek apakah ada file fisik yang diunggah
        $storedPath = null;
        $originalName = $validated['nama_file'] ?? null;
        $urlDrive = $validated['url_drive'] ?? null;

        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $storedPath = $uploadedFile->store('evidence', 'public');
            $originalName = $uploadedFile->getClientOriginalName();
            $urlDrive = Storage::disk('public')->url($storedPath);
        } elseif (! empty($originalName) && filter_var($originalName, FILTER_VALIDATE_URL)) {
            $urlDrive = $originalName;

            // Cek duplikasi link jika berupa URL
            $isDuplicate = Evidence::where('nama_file', $originalName)->orWhere('url_drive', $originalName)->exists();
            if ($isDuplicate && ! $request->boolean('konfirmasi_lanjut')) {
                return $this->respond(false, 409, 'Peringatan: Link ini sudah pernah digunakan sebelumnya. Kirim parameter "konfirmasi_lanjut": true jika ingin tetap menyimpan.', [
                    'is_duplicate' => true,
                ]);
            }
        }

        try {
            $evidence = DB::transaction(function () use ($validated, $storedPath, $originalName, $urlDrive, $idKerjaSama) {
                return Evidence::create([
                    'nama_file' => $originalName ?? 'Dokumen Evidence',
                    'path_file' => $storedPath,
                    'url_drive' => $urlDrive,
                    'jenis_dokumen' => $validated['jenis_dokumen'],
                    'tanggal_upload' => $validated['tanggal_upload'] ?? now()->toDateString(),
                    'status_validasi' => $validated['status_validasi'] ?? 'Menunggu Validasi',
                    'id_kegiatan' => $validated['id_kegiatan'] ?? null,
                    'id_luaran' => $validated['id_luaran'] ?? null,
                    'id_sertifikasi' => $validated['id_sertifikasi'] ?? null,
                    'id_penelitian' => $validated['id_penelitian'] ?? null,
                    'id_pkm' => $validated['id_pkm'] ?? null,
                    'kode_indikator' => $validated['kode_indikator'] ?? null,
                    'id_kerja_sama' => $idKerjaSama,
                    'id_kerjasama' => $idKerjaSama,
                ]);
            });

            AuditLogService::log('CREATE', 'Evidence', $evidence->id_evidence, null, $evidence->toArray());
        } catch (\Throwable $e) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            return $this->respond(false, 500, 'Gagal menyimpan data evidence: '.$e->getMessage());
        }

        return $this->respond(true, 201, 'Berkas evidence berhasil disimpan.', $evidence->load($this->relations));
    }

    // Ambil detail satu evidence
    public function show(int $id): JsonResponse
    {
        $evidence = Evidence::with($this->relations)->find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail evidence berhasil diambil.', $evidence);
    }

    // Update metadata evidence atau ganti berkas fisik
    public function update(Request $request, int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();
        $oldPath = $evidence->path_file;
        $newPath = null;

        // Tangani unggah berkas baru jika dikirimkan
        if ($request->hasFile('file')) {
            $newFile = $request->file('file');
            $newPath = $newFile->store('evidence', 'public');
            $validated['nama_file'] = $newFile->getClientOriginalName();
            $validated['path_file'] = $newPath;
            $validated['url_drive'] = Storage::disk('public')->url($newPath);
        }

        // Normalisasi foreign key kerja sama
        if (array_key_exists('id_kerjasama', $validated) || array_key_exists('id_kerja_sama', $validated)) {
            $idKerjaSama = $validated['id_kerja_sama'] ?? $validated['id_kerjasama'] ?? null;
            $validated['id_kerja_sama'] = $idKerjaSama;
            $validated['id_kerjasama'] = $idKerjaSama;
        }

        unset($validated['file']);

        try {
            $oldData = $evidence->toArray();
            DB::transaction(function () use ($evidence, $validated) {
                $evidence->update($validated);
            });

            // Hapus berkas fisik lama jika ada berkas baru
            if ($newPath && $oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            AuditLogService::log('UPDATE', 'Evidence', $evidence->id_evidence, $oldData, $evidence->toArray());
        } catch (\Throwable $e) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }

            return $this->respond(false, 500, 'Gagal memperbarui evidence: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data evidence berhasil diperbarui.', $evidence->fresh($this->relations));
    }

    // Hapus record evidence beserta berkas fisik di storage
    public function destroy(int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        try {
            if ($evidence->path_file && Storage::disk('public')->exists($evidence->path_file)) {
                Storage::disk('public')->delete($evidence->path_file);
            }

            $oldData = $evidence->toArray();
            $evidence->delete();

            AuditLogService::log('DELETE', 'Evidence', $id, $oldData, null);
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data evidence: '.$e->getMessage());
        }

        return $this->respond(true, 200, 'Data evidence dan berkas fisik berhasil dihapus.');
    }

    // Download berkas fisik evidence
    public function download(int $id): StreamedResponse|JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        if (! $evidence->path_file || ! Storage::disk('public')->exists($evidence->path_file)) {
            return $this->respond(false, 404, 'Berkas fisik evidence tidak ditemukan di server.');
        }

        return Storage::disk('public')->download($evidence->path_file, $evidence->nama_file ?? 'evidence');
    }

    // Workflow Validasi Evidence oleh Kaprodi / Validator (Tugas Teman)
    public function validasi(Request $request, int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'status_validasi' => 'required|in:Valid,Ditolak,Perlu Perbaikan',
            'catatan_validasi' => 'required_if:status_validasi,Ditolak,Perlu Perbaikan|nullable|string',
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

        // Jika status "Perlu Perbaikan", otomatis kirim Notifikasi in-app
        if ($status === 'Perlu Perbaikan') {
            Notifikasi::create([
                'user_id' => $evidence->divalidasi_oleh,
                'tipe' => 'EVIDENCE_REVISI',
                'judul' => 'Revisi Evidence Diperlukan',
                'pesan' => "Dokumen '{$evidence->jenis_dokumen}' memerlukan perbaikan: {$catatan}",
                'link' => "/evidence/{$evidence->id_evidence}",
            ]);
        }

        AuditLogService::log('VALIDATE', 'Evidence', $evidence->id_evidence, null, [
            'status_validasi' => $status,
            'catatan_validasi' => $catatan,
        ]);

        return $this->respond(true, 200, "Evidence berhasil divalidasi dengan status: {$status}.", $evidence);
    }

    // Fitur Ganti Link (Arsipkan Link Lama & Reset Status - Tugas Teman)
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
            'alasan_perubahan' => 'nullable|string|max:255',
        ], [
            'url_drive_baru.regex' => 'Format URL Google Drive baru tidak valid.',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $oldUrl = $evidence->url_drive ?? $evidence->nama_file;
        $newUrl = $request->input('url_drive_baru');

        $authUser = $request->input('auth_user');
        $userId = is_array($authUser) ? ($authUser['id'] ?? null) : ($authUser->id ?? auth()->id());

        // 1. Simpan riwayat link lama ke tabel evidence_histories
        EvidenceHistory::create([
            'evidence_id' => $evidence->id_evidence,
            'url_lama' => $oldUrl ?? '',
            'url_baru' => $newUrl,
            'alasan_perubahan' => $request->input('alasan_perubahan') ?? 'Penggantian link bukti dukung',
            'diubah_oleh' => $userId,
        ]);

        // 2. Perbarui link dan reset status ke "Menunggu Validasi"
        $evidence->update([
            'nama_file' => $newUrl,
            'url_drive' => $newUrl,
            'status_validasi' => 'Menunggu Validasi',
            'catatan_validasi' => null,
            'divalidasi_oleh' => null,
            'tanggal_validasi' => null,
        ]);

        AuditLogService::log('UPDATE_LINK', 'Evidence', $evidence->id_evidence, ['old' => $oldUrl], ['new' => $newUrl]);

        return $this->respond(true, 200, 'Link Google Drive berhasil diganti, link lama diarsipkan, dan status di-reset ke Menunggu Validasi.', $evidence->fresh('history'));
    }
}
