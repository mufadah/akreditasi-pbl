<?php

namespace App\Exports;

use App\Models\Luaran;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class LuaranSheetExport extends SafeExport implements Export, FromQuery, WithHeadings, WithMapping, WithTitle
{
    private array $fields;

    /**
     * @param  Builder<Luaran>|null  $query
     */
    public function __construct(
        private readonly string $kategori,
        private readonly ?Builder $baseQuery = null,
    ) {
        $this->fields = match ($kategori) {
            'Buku' => [
                'judul_buku',
                'isbn',
                'penerbit_buku',
                'tahun_terbit',
                'jenis_buku',
                'bidang_ilmu',
                'halaman_buku',
                'tanggal_terbit',
                'tautan_buku',
            ],

            'Jurnal' => [
                'nama_jurnal',
                'issn',
                'volume',
                'tanggal_terbit',
                'doi',
                'nomor_edisi',
                'halaman_jurnal',
                'indeksasi',
                'tautan_jurnal',
            ],

            'Prosiding' => [
                'nama_seminar',
                'nama_prosiding',
                'isbn_prosiding',
                'penyelenggara',
                'tanggal_seminar',
                'lokasi_seminar',
                'halaman_prosiding',
                'doi_prosiding',
                'tautan_seminar',
            ],

            'HKI' => [
                'jenis_hki',
                'judul_hak_cipta',
                'nomor_pendaftaran_hki',
                'nomor_sertifikat_hki',
                'tanggal_pendaftaran_hki',
                'tanggal_terbit_sertifikat_hki',
                'pemegang_hki',
                'status_hki',
                'tautan_hki',
            ],

            'Produk' => [
                'nama_produk',
                'jenis_produk',
                'status_pengembangan',
                'tingkat_kesiapanterapan',
                'tanggal_pembuatan',
                'deskripsi_produk',
                'tautan_produk',
            ],

            default => throw new \InvalidArgumentException(
                'Kategori luaran tidak valid.'
            ),
        };
    }

    public function title(): string
    {
        return $this->kategori;
    }

    public function query(): Builder
    {
        $query = $this->baseQuery ?? Luaran::query();

        return $query
            ->with(['dosen', 'jenisLuaran'])
            ->whereHas('jenisLuaran', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    match ($this->kategori) {
                        'Buku' => $query->where(
                            'nama_jenis_luaran',
                            'like',
                            '%Buku%'
                        ),

                        'Jurnal' => $query->where(
                            'nama_jenis_luaran',
                            'like',
                            '%Jurnal%'
                        ),

                        'Prosiding' => $query->where(
                            'nama_jenis_luaran',
                            'like',
                            '%Prosiding%'
                        ),

                        'HKI' => $query
                            ->where('nama_jenis_luaran', 'like', '%HKI%')
                            ->orWhere('nama_jenis_luaran', 'like', '%Hak Cipta%'),

                        'Produk' => $query->where(
                            'nama_jenis_luaran',
                            'like',
                            '%Produk%'
                        ),
                    };
                });
            })
            ->orderByDesc('id_luaran');
    }

    public function headings(): array
    {
        return array_merge([
            'nidn',
            'nama_dosen',
            'id_kegiatan',
            'nama_jenis_luaran',
            'indeks',
        ], $this->fields);
    }

    public function map($luaran): array
    {
        $data = [
            $luaran->dosen?->nidn,
            $luaran->dosen?->nama_dosen,
            $luaran->id_kegiatan,
            $luaran->jenisLuaran?->nama_jenis_luaran,
            $luaran->jenisLuaran?->indeks,
        ];

        foreach ($this->fields as $field) {
            $value = $luaran->{$field};

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }

            $data[] = $value;
        }

        return $data;
    }
}
