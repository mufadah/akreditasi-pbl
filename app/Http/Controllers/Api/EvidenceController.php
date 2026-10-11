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
        $query = Evidence::with('history');

        if ($tahun = $request->query('tahun')) {
            $query->where('tahun', $tahun);
        }
        if ($jenis = $request->query('jenis_dokumen')) {
            $query->where('jenis_dokumen', 'like', "%{$jenis}%");
        }
        if ($indikator = $request->query('kode_indikator')) {
            $query->where('kode_indikator', $indikator);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($tipe = $request->query('tipe_entitas')) {
            $query->where('tipe_entitas', $tipe);
        }

        $evidence = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data repository evidence berhasil diambil.', $evidence);
    }

    // TUGAS 3: Input Link Drive & Validasi Format URL + Cek Duplikasi
    public function store(Request $request, K3ApiService $k3): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url_drive'      => [
                'required',
                'url',
                'regex:/^https:\/\/(drive\.google\.com|docs\.google\.com)\/.+/',
            ],
            'tipe_entitas'   => 'required|string|in:Penelitian,Pkm,KerjaSama,Dosen',
            'id_entitas'     => 'required|integer',
            'jenis_dokumen'  => 'required|string|max:255',
            'kode_indikator' => 'required|string|max:50',
            'tahun'          => 'nullable|integer|digits:4',
        ], [
            'url_drive.regex' => 'Format URL Google Drive tidak valid. URL harus diawali https://drive.google.com atau https://docs.google.com',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();
        $urlDrive  = $validated['url_drive'];

        // Cek Duplikasi Link Google Drive (Sesuai Flowchart 11A)
        $isDuplicate = Evidence::where('url_drive', $urlDrive)->exists();
        if ($isDuplicate && ! $request->boolean('konfirmasi_lanjut')) {
            return $this->respond(false, 409, 'Peringatan: Link Google Drive ini sudah pernah digunakan sebelumnya. Kirim parameter "konfirmasi_lanjut": true jika ingin tetap menyimpan.', [
                'is_duplicate' => true,
            ]);
        }

        $validated['status']      = 'Menunggu Validasi';
        $validated['uploaded_by'] = $request->input('auth_user')->id ?? null;

        $evidence = Evidence::create($validated);

        // Catat Audit Log
        AuditLogService::log('CREATE', 'Evidence', $evidence->id_evidence, null, $evidence->toArray());

        return $this->respond(true, 201, 'Link evidence berhasil disimpan dan menunggu validasi.', $evidence);
    }

    // Detail Satu Evidence
    public function show(int $id): JsonResponse
    {
        $evidence = Evidence::with('history')->find($id);

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
            'status'            => 'required|in:Valid,Ditolak,Perlu Perbaikan',
            'catatan_penolakan' => 'required_if:status,Ditolak,Perlu Perbaikan|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $status  = $request->input('status');
        $catatan = $request->input('catatan_penolakan');

        $evidence->update([
            'status'            => $status,
            'catatan_penolakan' => $catatan,
            'validated_by'      => $request->input('auth_user')->id ?? null,
            'validated_at'      => now(),
        ]);

        // Jika status "Perlu Perbaikan", otomatis kirim Notifikasi in-app ke pengunggah
        if ($status === 'Perlu Perbaikan') {
            Notifikasi::create([
                'user_id' => $evidence->uploaded_by,
                'tipe'    => 'EVIDENCE_REVISI',
                'judul'   => 'Revisi Evidence Diperlukan',
                'pesan'   => "Dokumen '{$evidence->jenis_dokumen}' ({$evidence->kode_indikator}) memerlukan perbaikan: {$catatan}",
                'link'    => "/evidence/{$evidence->id_evidence}",
            ]);
        }

        AuditLogService::log('VALIDATE', 'Evidence', $evidence->id_evidence, null, [
            'status'  => $status,
            'catatan' => $catatan,
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
            'url_drive_baru' => [
                'required',
                'url',
                'regex:/^https:\/\/(drive\.google\.com|docs\.google\.com)\/.+/',
            ],
        ], [
            'url_drive_baru.regex' => 'Format URL Google Drive baru tidak valid.',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $oldUrl = $evidence->url_drive;
        $newUrl = $request->input('url_drive_baru');

        // 1. Simpan riwayat link lama ke tabel evidence_history
        EvidenceHistory::create([
            'id_evidence'   => $evidence->id_evidence,
            'old_url_drive' => $oldUrl,
            'replaced_by'   => $request->input('auth_user')->id ?? null,
        ]);

        // 2. Perbarui link dan reset status ke "Menunggu Validasi"
        $evidence->update([
            'url_drive'         => $newUrl,
            'status'            => 'Menunggu Validasi',
            'catatan_penolakan' => null,
            'validated_by'      => null,
            'validated_at'      => null,
        ]);

        AuditLogService::log('UPDATE_LINK', 'Evidence', $evidence->id_evidence, ['old' => $oldUrl], ['new' => $newUrl]);

        return $this->respond(true, 200, 'Link Google Drive berhasil diganti, link lama diarsipkan, dan status di-reset ke Menunggu Validasi.', $evidence->fresh('history'));
    }
}
