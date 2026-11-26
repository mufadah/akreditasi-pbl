<?php

namespace App\Exports;

use App\Models\Sertifikasi;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class SertifikasiTendikExport extends SafeExport implements Export, FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly bool $templateOnly = false) {}

    public function title(): string
    {
        return 'Sertifikasi';
    }

    public function query(): Builder
    {
        $query = Sertifikasi::whereNotNull('id_tendik')->with('tendik');

        return $this->templateOnly ? $query->whereRaw('1 = 0') : $query->orderBy('id_sertifikasi');
    }

    public function headings(): array
    {
        return [
            'nip', 'jenis_sertifikasi', 'bidang_studi',
            'nomor_registrasi_pendidik', 'nomor_sk', 'nomor_sertifikat',
            'lembaga_penerbit', 'tahun_sertifikasi',
            'tanggal_kedaluwarsa', 'status_sertifikasi', 'url_sertifikasi',
        ];
    }

    public function map($row): array
    {
        return [
            $row->tendik?->nip,
            $row->jenis_sertifikasi,
            $row->bidang_studi,
            $row->nomor_registrasi_pendidik,
            $row->nomor_sk,
            $row->nomor_sertifikat,
            $row->lembaga_penerbit,
            $row->tahun_sertifikasi,
            $row->tanggal_kedaluwarsa?->format('Y-m-d'),
            $row->status_sertifikasi,
            $row->url_sertifikasi,
        ];
    }
}
