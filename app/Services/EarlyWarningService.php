<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\KonfigurasiMetrik;
use App\Models\Luaran;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EarlyWarningService
{
    private const JENIS_PUBLIKASI = ['jurnal', 'prosiding', 'seminar', 'buku'];

    public function __construct(private K1ApiService $k1) {}

    public function hitung($param = [])
    {
        $param += [
            'tahun_tanpa_penelitian' => 2,
            'tahun_tanpa_publikasi' => 3,
            'bulan_sertifikasi' => 6,
            'rasio_maks' => null,
            'tahun' => today()->year,
            'prodi' => 'Teknologi Rekayasa Komputer',
        ];
        $param['tahun_tanpa_penelitian'] = (int) $param['tahun_tanpa_penelitian'];
        $param['tahun_tanpa_publikasi'] = (int) $param['tahun_tanpa_publikasi'];
        $param['bulan_sertifikasi'] = (int) $param['bulan_sertifikasi'];
        $param['tahun'] = (int) $param['tahun'];
        $param['rasio_maks'] = $param['rasio_maks'] === null ? null : (float) $param['rasio_maks'];

        $dosen = Dosen::query()
            ->where('status_dosen', 'DTPS')
            ->orderBy('nama_dosen')
            ->get(['id_dosen', 'nama_dosen', 'nidn'])
            ->keyBy('id_dosen');

        $ids = $dosen->keys()->all();

        $indikator = [
            $this->tanpaPenelitian($dosen, $ids, $param['tahun_tanpa_penelitian']),
            $this->tanpaPublikasi($dosen, $ids, $param['tahun_tanpa_publikasi']),
            $this->sertifikasi($dosen, $ids, $param['bulan_sertifikasi']),
            $this->rasio($dosen->count(), $param),
        ];

        $perIndikator = collect($indikator)->pluck('jumlah_peringatan', 'kode')->all();

        return [
            'tanggal_hitung' => today()->toDateString(),
            'parameter' => $param,
            'total_dosen' => $dosen->count(),
            'ringkasan' => [
                'total_peringatan' => array_sum($perIndikator),
                'per_indikator' => $perIndikator,
            ],
            'indikator' => $indikator,
        ];
    }

    private function tanpaPenelitian($dosen, $ids, $tahun)
    {
        $batas = today()->year - $tahun + 1;

        $terakhir = DB::table('anggota_kegiatan as a')
            ->join('kegiatan as k', 'k.id_kegiatan', '=', 'a.id_kegiatan')
            ->join('jenis_kegiatan as j', 'j.id_jenis_kegiatan', '=', 'k.id_jenis_kegiatan')
            ->whereIn('a.id_dosen', $ids)
            ->whereNotNull('k.tahun_kegiatan')
            ->whereRaw('LOWER(j.nama_jenis_kegiatan) = ?', ['penelitian'])
            ->groupBy('a.id_dosen')
            ->selectRaw('a.id_dosen, MAX(k.tahun_kegiatan) as tahun_terakhir')
            ->pluck('tahun_terakhir', 'id_dosen');

        $daftar = $dosen
            ->filter(fn ($d) => ($terakhir[$d->id_dosen] ?? 0) < $batas)
            ->map(fn ($d) => [
                'id_dosen' => $d->id_dosen,
                'nama_dosen' => $d->nama_dosen,
                'nidn' => $d->nidn,
                'tahun_terakhir' => isset($terakhir[$d->id_dosen]) ? (int) $terakhir[$d->id_dosen] : null,
            ])
            ->values()
            ->all();

        return $this->indikator('EW-PENELITIAN', "Dosen tanpa penelitian dalam {$tahun} tahun terakhir", $daftar);
    }

    private function tanpaPublikasi($dosen, $ids, $tahun)
    {
        $batas = today()->year - $tahun + 1;

        $luaran = Luaran::query()
            ->whereIn('id_dosen', $ids)
            ->whereHas('jenisLuaran', function ($q) {
                $q->where(function ($w) {
                    foreach (self::JENIS_PUBLIKASI as $kata) {
                        $w->orWhereRaw('LOWER(nama_jenis_luaran) LIKE ?', ["%{$kata}%"]);
                    }
                });
            })
            ->with('kegiatan:id_kegiatan,tahun_kegiatan')
            ->get(['id_luaran', 'id_dosen', 'id_kegiatan', 'tanggal_terbit', 'tahun_terbit', 'tanggal_seminar', 'created_at']);

        $terakhir = [];

        foreach ($luaran as $item) {
            $tahunLuaran = $item->tanggal_terbit?->year
                ?? $item->tahun_terbit
                ?? $item->tanggal_seminar?->year
                ?? $item->kegiatan?->tahun_kegiatan
                ?? $item->created_at->year;

            $terakhir[$item->id_dosen] = max($terakhir[$item->id_dosen] ?? 0, (int) $tahunLuaran);
        }

        $daftar = $dosen
            ->filter(fn ($d) => ($terakhir[$d->id_dosen] ?? 0) < $batas)
            ->map(fn ($d) => [
                'id_dosen' => $d->id_dosen,
                'nama_dosen' => $d->nama_dosen,
                'nidn' => $d->nidn,
                'tahun_terakhir' => $terakhir[$d->id_dosen] ?? null,
            ])
            ->values()
            ->all();

        return $this->indikator('EW-PUBLIKASI', "Dosen tanpa publikasi dalam {$tahun} tahun terakhir", $daftar);
    }

    private function sertifikasi($dosen, $ids, $bulan)
    {
        $batas = today()->addMonths($bulan);

        $grup = DB::table('sertifikasi')
            ->whereIn('id_dosen', $ids)
            ->where('status_sertifikasi', 'Aktif')
            ->get(['id_sertifikasi', 'id_dosen', 'jenis_sertifikasi', 'tanggal_kedaluwarsa'])
            ->groupBy(fn ($row) => $row->id_dosen.'|'.$row->jenis_sertifikasi);

        $daftar = [];

        foreach ($grup as $baris) {
            $masihAman = $baris->contains(
                fn ($row) => $row->tanggal_kedaluwarsa === null || Carbon::parse($row->tanggal_kedaluwarsa)->gt($batas)
            );

            if ($masihAman) {
                continue;
            }

            $terbaru = $baris->sortByDesc('tanggal_kedaluwarsa')->first();
            $kedaluwarsa = Carbon::parse($terbaru->tanggal_kedaluwarsa)->startOfDay();
            $sisaHari = (int) today()->diffInDays($kedaluwarsa, false);
            $d = $dosen[$terbaru->id_dosen];

            $daftar[] = [
                'id_dosen' => $d->id_dosen,
                'nama_dosen' => $d->nama_dosen,
                'nidn' => $d->nidn,
                'id_sertifikasi' => $terbaru->id_sertifikasi,
                'jenis_sertifikasi' => $terbaru->jenis_sertifikasi,
                'tanggal_kedaluwarsa' => $kedaluwarsa->toDateString(),
                'sisa_hari' => $sisaHari,
                'status' => $sisaHari < 0 ? 'sudah_kedaluwarsa' : 'segera_kedaluwarsa',
            ];
        }

        usort($daftar, fn ($a, $b) => $a['sisa_hari'] <=> $b['sisa_hari']);

        return $this->indikator('EW-SERTIFIKASI', "Sertifikasi dosen berakhir dalam {$bulan} bulan atau sudah kedaluwarsa", $daftar);
    }

    private function rasio($totalDtps, $param)
    {
        $batas = $param['rasio_maks'] ?? KonfigurasiMetrik::where('kode_indikator', 'EW-RASIO')->value('ambang_target');
        $batas = $batas === null ? null : (float) $batas;

        try {
            $totalMahasiswa = $this->k1->getStatistikMahasiswa(['tahun' => $param['tahun'], 'prodi' => $param['prodi']])['total_mahasiswa'] ?? null;
        } catch (\Throwable) {
            $totalMahasiswa = null;
        }

        $rasio = $totalMahasiswa !== null && $totalDtps > 0 ? round($totalMahasiswa / $totalDtps, 2) : null;

        $status = match (true) {
            $batas === null => 'batas_belum_diatur',
            $rasio === null => 'data_tidak_tersedia',
            $rasio > $batas => 'melebihi_batas',
            default => 'aman',
        };

        return [
            'kode' => 'EW-RASIO',
            'nama' => 'Rasio mahasiswa terhadap dosen tetap melebihi batas',
            'jumlah_peringatan' => $status === 'melebihi_batas' ? 1 : 0,
            'status' => $status,
            'rasio_dosen_mahasiswa' => $rasio,
            'batas_maksimal' => $batas,
            'total_mahasiswa' => $totalMahasiswa,
            'total_dtps' => $totalDtps,
            'daftar' => [],
        ];
    }

    private function indikator($kode, $nama, $daftar)
    {
        return [
            'kode' => $kode,
            'nama' => $nama,
            'jumlah_peringatan' => count($daftar),
            'daftar' => $daftar,
        ];
    }
}