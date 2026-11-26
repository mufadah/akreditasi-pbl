<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AssessmentKerjaSamaService
{
    private const STATUS_DIKECUALIKAN = ['Non-aktif', 'Sedang proses'];

    public function __construct(private AssessmentSkorService $skor) {}

    public function hitung($tahun, $jenjang = 'S1')
    {
        $konteks = $this->konteks($tahun);
        $indikator = [];

        foreach (config('assessment.kerjasama', []) as $kode => $definisi) {
            $indikator[] = $this->skor->nilaiIndikator($kode, $definisi, $this->nilaiMetrik($definisi['metrik'], $konteks), $jenjang);
        }

        return [
            'bidang' => 'kerjasama',
            'tahun' => (int) $tahun,
            'jenjang' => $jenjang,
            'sumber_rubrik' => 'konfigurasi_lokal_sementara',
            'rincian' => $konteks,
            'catatan_data' => [
                'kriteria_mahasiswa_terlibat' => 'Belum dinilai: data kerja sama belum menyimpan mahasiswa terlibat secara terstruktur.',
            ],
            'ringkasan' => $this->skor->ringkasan($indikator),
            'indikator' => $indikator,
        ];
    }

    private function konteks($tahun)
    {
        $awal = "{$tahun}-01-01";
        $akhir = "{$tahun}-12-31";

        $baris = DB::table('kerja_sama')
            ->where('tanggal_mulai', '<=', $akhir)
            ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $awal))
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', self::STATUS_DIKECUALIKAN))
            ->get(['id_kerja_sama', 'id_mitra', 'tingkat']);

        $jumlah = ['internasional' => 0, 'nasional' => 0, 'lainnya' => 0];

        foreach ($baris as $item) {
            $tingkat = strtolower((string) $item->tingkat);

            $kunci = match (true) {
                str_contains($tingkat, 'internasional') => 'internasional',
                str_contains($tingkat, 'nasional') => 'nasional',
                default => 'lainnya',
            };

            $jumlah[$kunci]++;
        }

        return [
            'total_berlaku' => $baris->count(),
            'internasional' => $jumlah['internasional'],
            'nasional' => $jumlah['nasional'],
            'lainnya' => $jumlah['lainnya'],
            'mitra_unik' => $baris->pluck('id_mitra')->unique()->count(),
        ];
    }

    private function nilaiMetrik($metrik, $konteks)
    {
        return match ($metrik) {
            'jumlah_internasional_nasional' => $konteks['internasional'] + $konteks['nasional'],
            default => 0,
        };
    }
}