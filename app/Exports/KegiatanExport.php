<?php

namespace App\Exports;

use App\Models\Kegiatan;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KegiatanExport extends SafeExport implements FromQuery, WithHeadings, WithMapping
{
    private int $nomor = 0;

    /**
     * @param  Builder<Kegiatan>  $query
     */
    public function __construct(private readonly Builder $query) {}

    /**
     * @return Builder<Kegiatan>
     */
    public function query(): Builder
    {
        return $this->query
            ->with(['jenisKegiatan', 'anggota.dosen', 'pendanaan', 'luaran'])
            ->orderByDesc('id_kegiatan');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'No',
            'Jenis Kegiatan',
            'Judul Kegiatan',
            'Ketua',
            'Anggota',
            'Tahun Usulan',
            'Tahun Kegiatan',
            'Tahun Pelaksanaan',
            'Lokasi',
            'Lama Kegiatan',
            'Status',
            'Total Dana (Rp)',
            'Sumber Dana',
            'Jumlah Luaran',
        ];
    }

    /**
     * @param  Kegiatan  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $namaDosen = static fn ($anggota): string => (string) ($anggota->dosen?->nama_dosen ?? '');

        $ketua = $row->anggota->first(fn ($a) => strcasecmp((string) $a->peran, 'Ketua') === 0);
        $anggota = $row->anggota
            ->reject(fn ($a) => $ketua !== null && $a->is($ketua))
            ->map($namaDosen)
            ->filter()
            ->implode('; ');

        return [
            ++$this->nomor,
            $row->jenisKegiatan?->nama_jenis_kegiatan,
            $row->judul_kegiatan,
            $ketua ? $namaDosen($ketua) : null,
            $anggota !== '' ? $anggota : null,
            $row->tahun_usulan,
            $row->tahun_kegiatan,
            $row->tahun_pelaksanaan,
            $row->lokasi_kegiatan,
            $row->lama_kegiatan,
            $row->status_kegiatan,
            (float) $row->pendanaan->sum(fn ($p) => (float) $p->jumlah_dana),
            $row->pendanaan->pluck('sumber_dana')->filter()->unique()->implode('; ') ?: null,
            $row->luaran->count(),
        ];
    }
}
