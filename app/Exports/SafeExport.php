<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Dasar semua ekspor Excel.
 *
 * Teks selalu disimpan sebagai teks: nol di depan (NIDN, ISBN) tidak hilang dan teks yang
 * diawali "=" (hasil scraping/input pengguna) tidak pernah dievaluasi sebagai rumus.
 * Angka asli (mis. jumlah dana) tetap bertipe angka agar bisa dijumlahkan di Excel.
 */
abstract class SafeExport extends StringValueBinder implements ShouldAutoSize, WithCustomValueBinder
{
    protected bool $convertNumeric = false;
}
