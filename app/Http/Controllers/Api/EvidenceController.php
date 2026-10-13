<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\EvidenceHistory;
use App\Models\Notifikasi;
use App\Services\AuditLogService;
use App\Services\K3ApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvidenceController extends Controller
{
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // TUGAS 5: Repository Evidence dengan Filter Multi-Kriteria
    public function index(Request $request): JsonResponse
    {
        $query = Evidence::with(['history', 'kerjaSama', 'sertifikasi', 'kegiatan', 'luaran']);

        if ($jenis = $request->query('jenis_dokumen')) {
            $query->where('jenis_dokumen', 'like', "%{$jenis}%");
        }
        if ($status = $request->query('status_validasi')) {
            $query->where('status_validasi', $status);
        }
        foreach (['id_kerjasama', 'id_sertifikasi', 'id_kegiatan', 'id_luaran'] as $fk) {
            if ($request->filled($fk)) {
                $query->where($fk, $request->query($fk));
            }
        }

        $evidence = $query->latest('id_evidence')->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data repository evidence berhasil diambil.', $evidence);
    }

    // TUGAS 3: Input Link Drive & Validasi Format URL + Cek Duplikasi
    public function store(Request $request, K3ApiService $k3): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nama_file'      => [
                'required',
                'url',
                'regex:/^https:\/\/(drive\.google\.com|docs\.google\.com)\/.+/',
            ],
            'jenis_dokumen'  => 'required|string|max:255',
            'id_kerjasama'   => 'nullable|integer|exists:kerja_sama,id_kerjasama',
            'id_sertifikasi' => 'nullable|integer|exists:sertifikasi,id_sertifikasi',
            'id_kegiatan'    => 'nullable|integer|exists:kegiatan,id_kegiatan',
            'id_luaran'      => 'nullable|integer|exists:luaran,id_luaran',
        ], [
            'nama_file.regex' => 'Format URL Google Drive tidak valid. URL harus diawali https://drive.google.com atau https://docs.google.com',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();
        $urlDrive  = $validated['nama_file'];

        // Cek Duplikasi Link Google Drive (Sesuai Flowchart 11A)
        $isDuplicate = Evidence::where('nama_file', $urlDrive)->exists();
        if ($isDuplicate && ! $request->boolean('konfirmasi_lanjut')) {
            return $this->respond(false, 409, 'Peringatan: Link Google Drive ini sudah pernah digunakan sebelumnya. Kirim parameter "konfirmasi_lanjut": true jika ingin tetap menyimpan.', [
                'is_duplicate' => true,
            ]);
        }

        $validated['status_validasi'] = 'Menunggu Validasi';
        $validated['tanggal_upload']  = now()->toDateString();

        $evidence = Evidence::create($validated);

        // Catat Audit Log
        AuditLogService::log('CREATE', 'Evidence', $evidence->id_evidence, null, $evidence->toArray());

        return $this->respond(true, 201, 'Link evidence berhasil disimpan dan menunggu validasi.', $evidence);
    }

    // Detail Satu Evidence
    public function show(int $id): JsonResponse
    {
        $evidence = Evidence::with(['history', 'kerjaSama', 'sertifikasi', 'kegiatan', 'luaran'])->find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail evidence berhasil diambil.', $evidence);
    }

    // TUGAS 4: Workflow Validasi Evidence oleh Kaprodi / Validator
    public function validasi(Request $request, int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'status_validasi'  => 'required|in:Valid,Ditolak,Perlu Perbaikan',
            'catatan_validasi' => 'required_if:status_validasi,Ditolak,Perlu Perbaikan|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $status  = $request->input('status_validasi');
        $catatan = $request->input('catatan_validasi');

        $evidence->update([
            'status_validasi'  => $status,
            'catatan_validasi' => $catatan,
            'divalidasi_oleh'  => $request->input('auth_user')->id ?? null,
            'tanggal_validasi' => now(),
        ]);

        // Jika status "Perlu Perbaikan", otomatis kirim Notifikasi in-app
        if ($status === 'Perlu Perbaikan') {
            Notifikasi::create([
                'user_id' => $evidence->divalidasi_oleh,
                'tipe'    => 'EVIDENCE_REVISI',
                'judul'   => 'Revisi Evidence Diperlukan',
                'pesan'   => "Dokumen '{$evidence->jenis_dokumen}' memerlukan perbaikan: {$catatan}",
                'link'    => "/evidence/{$evidence->id_evidence}",
            ]);
        }

        AuditLogService::log('VALIDATE', 'Evidence', $evidence->id_evidence, null, [
            'status_validasi'  => $status,
            'catatan_validasi' => $catatan,
        ]);

        return $this->respond(true, 200, "Evidence berhasil divalidasi dengan status: {$status}.", $evidence);
    }

    // TUGAS 5: Fitur Ganti Link (Arsipkan Link Lama & Reset Status)
    public function gantiLink(Request $request, int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'url_drive_baru'   => [
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

        $oldUrl = $evidence->nama_file;
        $newUrl = $request->input('url_drive_baru');

        // 1. Simpan riwayat link lama ke tabel evidence_histories
        EvidenceHistory::create([
            'evidence_id'      => $evidence->id_evidence,
            'url_lama'         => $oldUrl,
            'url_baru'         => $newUrl,
            'alasan_perubahan' => $request->input('alasan_perubahan') ?? 'Penggantian link bukti dukung',
            'diubah_oleh'      => $request->input('auth_user')->id ?? null,
        ]);

        // 2. Perbarui link dan reset status ke "Menunggu Validasi"
        $evidence->update([
            'nama_file'        => $newUrl,
            'status_validasi'  => 'Menunggu Validasi',
            'catatan_validasi' => null,
            'divalidasi_oleh'  => null,
            'tanggal_validasi' => null,
        ]);

        AuditLogService::log('UPDATE_LINK', 'Evidence', $evidence->id_evidence, ['old' => $oldUrl], ['new' => $newUrl]);

        return $this->respond(true, 200, 'Link Google Drive berhasil diganti, link lama diarsipkan, dan status di-reset ke Menunggu Validasi.', $evidence->fresh('history'));
    }
}
