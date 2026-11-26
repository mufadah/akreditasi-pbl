<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EarlyWarningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EarlyWarningController extends Controller
{
    public function __construct(private EarlyWarningService $service) {}

    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'tahun_tanpa_penelitian' => ['nullable', 'integer', 'between:1,10'],
            'tahun_tanpa_publikasi' => ['nullable', 'integer', 'between:1,10'],
            'bulan_sertifikasi' => ['nullable', 'integer', 'between:1,24'],
            'rasio_maks' => ['nullable', 'numeric', 'gt:0'],
            'tahun' => ['nullable', 'integer', 'digits:4'],
            'prodi' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $param = array_filter($validator->validated(), fn ($nilai) => $nilai !== null);

        return $this->respond(true, 200, 'Early warning dosen berhasil dihitung.', $this->service->hitung($param));
    }

    private function respond($success, $code, $message, $data = null)
    {
        return response()->json([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}