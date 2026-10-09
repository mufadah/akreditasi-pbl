<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JabatanAkademik;
use App\Models\Pendidikan;
use App\Models\JenisPenelitian;
use App\Models\JenisPublikasi;
use App\Models\TahunAkademik;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Jabatan Fungsional
        foreach (['Tenaga Pengajar', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'] as $j) {
            JabatanAkademik::firstOrCreate(['nama_jabatan' => $j]);
        }

        // 2. Data Pendidikan (karena butuh universitas, prodi, dan tahun_lulus)
        $dataPendidikan = [
            [
                'jenjang' => 'S2',
                'universitas' => 'Institut Teknologi Bandung',
                'program_studi' => 'Teknik Elektro dan Informatika',
                'tahun_lulus' => 2020,
            ],
            [
                'jenjang' => 'S2',
                'universitas' => 'Universitas Gadjah Mada',
                'program_studi' => 'Ilmu Komputer',
                'tahun_lulus' => 2021,
            ],
            [
                'jenjang' => 'S3',
                'universitas' => 'Universitas Diponegoro',
                'program_studi' => 'Teknik Sistem Komputer',
                'tahun_lulus' => 2023,
            ],
            [
                'jenjang' => 'D4/S1',
                'universitas' => 'Politeknik Negeri Semarang',
                'program_studi' => 'Teknik Telekomunikasi',
                'tahun_lulus' => 2018,
            ],
        ];

        foreach ($dataPendidikan as $p) {
            Pendidikan::firstOrCreate(
                [
                    'jenjang' => $p['jenjang'],
                    'universitas' => $p['universitas'],
                    'program_studi' => $p['program_studi'],
                ],
                $p
            );
        }

        // 3. Jenis Penelitian
        foreach (['Penelitian Terapan', 'Penelitian Dasar', 'Pengembangan', 'Mandiri'] as $jp) {
            JenisPenelitian::firstOrCreate(['nama_jenis_penelitian' => $jp]);
        }

        // 4. Jenis Publikasi
        foreach (['Jurnal Internasional Bereputasi', 'Jurnal Nasional (SINTA)', 'Prosiding Seminar'] as $pub) {
            JenisPublikasi::firstOrCreate(['nama_jenis_publikasi' => $pub]);
        }

        // 5. Tahun Akademik
        TahunAkademik::firstOrCreate(['tahun_akademik' => 2025]);

        // 6. Master Data Baru (Revisi ERD)
        $this->call([
            JenisHkiSeeder::class,
            JenisPkmSeeder::class,
            JenisKerjaSamaSeeder::class,
        ]);
    }
}