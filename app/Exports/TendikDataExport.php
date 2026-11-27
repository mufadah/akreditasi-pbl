<?php

namespace App\Exports;

use App\Models\TenagaKependidikan;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class TendikDataExport extends SafeExport implements Export, FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly bool $templateOnly = false) {}

    public function title(): string
    {
        return 'Tenaga Kependidikan';
    }

    public function query(): Builder
    {
        $query = TenagaKependidikan::withTrashed()->with('pendidikan');

        return $this->templateOnly ? $query->whereRaw('1 = 0') : $query->orderBy('id_tendik');
    }

    public function headings(): array
    {
        return [
            'nip', 'nama_tendik', 'jabatan_tendik',
            'jenjang', 'universitas', 'program_studi', 'tahun_lulus',
        ];
    }

    public function map($row): array
    {
        return [
            $row->nip,
            $row->nama_tendik,
            $row->jabatan_tendik,
            $row->pendidikan?->jenjang,
            $row->pendidikan?->universitas,
            $row->pendidikan?->program_studi,
            $row->pendidikan?->tahun_lulus,
        ];
    }
}
