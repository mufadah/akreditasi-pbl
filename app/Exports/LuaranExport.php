<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LuaranExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new LuaranSheetExport('Buku'),
            new LuaranSheetExport('Jurnal'),
            new LuaranSheetExport('Prosiding'),
            new LuaranSheetExport('HKI'),
            new LuaranSheetExport('Produk'),
        ];
    }
}
