<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssessmentKerjaSamaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssessmentKerjaSamaDetailController extends Controller
{
    public function __construct(private AssessmentKerjaSamaService $service) {}

    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'tahun' => ['nullable', 'integer', 'digits:4'],
            'jenjang' => ['nullable', 'in:D3,D4,S1,S2,S3'],
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        $hasil = $this->service->hitung($validated['tahun'] ?? today()->year, $validated['jenjang'] ?? 'S1');

        return $this->respond(true, 200, 'Assessment kerja sama per indikator berhasil dihitung.', $hasil);
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