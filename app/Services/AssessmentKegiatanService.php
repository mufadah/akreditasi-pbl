<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\Luaran;
use Illuminate\Support\Facades\DB;

class AssessmentKegiatanService
{
    public function __construct(private AssessmentSkorService $skor) {}

    public function hitung($bidang, $tahun, $jenjang = 'S1')
    {
        $konteks = $this->konteks($bidang, $tahun);
        $indikator = [];

        foreach (config("assessment.{$bidang}", []) as $kode => $definisi) {
            $indikator[] = $this->skor->nilaiIndikator($kode, $definisi, $this->nilaiMetrik($definisi['metrik'], $konteks), $jenjang);
        }

        return [
            'bidang' => $bidang,
            'tahun' => (int) $tahun,
            'jenjang' => $jenjang,
            'total_dtps' => $konteks['total_dtps'],
            'sumber_rubrik' => 'konfigurasi_lokal_sementara',
            'ringkasan' => $this->skor->ringkasan($indikator),
            'indikator' => $indikator,
        ];
    }

    private function konteks($bidang, $tahun)
    {
        $ids = Dosen::query()->where('status_dosen', 'DTPS')->pluck('id_dosen')->all();

        $baris = DB::table('anggota_kegiatan as a')
            ->join('kegiatan as k', 'k.id_kegiatan', '=', 'a.id_kegiatan')
            ->join('jenis_kegiatan as j', 'j.id_jenis_kegiatan', '=', 'k.id_jenis_kegiatan')
            ->whereIn('a.id_dosen', $ids)
            ->whereRaw('LOWER(j.nama_jenis_kegiatan) = ?', [$bidang])
            ->where('k.tahun_kegiatan', $tahun)
            ->select('k.id_kegiatan', 'a.id_dosen')
            ->distinct()
            ->get();

        $idKegiatan = $baris->pluck('id_kegiatan')->unique()->values()->all();

        return [
            'total_dtps' => count($ids),
            'jumlah_kegiatan' => count($idKegiatan),
            'dosen_terlibat' => $baris->pluck('id_dosen')->unique()->count(),
            'jumlah_luaran' => $idKegiatan ? Luaran::whereIn('id_kegiatan', $idKegiatan)->count() : 0,
            'total_dana' => $idKegiatan ? (float) DB::table('pendanaan_kegiatan')->whereIn('id_kegiatan', $idKegiatan)->sum('jumlah_dana') : 0.0,
        ];
    }

    private function nilaiMetrik($metrik, $konteks)
    {
        $total = $konteks['total_dtps'];

        if ($total === 0) {
            return 0.0;
        }

        return round(match ($metrik) {
            'rata_kegiatan' => $konteks['jumlah_kegiatan'] / $total,
            'persen_dosen_terlibat' => $konteks['dosen_terlibat'] / $total * 100,
            'rata_luaran' => $konteks['jumlah_luaran'] / $total,
            'rata_dana' => $konteks['total_dana'] / $total,
            default => 0,
        }, 2);
    }
}