<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GapKompetensiDosenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GapKompetensiDosenController extends Controller
{
    public function __construct(private GapKompetensiDosenService $service) {}

    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'jenjang' => ['nullable', 'in:D3,D4,S1,S2,S3'],
            'target_s3' => ['nullable', 'numeric', 'between:0,100'],
            'target_lektor' => ['nullable', 'numeric', 'between:0,100'],
            'target_sertifikasi' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        $hasil = $this->service->hitung($validated['jenjang'] ?? 'S1', [
            'S3' => $validated['target_s3'] ?? null,
            'LEKTOR' => $validated['target_lektor'] ?? null,
            'SERTIFIKASI' => $validated['target_sertifikasi'] ?? null,
        ]);

        return $this->respond(true, 200, 'Analisis gap kompetensi dosen berhasil dihitung.', $hasil);
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