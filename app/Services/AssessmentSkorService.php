<?php

namespace App\Services;

class AssessmentSkorService
{
    public function __construct(private K3ApiService $k3) {}

    public function nilaiIndikator($kode, $definisi, $nilai, $jenjang)
    {
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

    public function ringkasan($indikator)
    {
        $totalBobot = array_sum(array_column($indikator, 'bobot'));
        $totalTertimbang = array_sum(array_map(fn ($item) => $item['skor'] * $item['bobot'], $indikator));

        return [
            'nilai_bidang' => $totalBobot > 0 ? round($totalTertimbang / $totalBobot, 2) : 0.0,
            'skala' => '0 - 4',
            'total_indikator' => count($indikator),
            'indikator_terpenuhi' => count(array_filter($indikator, fn ($item) => $item['status'] === 'Terpenuhi')),
            'gap_terdeteksi' => count(array_filter($indikator, fn ($item) => $item['status'] === 'Gap Terdeteksi')),
        ];
    }
}