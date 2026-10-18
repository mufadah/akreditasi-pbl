<?php

namespace Database\Seeders;

use App\Models\KonfigurasiMetrik;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class KonfigurasiMetrikSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('konfigurasi_metrik')) {
            return;
        }

        $metrikList = [
            [
                'kode_indikator' => 'SDM-01',
                'nama_indikator' => 'Kecukupan Dosen Tetap Perguruan Tinggi (DTPS)',
                'bidang' => 'SDM',
                'rumus_formula' => 'COUNT(dosen_dtps)',
                'ambang_target' => 12.00,
                'jenjang' => 'S1',
            ],
            [
                'kode_indikator' => 'SDM-02',
                'nama_indikator' => 'Kualifikasi Akademik Dosen Tetap (Doktor/S3)',
                'bidang' => 'SDM',
                'rumus_formula' => '(COUNT(dosen_s3) / COUNT(dosen_dtps)) * 100',
                'ambang_target' => 3.50,
                'jenjang' => 'S1',
            ],
            [
                'kode_indikator' => 'SDM-03',
                'nama_indikator' => 'Jabatan Akademik Dosen Tetap (Lektor Kepala/Guru Besar)',
                'bidang' => 'SDM',
                'rumus_formula' => '(COUNT(dosen_lk_gb) / COUNT(dosen_dtps)) * 100',
                'ambang_target' => 3.50,
                'jenjang' => 'S1',
            ],
            [
                'kode_indikator' => 'PEN-01',
                'nama_indikator' => 'Produktivitas Penelitian DTPS per Tahun',
                'bidang' => 'Penelitian',
                'rumus_formula' => 'COUNT(penelitian) / COUNT(dosen_dtps)',
                'ambang_target' => 1.00,
                'jenjang' => 'S1',
            ],
            [
                'kode_indikator' => 'PKM-01',
                'nama_indikator' => 'Produktivitas PkM DTPS per Tahun',
                'bidang' => 'PkM',
                'rumus_formula' => 'COUNT(pkm) / COUNT(dosen_dtps)',
                'ambang_target' => 0.50,
                'jenjang' => 'S1',
            ],
            [
                'kode_indikator' => 'KS-01',
                'nama_indikator' => 'Kerja Sama Tridharma Aktif (Internasional, Nasional, Lokal)',
                'bidang' => 'Kerja Sama',
                'rumus_formula' => 'COUNT(kerjasama_aktif)',
                'ambang_target' => 3.50,
                'jenjang' => 'S1',
            ],
        ];

        foreach ($metrikList as $metrik) {
            KonfigurasiMetrik::updateOrCreate(
                ['kode_indikator' => $metrik['kode_indikator']],
                $metrik
            );
        }
    }
}
