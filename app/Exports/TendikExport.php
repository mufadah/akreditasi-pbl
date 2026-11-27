<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TendikExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly bool $templateOnly = false) {}

    public function sheets(): array
    {
        return [
            new TendikDataExport($this->templateOnly),
            new SertifikasiTendikExport($this->templateOnly),
        ];
    }
}
