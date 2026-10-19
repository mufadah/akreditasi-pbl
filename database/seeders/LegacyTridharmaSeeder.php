<?php

namespace Database\Seeders;

use App\Models\AnggotaPenelitian;
use App\Models\AnggotaPkm;
use App\Models\Dosen;
use App\Models\JenisPenelitian;
use App\Models\JenisPublikasi;
use App\Models\PendanaanPenelitian;
use App\Models\Penelitian;
use App\Models\Pkm;
use App\Models\Publikasi;
use App\Models\TahunAkademik;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LegacyTridharmaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeder ini mengisi data model Tridharma jika tabel-tabelnya tersedia di basis data.
     */
    public function run(): void
    {
        $dosen = Schema::hasTable('dosen') ? Dosen::first() : null;
        $tahun = Schema::hasTable('tahun_akademik') ? TahunAkademik::first() : null;

        // 1. Jenis Penelitian
        if (Schema::hasTable('jenis_penelitian')) {
            foreach (['Penelitian Terapan', 'Penelitian Dasar', 'Penelitian Pengembangan'] as $jp) {
                JenisPenelitian::firstOrCreate(['nama_jenis_penelitian' => $jp]);
            }
        }

        // 2. Jenis Publikasi
        if (Schema::hasTable('jenis_publikasi')) {
            foreach (['Jurnal Internasional Bereputasi', 'Jurnal Nasional Terakreditasi', 'Prosiding Seminar Internasional'] as $jpub) {
                JenisPublikasi::firstOrCreate(['nama_jenis_publikasi' => $jpub]);
            }
        }

        // 3. Penelitian
        $penelitian = null;
        if (Schema::hasTable('penelitian') && $dosen && $tahun) {
            $jpFirst = Schema::hasTable('jenis_penelitian') ? JenisPenelitian::first() : null;
            $penelitian = Penelitian::firstOrCreate(
                ['judul_penelitian' => 'Optimasi Routing Jaringan Sensor Nirkabel Menggunakan Algoritma Genetika'],
                [
                    'id_dosen' => $dosen->id_dosen,
                    'id_jenis_penelitian' => $jpFirst?->id_jenis_penelitian ?? 1,
                    'id_tahun_akademik' => $tahun->id_tahun_akademik,
                    'tahun' => 2025,
                    'status' => 'Selesai',
                ]
            );

            // 4. Pendanaan Penelitian
            if (Schema::hasTable('pendanaan_penelitian') && $penelitian) {
                PendanaanPenelitian::firstOrCreate(
                    [
                        'id_penelitian' => $penelitian->id_penelitian,
                        'sumber_dana' => 'DIPA Internal',
                    ],
                    [
                        'jumlah_dana' => 15000000.00,
                        'tahun_pendanaan' => 2025,
                    ]
                );
            }

            // 5. Anggota Penelitian
            if (Schema::hasTable('anggota_penelitian') && $penelitian) {
                AnggotaPenelitian::firstOrCreate(
                    [
                        'id_penelitian' => $penelitian->id_penelitian,
                        'id_dosen' => $dosen->id_dosen,
                    ],
                    [
                        'jenis_anggota' => 'Dosen',
                        'peran' => 'Ketua',
                    ]
                );
            }
        }

        // 6. PkM
        $pkm = null;
        if (Schema::hasTable('pkm') && $dosen && $tahun) {
            $pkm = Pkm::firstOrCreate(
                ['judul_pkm' => 'Pelatihan Literasi Digital dan Keamanan Data bagi Siswa SMK'],
                [
                    'id_dosen' => $dosen->id_dosen,
                    'id_tahun_akademik' => $tahun->id_tahun_akademik,
                    'tahun' => 2025,
                    'status' => 'Selesai',
                ]
            );
        }

        // 7. Anggota PkM
        if (Schema::hasTable('anggota_pkm')) {
            $pkmId = $pkm ? $pkm->id_pkm : 1;
            // Jika ada relasi foreign key ke pkm, pastikan pkm ada
            if (! Schema::hasTable('pkm') || $pkm) {
                AnggotaPkm::firstOrCreate(
                    [
                        'id_pkm' => $pkmId,
                        'id_mahasiswa' => 230101001,
                    ],
                    [
                        'peran' => 'Anggota',
                        'semester' => 'Semester 5',
                    ]
                );
            }
        }

        // 8. Publikasi
        if (Schema::hasTable('publikasi') && $dosen) {
            $jpubFirst = Schema::hasTable('jenis_publikasi') ? JenisPublikasi::first() : null;
            Publikasi::firstOrCreate(
                ['judul' => 'Energy Efficient Wireless Sensor Network Clustering with Genetic Algorithm'],
                [
                    'id_dosen' => $dosen->id_dosen,
                    'id_jenis_publikasi' => $jpubFirst?->id_jenis_publikasi ?? 1,
                    'id_penelitian' => $penelitian?->id_penelitian,
                    'id_pkm' => null,
                    'nama_jurnal' => 'International Journal of Advanced Computer Science',
                    'tahun' => 2025,
                    'jumlah_sitasi' => 5,
                ]
            );
        }
    }
}
