<?php

namespace App\Exports;

use App\Models\Dosen;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DosenExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return Dosen::withTrashed()
            ->with(['jabatanAkademik', 'pendidikan'])
            ->orderBy('id_dosen');
    }

    public function headings(): array
    {
        return [
            'NIDN',
            'Nama',
            'Jabatan Akademik',
            'Pendidikan Terakhir',
            'Serdos',
            'Status',
        ];
    }

    /**
     * @param  Dosen  $row
     * @return array<int, string|null>
     */
    public function map(mixed $row): array
    {
        if (! $row instanceof Dosen) {
            throw new InvalidArgumentException('Dosen export expects a Dosen model.');
        }

        return [
            $row->nidn,
            $row->nama_dosen,
            $row->jabatanAkademik?->nama_jabatan,
            $row->pendidikan?->jenjang,
            $row->sertifikasi,
            $row->status_dosen,
        ];
    }
}
