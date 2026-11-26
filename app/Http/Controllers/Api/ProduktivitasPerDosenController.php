<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProduktivitasPerDosenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProduktivitasPerDosenController extends Controller
{
    public function __construct(private ProduktivitasPerDosenService $service) {}

    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'tahun' => ['nullable', 'integer', 'digits:4'],
            'id_dosen' => ['nullable', 'integer', Rule::exists('dosen', 'id_dosen')->whereNull('deleted_at')],
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        $hasil = $this->service->hitung($validated['tahun'] ?? null, $validated['id_dosen'] ?? null);

        return $this->respond(true, 200, 'Produktivitas per dosen berhasil dihitung.', $hasil);
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