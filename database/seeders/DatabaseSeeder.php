<?php

namespace Database\Seeders;

use App\Models\BidangKeahlian;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\JenisKegiatan;
use App\Models\JenisKerjaSama;
use App\Models\JenisLuaran;
use App\Models\Pendidikan;
use App\Models\TahunAkademik;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Jabatan Fungsional
        foreach (['Tenaga Pengajar', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'] as $j) {
            JabatanAkademik::firstOrCreate(['nama_jabatan' => $j]);
        }

        // 2. Data Pendidikan
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

        // 3. Bidang Keahlian
        foreach (['Artificial Intelligence', 'Internet of Things', 'Cybersecurity', 'Software Engineering', 'Cloud Computing'] as $bk) {
            BidangKeahlian::firstOrCreate(['nama_bidang' => $bk]);
        }

        // 4. Jenis Kerja Sama (MoU, MoA, IA)
        foreach (['MoU (Memorandum of Understanding)', 'MoA (Memorandum of Agreement)', 'IA (Implementation Arrangement)'] as $jks) {
            JenisKerjaSama::firstOrCreate(['nama_jenis_kerjasama' => $jks]);
        }

        // 5. Jenis Kegiatan (Penelitian & PkM)
        JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => 'Penelitian', 'deskripsi' => 'Kegiatan Penelitian Dosen & Mahasiswa']);
        JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => 'PkM', 'deskripsi' => 'Pengabdian kepada Masyarakat']);

        // 6. Jenis Luaran
        JenisLuaran::firstOrCreate(['nama_jenis_luaran' => 'Jurnal Internasional Bereputasi', 'indeks' => 'Scopus']);
        JenisLuaran::firstOrCreate(['nama_jenis_luaran' => 'Jurnal Nasional Terakreditasi', 'indeks' => 'SINTA']);
        JenisLuaran::firstOrCreate(['nama_jenis_luaran' => 'Prosiding Seminar Internasional', 'indeks' => 'IEEE/Scopus']);
        JenisLuaran::firstOrCreate(['nama_jenis_luaran' => 'Hak Cipta / HKI', 'indeks' => 'DJKI']);
        JenisLuaran::firstOrCreate(['nama_jenis_luaran' => 'Buku Ajar / Monograf', 'indeks' => 'ISBN']);
        JenisLuaran::firstOrCreate(['nama_jenis_luaran' => 'Produk Teknologi / Prototipe', 'indeks' => 'TKT']);

        // 7. Tahun Akademik
        TahunAkademik::firstOrCreate(['tahun_akademik' => 2025]);
        TahunAkademik::firstOrCreate(['tahun_akademik' => 2026]);

        // 8. Master Data Baru & Seeder Idempotent (Revisi ERD)
        $this->call([
            JenisHkiSeeder::class,
            JenisPkmSeeder::class,
            JenisKerjaSamaSeeder::class,
            JenisKegiatanSeeder::class,
            JenisLuaranSeeder::class,
        ]);

        // 9. Data Akademik Dosen Terkait Akun K1 (Tabel dosen, bukan users)
        $jabatan = JabatanAkademik::first();
        $pendidikan = Pendidikan::first();
        if ($jabatan && $pendidikan) {
            Dosen::firstOrCreate(
                ['email' => 'dosen@example.com'],
                [
                    'id_jabatan' => $jabatan->id_jabatan,
                    'id_pendidikan' => $pendidikan->id_pendidikan,
                    'nidn' => '0012345678',
                    'nama_dosen' => 'Dosen K1 SSO',
                    'status_dosen' => 'DTPS',
                ]
            );
        }
    }
}
