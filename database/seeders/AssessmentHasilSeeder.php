<?php

namespace Database\Seeders;

use App\Models\AssessmentHasil;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AssessmentHasilSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('assessment_hasil')) {
            return;
        }

        $hasilList = [
            [
                'kode_indikator' => 'SDM-01',
                'versi_instrumen' => 'LAM INFOKOM 2025 v1.2',
                'nilai_metrik' => 14.00,
                'skor_capaian' => 4.00,
                'skor_target' => 3.50,
                'status' => 'Terpenuhi',
                'gap' => 0.00,
                'rekomendasi' => 'Jumlah Dosen Tetap Perguruan Tinggi (DTPS) telah melampaui ambang batas minimal akreditasi Unggul.',
                'dikirim_ke_k3_at' => now(),
            ],
            [
                'kode_indikator' => 'SDM-02',
                'versi_instrumen' => 'LAM INFOKOM 2025 v1.2',
                'nilai_metrik' => 42.86,
                'skor_capaian' => 3.60,
                'skor_target' => 3.50,
                'status' => 'Terpenuhi',
                'gap' => 0.00,
                'rekomendasi' => 'Persentase dosen berpendidikan S3/Doktor telah memenuhi syarat akreditasi Unggul.',
                'dikirim_ke_k3_at' => now(),
            ],
            [
                'kode_indikator' => 'SDM-03',
                'versi_instrumen' => 'LAM INFOKOM 2025 v1.2',
                'nilai_metrik' => 28.57,
                'skor_capaian' => 2.80,
                'skor_target' => 3.50,
                'status' => 'Gap Terdeteksi',
                'gap' => -0.70,
                'rekomendasi' => 'Perlu percepatan usulan kenaikan jabatan akademik fungsional dari Lektor menjadi Lektor Kepala bagi 2 dosen.',
                'dikirim_ke_k3_at' => now(),
            ],
            [
                'kode_indikator' => 'PEN-01',
                'versi_instrumen' => 'LAM INFOKOM 2025 v1.2',
                'nilai_metrik' => 1.25,
                'skor_capaian' => 3.75,
                'skor_target' => 3.50,
                'status' => 'Terpenuhi',
                'gap' => 0.00,
                'rekomendasi' => 'Produktivitas riset dosen per tahun sudah mencapai target rasio 1 penelitian per dosen.',
                'dikirim_ke_k3_at' => now(),
            ],
            [
                'kode_indikator' => 'PKM-01',
                'versi_instrumen' => 'LAM INFOKOM 2025 v1.2',
                'nilai_metrik' => 0.65,
                'skor_capaian' => 3.50,
                'skor_target' => 3.50,
                'status' => 'Terpenuhi',
                'gap' => 0.00,
                'rekomendasi' => 'Rata-rata kegiatan Pengabdian kepada Masyarakat memenuhi ambang batas target akreditasi.',
                'dikirim_ke_k3_at' => now(),
            ],
            [
                'kode_indikator' => 'KS-01',
                'versi_instrumen' => 'LAM INFOKOM 2025 v1.2',
                'nilai_metrik' => 4.00,
                'skor_capaian' => 3.80,
                'skor_target' => 3.50,
                'status' => 'Terpenuhi',
                'gap' => 0.00,
                'rekomendasi' => 'Kerja sama tridharma internasional dan nasional aktif berjalan dengan baik dan memiliki bukti implementasi nyata (IA).',
                'dikirim_ke_k3_at' => now(),
            ],
        ];

        foreach ($hasilList as $hasil) {
            AssessmentHasil::firstOrCreate(
                [
                    'kode_indikator' => $hasil['kode_indikator'],
                    'versi_instrumen' => $hasil['versi_instrumen'],
                ],
                $hasil
            );
        }
    }
}
