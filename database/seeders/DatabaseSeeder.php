<?php

namespace Database\Seeders;

use App\Models\BidangKeahlian;
use App\Models\JabatanAkademik;
use App\Models\JenisKegiatan;
use App\Models\JenisKerjaSama;
use App\Models\JenisLuaran;
use App\Models\Pendidikan;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Akun Pengguna Default
        User::firstOrCreate(
            ['email' => 'admin@polines.ac.id'],
            [
                'name' => 'Administrator K2',
                'password' => Hash::make('password123'),
                'role' => 'ADMINISTRATOR',
            ]
        );

        User::firstOrCreate(
            ['email' => 'kaprodi.trpl@polines.ac.id'],
            [
                'name' => 'Kaprodi TRPL',
                'password' => Hash::make('password123'),
                'role' => 'KAPRODI',
            ]
        );

        User::firstOrCreate(
            ['email' => 'dosen@polines.ac.id'],
            [
                'name' => 'Dosen Peneliti',
                'password' => Hash::make('password123'),
                'role' => 'DOSEN',
            ]
        );

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
            JenisKerjaSamaSeeder::class,
            JenisKegiatanSeeder::class,
            JenisLuaranSeeder::class,
        ]);

        if (\Illuminate\Support\Facades\Schema::hasTable('jenis_hki')) {
            $this->call(JenisHkiSeeder::class);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('jenis_pkm')) {
            $this->call(JenisPkmSeeder::class);
        }
    }
}
