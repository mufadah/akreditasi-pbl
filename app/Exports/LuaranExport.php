<?php

namespace App\Exports;

use App\Models\Luaran;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LuaranExport extends SafeExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * Kolom khusus per kategori ala SINTA: [judul kolom, atribut model].
     * Kategori kosong/produk memakai kolom umum.
     *
     * @var array<string, array<int, array{0: string, 1: string}>>
     */
    private const KOLOM = [
        'artikel' => [
            ['Judul / Nama Jurnal', 'nama_jurnal'],
            ['ISSN', 'issn'],
            ['Volume', 'volume'],
            ['Nomor', 'nomor_edisi'],
            ['Halaman', 'halaman_jurnal'],
            ['DOI', 'doi'],
            ['Indeksasi', 'indeksasi'],
            ['Tanggal Terbit', 'tanggal_terbit'],
            ['Tautan', 'tautan_jurnal'],
        ],
        'buku' => [
            ['Judul Buku', 'judul_buku'],
            ['Penerbit', 'penerbit_buku'],
            ['ISBN', 'isbn'],
            ['Jenis Buku', 'jenis_buku'],
            ['Bidang Ilmu', 'bidang_ilmu'],
            ['Halaman', 'halaman_buku'],
            ['Tanggal Terbit', 'tanggal_terbit'],
            ['Tautan', 'tautan_buku'],
        ],
        'hki' => [
            ['Judul', 'judul_hak_cipta'],
            ['Jenis HKI', 'jenis_hki'],
            ['No. Pendaftaran', 'nomor_pendaftaran_hki'],
            ['No. Sertifikat', 'nomor_sertifikat_hki'],
            ['Tanggal Pendaftaran', 'tanggal_pendaftaran_hki'],
            ['Tanggal Terbit Sertifikat', 'tanggal_terbit_sertifikat_hki'],
            ['Pemegang', 'pemegang_hki'],
            ['Status', 'status_hki'],
            ['Tautan', 'tautan_hki'],
        ],
        'seminar' => [
            ['Nama Seminar', 'nama_seminar'],
            ['Nama Prosiding', 'nama_prosiding'],
            ['Penyelenggara', 'penyelenggara'],
            ['Lokasi', 'lokasi_seminar'],
            ['Tanggal Seminar', 'tanggal_seminar'],
            ['ISBN Prosiding', 'isbn_prosiding'],
            ['DOI Prosiding', 'doi_prosiding'],
            ['Halaman', 'halaman_prosiding'],
            ['Tautan', 'tautan_seminar'],
        ],
        'produk' => [
            ['Nama Produk', 'nama_produk'],
            ['Jenis Produk', 'jenis_produk'],
            ['Status Pengembangan', 'status_pengembangan'],
            ['TKT', 'tingkat_kesiapanterapan'],
            ['Tanggal Pembuatan', 'tanggal_pembuatan'],
            ['Tautan', 'tautan_produk'],
        ],
    ];

    private int $nomor = 0;

    /**
     * @param  Builder<Luaran>  $query
     */
    public function __construct(
        private readonly Builder $query,
        private readonly ?string $kategori = null,
    ) {}

    /**
     * @return Builder<Luaran>
     */
    public function query(): Builder
    {
        return $this->query
            ->with(['dosen', 'jenisLuaran', 'kegiatan'])
            ->orderByDesc('id_luaran');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $khusus = $this->kategori !== null && isset(self::KOLOM[$this->kategori])
            ? array_column(self::KOLOM[$this->kategori], 0)
            : ['Judul'];

        return array_merge(['No', 'Jenis Luaran', 'Penulis'], $khusus, ['Tahun', 'Kegiatan Terkait']);
    }

    /**
     * @param  Luaran  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $jenis = $row->jenisLuaran;
        $namaJenis = trim(($jenis?->nama_jenis_luaran ?? '').($jenis?->indeks ? ' ('.$jenis->indeks.')' : ''));

        if ($this->kategori !== null && isset(self::KOLOM[$this->kategori])) {
            $khusus = array_map(fn (array $kolom) => $this->nilai($row, $kolom[1]), self::KOLOM[$this->kategori]);
        } else {
            $khusus = [$this->judul($row)];
        }

        return array_merge(
            [++$this->nomor, $namaJenis !== '' ? $namaJenis : null, $row->dosen?->nama_dosen],
            $khusus,
            [$this->tahun($row), $row->kegiatan?->judul_kegiatan],
        );
    }

    private function judul(Luaran $luaran): ?string
    {
        foreach (['judul_buku', 'nama_jurnal', 'nama_prosiding', 'nama_seminar', 'judul_hak_cipta', 'nama_produk'] as $atribut) {
            if (filled($luaran->{$atribut})) {
                return (string) $luaran->{$atribut};
            }
        }

        return null;
    }

    private function nilai(Luaran $luaran, string $atribut): mixed
    {
        $nilai = $luaran->{$atribut};

        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        return filled($nilai) ? $nilai : null;
    }

    private function tahun(Luaran $luaran): mixed
    {
        if ($luaran->tahun_terbit) {
            return $luaran->tahun_terbit;
        }

        foreach (['tanggal_terbit', 'tanggal_seminar', 'tanggal_terbit_sertifikat_hki', 'tanggal_pendaftaran_hki', 'tanggal_pembuatan'] as $atribut) {
            if ($luaran->{$atribut} instanceof \DateTimeInterface) {
                return (int) $luaran->{$atribut}->format('Y');
            }
        }

        return null;
    }
}
