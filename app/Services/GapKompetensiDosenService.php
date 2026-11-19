<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\KonfigurasiMetrik;
use Illuminate\Support\Facades\DB;

class GapKompetensiDosenService
{
    private const JABATAN_LEKTOR_KE_ATAS = ['lektor', 'lektor kepala', 'guru besar'];

    private const DEFINISI = [
        'S3' => 'Dosen berpendidikan Doktor (S3)',
        'LEKTOR' => 'Dosen berjabatan Lektor ke atas',
        'SERTIFIKASI' => 'Dosen dengan sertifikasi aktif',
    ];

    public function hitung($jenjang = 'S1', $targetManual = [])
    {
        $populasi = fn () => Dosen::query()->where('status_dosen', 'DTPS');
        $total = $populasi()->count();

        $jumlah = [
            'S3' => $populasi()
                ->whereHas('pendidikan', fn ($q) => $q->where('jenjang', 'S3'))
                ->count(),
            'LEKTOR' => $populasi()
                ->whereHas('jabatanAkademik', fn ($q) => $q->whereIn(DB::raw('LOWER(nama_jabatan)'), self::JABATAN_LEKTOR_KE_ATAS))
                ->count(),
            'SERTIFIKASI' => $populasi()
                ->whereHas('sertifikasi', fn ($q) => $q
                    ->where('status_sertifikasi', 'Aktif')
                    ->where(fn ($w) => $w->whereNull('tanggal_kedaluwarsa')->orWhereDate('tanggal_kedaluwarsa', '>=', today())))
                ->count(),
        ];

        $indikator = [];

        foreach (self::DEFINISI as $kunci => $nama) {
            $indikator[] = $this->indikator(
                "GAP-{$kunci}-{$jenjang}",
                $nama,
                $jumlah[$kunci],
                $total,
                $this->target($kunci, $jenjang, $targetManual)
            );
        }

        $status = array_column($indikator, 'status');

        return [
            'jenjang' => $jenjang,
            'populasi' => 'Dosen tetap (DTPS)',
            'total_dosen' => $total,
            'tanggal_hitung' => today()->toDateString(),
            'indikator' => $indikator,
            'ringkasan' => [
                'terpenuhi' => count(array_keys($status, 'terpenuhi')),
                'belum_terpenuhi' => count(array_keys($status, 'belum_terpenuhi')),
                'standar_belum_diatur' => count(array_keys($status, 'standar_belum_diatur')),
            ],
        ];
    }

    private function indikator($kode, $nama, $jumlah, $total, $target)
    {
        $persentase = $total > 0 ? round($jumlah / $total * 100, 2) : 0.0;

        return [
            'kode' => $kode,
            'nama' => $nama,
            'jumlah_dosen' => $jumlah,
            'persentase' => $persentase,
            'standar_persen' => $target,
            'selisih_persen' => $target === null ? null : round($persentase - $target, 2),
            'kekurangan_dosen' => $target === null ? null : max(0, (int) ceil(round($target * $total / 100, 6)) - $jumlah),
            'status' => $target === null ? 'standar_belum_diatur' : ($persentase >= $target ? 'terpenuhi' : 'belum_terpenuhi'),
        ];
    }

    private function target($kunci, $jenjang, $targetManual)
    {
        if (($targetManual[$kunci] ?? null) !== null) {
            return (float) $targetManual[$kunci];
        }

        $nilai = KonfigurasiMetrik::where('kode_indikator', "GAP-{$kunci}-{$jenjang}")->value('ambang_target');

        return $nilai === null ? null : (float) $nilai;
    }
}