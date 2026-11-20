<?php

namespace App\Exports;

use App\Models\Kegiatan;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KegiatanExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $jenisKegiatan,
        private readonly bool $templateOnly = false
    ) {}

    public function query(): Builder
    {
        $query = Kegiatan::query()
            ->with('jenisKegiatan')
            ->whereHas('jenisKegiatan', function (Builder $query): void {
                $query->where(
                    'nama_jenis_kegiatan',
                    $this->jenisKegiatan
                );
            });

        if ($this->templateOnly) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'jenis_kegiatan',
            'judul_kegiatan',
            'lokasi_kegiatan',
            'tahun_usulan',
            'tahun_kegiatan',
            'tahun_pelaksanaan',
            'lama_kegiatan',
            'status_kegiatan',
            'in_kind',
        ];
    }

    public function map($kegiatan): array
    {
        return [
            $kegiatan->jenisKegiatan?->nama_jenis_kegiatan,
            $kegiatan->judul_kegiatan,
            $kegiatan->lokasi_kegiatan,
            $kegiatan->tahun_usulan,
            $kegiatan->tahun_kegiatan,
            $kegiatan->tahun_pelaksanaan,
            $kegiatan->lama_kegiatan,
            $kegiatan->status_kegiatan,
            $kegiatan->in_kind,
        ];
    }
}
