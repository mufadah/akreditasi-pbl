<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TendikImport implements Import, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Tenaga Kependidikan' => new TendikDataImport,
            'Sertifikasi' => new SertifikasiTendikImport,
        ];
    }
}
