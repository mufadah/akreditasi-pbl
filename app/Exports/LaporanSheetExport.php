<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanSheetExport extends SafeExport implements Export, FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $judul,
        private readonly array $headings,
        private readonly array $rows,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $this->judul), 0, 31);
    }
}