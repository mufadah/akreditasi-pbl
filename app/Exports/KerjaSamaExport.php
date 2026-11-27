<?php

namespace App\Exports;

use App\Models\KerjaSama;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KerjaSamaExport extends SafeExport implements Export, FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly bool $templateOnly = false
    ) {}

    public function query(): Builder
    {
        $query = KerjaSama::query()
            ->with(['mitra', 'jenisKerjaSama', 'dosen'])
            ->orderBy('id_kerja_sama');

        if ($this->templateOnly) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'nama_mitra',
            'nama_jenis_kerjasama',
            'nidn',
            'judul_kerja_sama',
            'tingkat',
            'bentuk_kegiatan',
            'nomor_dokumen',
            'jenis_dokumen',
            'tanggal_mulai',
            'tanggal_selesai',
            'status',
            'bukti_dokumen',
        ];
    }

    public function map($row): array
    {
        return [
            $row->mitra?->nama_mitra,
            $row->jenisKerjaSama?->nama_jenis_kerjasama,
            $row->dosen?->nidn,
            $row->judul_kerja_sama,
            $row->tingkat,
            $row->bentuk_kegiatan,
            $row->nomor_dokumen,
            $row->jenis_dokumen,
            $row->tanggal_mulai,
            $row->tanggal_selesai,
            $row->status,
            $row->bukti_dokumen,
        ];
    }
}
