<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\Luaran;
use Illuminate\Support\Facades\DB;

class AssessmentKegiatanService
{
    public function __construct(private K3ApiService $k3) {}

    public function hitung($bidang, $tahun, $jenjang = 'S1')
    {
        $konteks = $this->konteks($bidang, $tahun);
        $indikator = [];

        foreach (config("assessment.{$bidang}", []) as $kode => $definisi) {
            $indikator[] = $this->nilaiIndikator($kode, $definisi, $konteks, $jenjang);
        }

        $totalBobot = array_sum(array_column($indikator, 'bobot'));
        $totalTertimbang = array_sum(array_map(fn ($item) => $item['skor'] * $item['bobot'], $indikator));

        return [
            'bidang' => $bidang,
            'tahun' => (int) $tahun,
            'jenjang' => $jenjang,
            'total_dtps' => $konteks['total_dtps'],
            'sumber_rubrik' => 'konfigurasi_lokal_sementara',
            'ringkasan' => [
                'nilai_bidang' => $totalBobot > 0 ? round($totalTertimbang / $totalBobot, 2) : 0.0,
                'skala' => '0 - 4',
                'total_indikator' => count($indikator),
                'indikator_terpenuhi' => count(array_filter($indikator, fn ($item) => $item['status'] === 'Terpenuhi')),
                'gap_terdeteksi' => count(array_filter($indikator, fn ($item) => $item['status'] === 'Gap Terdeteksi')),
            ],
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

    private function nilaiIndikator($kode, $definisi, $konteks, $jenjang)
    {
        $nilai = $this->nilaiMetrik($definisi['metrik'], $konteks);
        $rubrik = collect($definisi['rubrik'])->sortByDesc('min')->values();

        $tingkat = $rubrik->first(fn ($level) => $nilai >= $level['min']);
        $skor = $tingkat['skor'] ?? 0;

        $target = (float) $this->k3->getAmbangRubrik($kode, $jenjang);
        $terpenuhi = $skor >= $target;

        $dibutuhkan = $terpenuhi
            ? null
            : $rubrik->filter(fn ($level) => $level['skor'] >= $target)->sortBy('skor')->first();

        $rekomendasi = match (true) {
            $terpenuhi => 'Indikator memenuhi target rubrik.',
            $dibutuhkan !== null => "Naikkan {$definisi['satuan']} dari {$nilai} menjadi minimal {$dibutuhkan['min']} untuk mencapai skor {$dibutuhkan['skor']}.",
            default => 'Target melebihi skor maksimum pada rubrik.',
        };

        return [
            'kode' => $kode,
            'nama' => $definisi['nama'],
            'metrik' => $definisi['metrik'],
            'satuan' => $definisi['satuan'],
            'nilai_metrik' => $nilai,
            'bobot' => $definisi['bobot'],
            'skor' => $skor,
            'skor_tertimbang' => round($skor * $definisi['bobot'], 2),
            'skor_target' => $target,
            'status' => $terpenuhi ? 'Terpenuhi' : 'Gap Terdeteksi',
            'gap' => $terpenuhi ? 0.0 : round($target - $skor, 2),
            'level_dibutuhkan' => $dibutuhkan,
            'rekomendasi' => $rekomendasi,
        ];
    }
}