<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanWorkbookExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly array $lembar) {}

    public function sheets(): array
    {
        return array_map(
            fn ($item) => new LaporanSheetExport($item['judul'], $item['headings'], $item['rows']),
            $this->lembar
        );
    }
}