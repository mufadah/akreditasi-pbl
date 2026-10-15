<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KonfigurasiMetrik;
use App\Services\AuditLogService;
use App\Services\K3ApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KonfigurasiMetrikController extends Controller
{
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
     * Ambil daftar pemetaan indikator ke metrik yang tersimpan di K2.
     */
    public function index(Request $request): JsonResponse
    {
        $query = KonfigurasiMetrik::query();

        if ($bidang = $request->query('bidang')) {
            $query->where('bidang', $bidang);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_indikator', 'like', "%{$search}%")
                    ->orWhere('nama_indikator', 'like', "%{$search}%");
            });
        }

        $metrik = $query->paginate($request->integer('per_page', 15));

        return $this->respond(true, 200, 'Daftar konfigurasi metrik berhasil diambil.', $metrik);
    }

    /**
     * Ambil daftar indikator aktif dari API K3 (Fitur 13 Flowchart).
     */
    public function getIndikatorK3(K3ApiService $k3): JsonResponse
    {
        $indikator = $k3->getIndikatorAktif();

        if (empty($indikator)) {
            return $this->respond(false, 502, 'Gagal mengambil daftar indikator aktif dari API K3.');
        }

        return $this->respond(true, 200, 'Daftar indikator aktif dari API K3 berhasil diambil.', $indikator);
    }

    /**
     * Simpan pemetaan indikator ke rumus metrik K2 (Fitur 13 Flowchart).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'kode_indikator' => ['required', 'string', 'max:50', Rule::unique('konfigurasi_metrik', 'kode_indikator')],
            'nama_indikator' => ['required', 'string', 'max:255'],
            'bidang' => ['required', 'string', Rule::in(['SDM', 'Penelitian', 'PkM', 'Kerja Sama', 'Kerjasama'])],
            'rumus_formula' => ['required', 'string', 'max:255'],
            'ambang_target' => ['nullable', 'numeric', 'min:0'],
            'jenjang' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        // Validasi sintaks formula (Fitur 13: Rumus valid?)
        if (trim($validated['rumus_formula']) === '') {
            return $this->respond(false, 422, 'Rumus tidak valid: Rumus formula metrik tidak boleh kosong.');
        }

        $metrik = KonfigurasiMetrik::create($validated);

        if (class_exists(AuditLogService::class)) {
            AuditLogService::log('CREATE', 'KonfigurasiMetrik', $metrik->id_metrik, null, $metrik->toArray());
        }

        return $this->respond(true, 201, 'Pemetaan indikator ke metrik berhasil disimpan.', $metrik);
    }

    /**
     * Detail satu pemetaan metrik.
     */
    public function show(int $id): JsonResponse
    {
        $metrik = KonfigurasiMetrik::find($id);

        if (! $metrik) {
            return $this->respond(false, 404, 'Konfigurasi metrik tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail konfigurasi metrik berhasil diambil.', $metrik);
    }

    /**
     * Update pemetaan indikator ke metrik.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $metrik = KonfigurasiMetrik::find($id);

        if (! $metrik) {
            return $this->respond(false, 404, 'Konfigurasi metrik tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'kode_indikator' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('konfigurasi_metrik', 'kode_indikator')->ignore($metrik->id_metrik, 'id_metrik')],
            'nama_indikator' => ['sometimes', 'required', 'string', 'max:255'],
            'bidang' => ['sometimes', 'required', 'string', Rule::in(['SDM', 'Penelitian', 'PkM', 'Kerja Sama', 'Kerjasama'])],
            'rumus_formula' => ['sometimes', 'required', 'string', 'max:255'],
            'ambang_target' => ['nullable', 'numeric', 'min:0'],
            'jenjang' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();
        $oldValues = $metrik->toArray();

        $metrik->update($validated);

        if (class_exists(AuditLogService::class)) {
            AuditLogService::log('UPDATE', 'KonfigurasiMetrik', $id, $oldValues, $metrik->fresh()->toArray());
        }

        return $this->respond(true, 200, 'Konfigurasi metrik berhasil diperbarui.', $metrik->fresh());
    }

    /**
     * Hapus konfigurasi pemetaan metrik.
     */
    public function destroy(int $id): JsonResponse
    {
        $metrik = KonfigurasiMetrik::find($id);

        if (! $metrik) {
            return $this->respond(false, 404, 'Konfigurasi metrik tidak ditemukan.');
        }

        $oldValues = $metrik->toArray();
        $metrik->delete();

        if (class_exists(AuditLogService::class)) {
            AuditLogService::log('DELETE', 'KonfigurasiMetrik', $id, $oldValues, null);
        }

        return $this->respond(true, 200, 'Konfigurasi metrik berhasil dihapus.');
    }
}
