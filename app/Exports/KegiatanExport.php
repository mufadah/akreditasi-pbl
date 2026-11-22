<?php

namespace App\Exports;

use App\Models\Kegiatan;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KegiatanExport extends SafeExport implements Export, FromQuery, WithHeadings, WithMapping
{
    private int $nomor = 0;

    /**
     * @param  Builder<Kegiatan>|string  $source
     */
    public function __construct(
        private readonly Builder|string $source,
        private readonly bool $templateOnly = false,
    ) {}

    /**
     * @return Builder<Kegiatan>
     */
    public function query(): Builder
    {
        if (is_string($this->source)) {
            $query = Kegiatan::query()
                ->with('jenisKegiatan')
                ->whereHas('jenisKegiatan', function (Builder $query): void {
                    $query->where(
                        'nama_jenis_kegiatan',
                        $this->source
                    );
                });

            if ($this->templateOnly) {
                $query->whereRaw('1 = 0');
            }

            return $query;
        }

        return $this->source
            ->with([
                'jenisKegiatan',
                'anggota.dosen',
                'pendanaan',
                'luaran',
            ])
            ->orderByDesc('id_kegiatan');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        if (is_string($this->source)) {
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
        if (is_string($this->source)) {
            return [
                $row->jenisKegiatan?->nama_jenis_kegiatan,
                $row->judul_kegiatan,
                $row->lokasi_kegiatan,
                $row->tahun_usulan,
                $row->tahun_kegiatan,
                $row->tahun_pelaksanaan,
                $row->lama_kegiatan,
                $row->status_kegiatan,
                $row->in_kind,
            ];
        }

        $namaDosen = static fn ($anggota): string => (string) ($anggota->dosen?->nama_dosen ?? '');

        $ketua = $row->anggota->first(
            fn ($anggota) => strcasecmp(
                (string) $anggota->peran,
                'Ketua'
            ) === 0
        );

        $anggota = $row->anggota
            ->reject(fn ($item) => $ketua !== null && $item->is($ketua))
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
            (float) $row->pendanaan->sum(
                fn ($pendanaan) => (float) $pendanaan->jumlah_dana
            ),
            $row->pendanaan
                ->pluck('sumber_dana')
                ->filter()
                ->unique()
                ->implode('; ') ?: null,
            $row->luaran->count(),
        ];
    }
}
