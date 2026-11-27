<?php

namespace App\Http\Controllers\Api;

use App\Exports\KerjaSamaExport;
use App\Http\Controllers\Controller;
use App\Imports\KerjaSamaImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KerjaSamaImportExportController extends Controller
{
    public function template(): BinaryFileResponse
    {
        return Excel::download(
            new KerjaSamaExport(true),
            'template_kerja_sama.xlsx'
        );
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(
            new KerjaSamaExport,
            'data_kerja_sama.xlsx'
        );
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240',
            ],
        ]);

        Excel::import(
            new KerjaSamaImport,
            $request->file('file')
        );

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'Import data kerja sama berhasil.',
            'data' => null,
            'errors' => null,
        ]);
    }
}
