<?php

namespace App\Http\Controllers\Api;

use App\Exports\TendikExport;
use App\Http\Controllers\Controller;
use App\Imports\TendikImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class TendikImportExportController extends Controller
{
    public function template()
    {
        return Excel::download(
            new TendikExport(true),
            'template_tendik.xlsx'
        );
    }

    public function export()
    {
        return Excel::download(
            new TendikExport,
            'data_tendik.xlsx'
        );
    }

    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($request): void {
            Excel::import(new TendikImport, $request->file('file'));
        });

        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan, pendidikan, dan sertifikasi berhasil diimpor.',
        ]);
    }
}
