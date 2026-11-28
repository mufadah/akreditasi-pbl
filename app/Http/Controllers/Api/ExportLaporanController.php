<?php

namespace App\Http\Controllers\Api;

use App\Exports\LaporanWorkbookExport;
use App\Http\Controllers\Controller;
use App\Services\LaporanService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ExportLaporanController extends Controller
{
    public function __construct(private LaporanService $laporan, private PdfService $pdf) {}

    public function assessment(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'format' => ['required', 'in:pdf,xlsx'],
            'bidang' => ['nullable', 'in:semua,penelitian,pkm,kerjasama'],
            'tahun' => ['nullable', 'integer', 'digits:4'],
            'jenjang' => ['nullable', 'in:D3,D4,S1,S2,S3'],
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        $laporan = $this->laporan->assessment(
            $validated['tahun'] ?? today()->year,
            $validated['jenjang'] ?? 'S1',
            $validated['bidang'] ?? 'semua'
        );

        return $this->kirim(
            $validated['format'],
            "laporan-assessment-{$laporan['tahun']}-{$laporan['jenjang']}",
            'laporan.assessment',
            $laporan,
            'landscape',
            fn () => $this->laporan->lembarAssessment($laporan)
        );
    }

    public function dashboard(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'format' => ['required', 'in:pdf,xlsx'],
            'tahun' => ['nullable', 'integer', 'digits:4'],
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        $laporan = $this->laporan->dashboard($validated['tahun'] ?? today()->year);

        return $this->kirim(
            $validated['format'],
            "laporan-dashboard-{$laporan['tahun']}",
            'laporan.dashboard',
            $laporan,
            'portrait',
            fn () => $this->laporan->lembarDashboard($laporan)
        );
    }

    private function kirim($format, $nama, $view, $data, $orientasi, $lembar)
    {
        if ($format === 'pdf') {
            return response($this->pdf->dariView($view, $data, $orientasi), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$nama}.pdf\"",
            ]);
        }

        return Excel::download(new LaporanWorkbookExport($lembar()), "{$nama}.xlsx");
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