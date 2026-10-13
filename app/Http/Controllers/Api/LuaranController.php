<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Luaran;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LuaranController extends Controller
{
    private array $relations = ['kegiatan', 'dosen', 'jenisLuaran', 'evidence'];

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
        $query = Luaran::with($this->relations);

        if ($dosen = $request->query('id_dosen')) {
            $query->where('id_dosen', $dosen);
        }

        if ($kegiatan = $request->query('id_kegiatan')) {
            $query->where('id_kegiatan', $kegiatan);
        }

        if ($jenis = $request->query('id_jenis_luaran')) {
            $query->where('id_jenis_luaran', $jenis);
        }

        $luaran = $query->latest('id_luaran')->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Daftar luaran (Buku, Jurnal, Prosiding, HKI, Produk) berhasil diambil.', $luaran);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_dosen'        => 'required|integer|exists:dosen,id_dosen',
            'id_jenis_luaran' => 'required|integer|exists:jenis_luaran,id_jenis_luaran',
            'id_kegiatan'     => 'nullable|integer|exists:kegiatan,id_kegiatan',

            // Buku
            'judul_buku'      => 'nullable|string|max:255',
            'isbn'            => 'nullable|string|max:100',
            'penerbit_buku'   => 'nullable|string|max:255',
            'tahun_terbit'    => 'nullable|date',

            // Jurnal
            'nama_jurnal'     => 'nullable|string|max:255',
            'issn'            => 'nullable|string|max:100',
            'doi'             => 'nullable|string|max:255',
            'indeksasi'       => 'nullable|string|max:100',

            // HKI
            'jenis_hki'       => 'nullable|string|max:100',
            'judul_hak_cipta' => 'nullable|string|max:255',
            'nomor_pendaftaran_hki' => 'nullable|string|max:100',
            'nomor_sertifikat_hki'  => 'nullable|string|max:100',

            // Produk
            'nama_produk'     => 'nullable|string|max:255',
            'tingkat_kesiapanterapan' => 'nullable|integer|between:1,9',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $luaran = Luaran::create($validator->validated());
        AuditLogService::log('CREATE', 'Luaran', $luaran->id_luaran, null, $luaran->toArray());

        return $this->respond(true, 201, 'Data luaran berhasil ditambahkan.', $luaran->load($this->relations));
    }

    public function show(int $id): JsonResponse
    {
        $luaran = Luaran::with($this->relations)->find($id);

        if (! $luaran) {
            return $this->respond(false, 404, 'Data luaran tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail luaran berhasil diambil.', $luaran);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $luaran = Luaran::find($id);

        if (! $luaran) {
            return $this->respond(false, 404, 'Data luaran tidak ditemukan.');
        }

        $oldValues = $luaran->toArray();
        $luaran->update($request->all());
        AuditLogService::log('UPDATE', 'Luaran', $id, $oldValues, $luaran->fresh()->toArray());

        return $this->respond(true, 200, 'Data luaran berhasil diperbarui.', $luaran->fresh($this->relations));
    }

    public function destroy(int $id): JsonResponse
    {
        $luaran = Luaran::find($id);

        if (! $luaran) {
            return $this->respond(false, 404, 'Data luaran tidak ditemukan.');
        }

        AuditLogService::log('DELETE', 'Luaran', $id, $luaran->toArray(), null);
        $luaran->delete();

        return $this->respond(true, 200, 'Data luaran berhasil dihapus.');
    }
}
