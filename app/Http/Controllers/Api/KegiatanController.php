<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KegiatanController extends Controller
{
    private array $relations = ['jenisKegiatan', 'pendanaan', 'anggota.dosen', 'luaran', 'evidence'];

    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Kegiatan::with($this->relations);

        if ($search = $request->query('search')) {
            $query->where('judul_kegiatan', 'like', "%{$search}%");
        }

        if ($jenis = $request->query('id_jenis_kegiatan')) {
            $query->where('id_jenis_kegiatan', $jenis);
        }

        if ($tahun = $request->query('tahun_kegiatan')) {
            $query->where('tahun_kegiatan', $tahun);
        }

        if ($status = $request->query('status_kegiatan')) {
            $query->where('status_kegiatan', $status);
        }

        $kegiatan = $query->latest('id_kegiatan')->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Daftar kegiatan (Penelitian & PkM) berhasil diambil.', $kegiatan);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_jenis_kegiatan'   => 'required|integer|exists:jenis_kegiatan,id_jenis_kegiatan',
            'judul_kegiatan'      => 'required|string|max:255',
            'afiliasi'            => 'required|string|max:255',
            'lokasi_kegiatan'     => 'nullable|string|max:255',
            'litabmas_sebelumnya' => 'nullable|string|max:255',
            'jenis_skim'          => 'nullable|string|max:100',
            'tahun_usulan'        => 'required|integer',
            'tahun_kegiatan'      => 'required|integer',
            'tahun_pelaksanaan'   => 'nullable|integer',
            'lama_kegiatan'       => 'required|integer',
            'tahun_pelaksanaan_ke'=> 'required|integer',
            'nomor_sk_penugasan'  => 'nullable|string|max:100',
            'tanggal_sk_penugasan'=> 'nullable|date',
            'in_kind'             => 'nullable|string',
            'status_kegiatan'     => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $kegiatan = Kegiatan::create($validator->validated());
        AuditLogService::log('CREATE', 'Kegiatan', $kegiatan->id_kegiatan, null, $kegiatan->toArray());

        return $this->respond(true, 201, 'Data kegiatan berhasil dibuat.', $kegiatan->load($this->relations));
    }

    public function show(int $id): JsonResponse
    {
        $kegiatan = Kegiatan::with($this->relations)->find($id);

        if (! $kegiatan) {
            return $this->respond(false, 404, 'Data kegiatan tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail kegiatan berhasil diambil.', $kegiatan);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $kegiatan = Kegiatan::find($id);

        if (! $kegiatan) {
            return $this->respond(false, 404, 'Data kegiatan tidak ditemukan.');
        }

        $validator = Validator::make($request->all(), [
            'id_jenis_kegiatan'   => 'sometimes|required|integer|exists:jenis_kegiatan,id_jenis_kegiatan',
            'judul_kegiatan'      => 'sometimes|required|string|max:255',
            'afiliasi'            => 'sometimes|required|string|max:255',
            'tahun_kegiatan'      => 'sometimes|required|integer',
            'lama_kegiatan'       => 'sometimes|required|integer',
            'status_kegiatan'     => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $oldValues = $kegiatan->toArray();
        $kegiatan->update($validator->validated());
        AuditLogService::log('UPDATE', 'Kegiatan', $id, $oldValues, $kegiatan->fresh()->toArray());

        return $this->respond(true, 200, 'Data kegiatan berhasil diperbarui.', $kegiatan->fresh($this->relations));
    }

    public function destroy(int $id): JsonResponse
    {
        $kegiatan = Kegiatan::find($id);

        if (! $kegiatan) {
            return $this->respond(false, 404, 'Data kegiatan tidak ditemukan.');
        }

        AuditLogService::log('DELETE', 'Kegiatan', $id, $kegiatan->toArray(), null);
        $kegiatan->delete();

        return $this->respond(true, 200, 'Data kegiatan berhasil dihapus.');
    }
}
