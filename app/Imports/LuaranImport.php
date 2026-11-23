<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LuaranImport implements Import, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Buku' => new LuaranSheetImport('Buku'),
            'Jurnal' => new LuaranSheetImport('Jurnal'),
            'Prosiding' => new LuaranSheetImport('Prosiding'),
            'HKI' => new LuaranSheetImport('HKI'),
            'Produk' => new LuaranSheetImport('Produk'),
        ];
    }
}
