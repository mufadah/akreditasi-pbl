<?php

namespace Database\Seeders;

use App\Models\AnggotaKegiatan;
use App\Models\Dosen;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\PendanaanKegiatan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class KegiatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('kegiatan')) {
            return;
        }

        $jenisPenelitian = JenisKegiatan::where('nama_jenis_kegiatan', 'like', '%Penelitian%')->first();
        $jenisPkm = JenisKegiatan::where('nama_jenis_kegiatan', 'like', '%PkM%')->first();

        if (! $jenisPenelitian) {
            $jenisPenelitian = JenisKegiatan::create(['nama_jenis_kegiatan' => 'Penelitian', 'deskripsi' => 'Kegiatan Penelitian Dosen & Mahasiswa']);
        }
        if (! $jenisPkm) {
            $jenisPkm = JenisKegiatan::create(['nama_jenis_kegiatan' => 'PkM', 'deskripsi' => 'Pengabdian kepada Masyarakat']);
        }

        $dosens = Dosen::all();
        if ($dosens->isEmpty()) {
            return;
        }

        $dosen1 = $dosens->first();
        $dosen2 = $dosens->skip(1)->first() ?? $dosen1;
        $dosen3 = $dosens->skip(2)->first() ?? $dosen1;
        $dosen4 = $dosens->skip(3)->first() ?? $dosen1;

        $daftarKegiatan = [
            // 1. Penelitian Selesai
            [
                'id_jenis_kegiatan' => $jenisPenelitian->id_jenis_kegiatan,
                'judul_kegiatan' => 'Rancang Bangun Sistem Monitoring Kualitas Air Tambak Udang Berbasis IoT dan Edge Computing',
                'afiliasi' => 'Jurusan Teknik Elektro / Teknik Informatika',
                'lokasi_kegiatan' => 'Kendal, Jawa Tengah',
                'litabmas_sebelumnya' => null,
                'jenis_skim' => 'Penelitian Terapan Unggulan Perguruan Tinggi',
                'tahun_usulan' => 2024,
                'tahun_kegiatan' => 2025,
                'tahun_pelaksanaan' => 2025,
                'lama_kegiatan' => '1 Tahun',
                'tahun_pelaksanaan_ke' => 1,
                'nomor_sk_penugasan' => 'SK-DIR/P3M/2025/102',
                'tanggal_sk_penugasan' => '2025-02-15',
                'in_kind' => 'Penyediaan kolam uji coba dan sensor pH dari Tambak Mitra Mandiri',
                'status_kegiatan' => 'Selesai',
                'ketua' => $dosen1->id_dosen,
                'anggota_dosen' => [$dosen2->id_dosen],
                'anggota_mhs' => [230101001, 230101002],
                'pendanaan' => [
                    [
                        'sumber_dana' => 'BIMA Kemendikbudristek',
                        'jumlah_dana' => 45000000.00,
                        'tahun_pendanaan' => 2025,
                        'jenis_pendanaan' => 'Hibah Kompetitif Nasional',
                        'keterangan' => 'Pendanaan riset terapan multi-tahun tahap 1',
                    ],
                ],
            ],
            // 2. Penelitian Berjalan
            [
                'id_jenis_kegiatan' => $jenisPenelitian->id_jenis_kegiatan,
                'judul_kegiatan' => 'Implementasi Deep Learning untuk Deteksi Dini Hama Padi Berbasis Citra Drone Multispektral',
                'afiliasi' => 'Pusat Studi Kecerdasan Buatan & Robotika',
                'lokasi_kegiatan' => 'Demak, Jawa Tengah',
                'litabmas_sebelumnya' => 'Penelitian Dasar 2024',
                'jenis_skim' => 'Penelitian Fundamental',
                'tahun_usulan' => 2025,
                'tahun_kegiatan' => 2026,
                'tahun_pelaksanaan' => 2026,
                'lama_kegiatan' => '2 Tahun',
                'tahun_pelaksanaan_ke' => 1,
                'nomor_sk_penugasan' => 'SK-DIR/P3M/2026/015',
                'tanggal_sk_penugasan' => '2026-01-20',
                'in_kind' => 'Akses lahan persawahan Gapoktan Tani Makmur',
                'status_kegiatan' => 'Berjalan',
                'ketua' => $dosen2->id_dosen,
                'anggota_dosen' => [$dosen3->id_dosen, $dosen4->id_dosen],
                'anggota_mhs' => [230101003, 230101004],
                'pendanaan' => [
                    [
                        'sumber_dana' => 'DIPA Internal Kampus',
                        'jumlah_dana' => 25000000.00,
                        'tahun_pendanaan' => 2026,
                        'jenis_pendanaan' => 'DIPA PNBP',
                        'keterangan' => 'Alokasi pengadaan sensor drone dan pelatihan model GPU',
                    ],
                ],
            ],
            // 3. Penelitian Usulan
            [
                'id_jenis_kegiatan' => $jenisPenelitian->id_jenis_kegiatan,
                'judul_kegiatan' => 'Arsitektur Blockchain untuk Verifikasi Ijazah Digital dan Transkrip Nilai Akademik Terdistribusi',
                'afiliasi' => 'Laboratorium Cybersecurity',
                'lokasi_kegiatan' => 'Kota Semarang',
                'litabmas_sebelumnya' => null,
                'jenis_skim' => 'Penelitian Dosen Pemula (PDP)',
                'tahun_usulan' => 2026,
                'tahun_kegiatan' => 2026,
                'tahun_pelaksanaan' => 2026,
                'lama_kegiatan' => '1 Tahun',
                'tahun_pelaksanaan_ke' => 1,
                'nomor_sk_penugasan' => null,
                'tanggal_sk_penugasan' => null,
                'in_kind' => null,
                'status_kegiatan' => 'Usulan',
                'ketua' => $dosen3->id_dosen,
                'anggota_dosen' => [$dosen1->id_dosen],
                'anggota_mhs' => [230101005],
                'pendanaan' => [
                    [
                        'sumber_dana' => 'Mandiri / Pengusul',
                        'jumlah_dana' => 10000000.00,
                        'tahun_pendanaan' => 2026,
                        'jenis_pendanaan' => 'Dana Mandiri',
                        'keterangan' => 'Pengujian smart contract di testnet Ethereum',
                    ],
                ],
            ],
            // 4. PkM Selesai
            [
                'id_jenis_kegiatan' => $jenisPkm->id_jenis_kegiatan,
                'judul_kegiatan' => 'Pemberdayaan UMKM Sentra Kerajinan Batik Melalui Digital Marketing dan Katalog E-Commerce Terintegrasi',
                'afiliasi' => 'Pusat Pengabdian kepada Masyarakat (P3M)',
                'lokasi_kegiatan' => 'Desa Wisata Batik Laweyan, Surakarta',
                'litabmas_sebelumnya' => null,
                'jenis_skim' => 'Program Kemitraan Masyarakat (PKM)',
                'tahun_usulan' => 2024,
                'tahun_kegiatan' => 2025,
                'tahun_pelaksanaan' => 2025,
                'lama_kegiatan' => '6 Bulan',
                'tahun_pelaksanaan_ke' => 1,
                'nomor_sk_penugasan' => 'SK-DIR/PKM/2025/088',
                'tanggal_sk_penugasan' => '2025-04-10',
                'in_kind' => 'Tempat pelatihan dan konsumsi swadaya paguyuban perajin',
                'status_kegiatan' => 'Selesai',
                'ketua' => $dosen2->id_dosen,
                'anggota_dosen' => [$dosen4->id_dosen],
                'anggota_mhs' => [230101006, 230101007],
                'pendanaan' => [
                    [
                        'sumber_dana' => 'BIMA Kemendikbudristek',
                        'jumlah_dana' => 20000000.00,
                        'tahun_pendanaan' => 2025,
                        'jenis_pendanaan' => 'Hibah Pengabdian Nasional',
                        'keterangan' => 'Pelatihan dan pendampingan pembuatan foto produk dan website katalog',
                    ],
                ],
            ],
            // 5. PkM Berjalan
            [
                'id_jenis_kegiatan' => $jenisPkm->id_jenis_kegiatan,
                'judul_kegiatan' => 'Pelatihan Pemrograman Visual Scratch dan Literasi Keamanan Siber bagi Siswa dan Guru SMK Negeri 1 Semarang',
                'afiliasi' => 'Jurusan Teknologi Informasi',
                'lokasi_kegiatan' => 'SMKN 1 Semarang',
                'litabmas_sebelumnya' => null,
                'jenis_skim' => 'PKM Binaan Sekolah Mitra',
                'tahun_usulan' => 2025,
                'tahun_kegiatan' => 2026,
                'tahun_pelaksanaan' => 2026,
                'lama_kegiatan' => '8 Bulan',
                'tahun_pelaksanaan_ke' => 1,
                'nomor_sk_penugasan' => 'SK-DIR/PKM/2026/021',
                'tanggal_sk_penugasan' => '2026-02-01',
                'in_kind' => 'Laboratorium komputer SMKN 1 Semarang',
                'status_kegiatan' => 'Berjalan',
                'ketua' => $dosen3->id_dosen,
                'anggota_dosen' => [$dosen1->id_dosen],
                'anggota_mhs' => [230101008],
                'pendanaan' => [
                    [
                        'sumber_dana' => 'DIPA Internal Kampus',
                        'jumlah_dana' => 15000000.00,
                        'tahun_pendanaan' => 2026,
                        'jenis_pendanaan' => 'DIPA Pengabdian',
                        'keterangan' => 'Honorarium instruktur dan modul ajar praktikum',
                    ],
                ],
            ],
        ];

        foreach ($daftarKegiatan as $kegiatanData) {
            $ketuaId = $kegiatanData['ketua'];
            $anggotaDosen = $kegiatanData['anggota_dosen'] ?? [];
            $anggotaMhs = $kegiatanData['anggota_mhs'] ?? [];
            $pendanaanList = $kegiatanData['pendanaan'] ?? [];

            unset($kegiatanData['ketua'], $kegiatanData['anggota_dosen'], $kegiatanData['anggota_mhs'], $kegiatanData['pendanaan']);

            $kegiatan = Kegiatan::firstOrCreate(
                ['judul_kegiatan' => $kegiatanData['judul_kegiatan']],
                $kegiatanData
            );

            // Seed Anggota: Ketua Dosen
            if (Schema::hasTable('anggota_kegiatan')) {
                AnggotaKegiatan::firstOrCreate(
                    [
                        'id_kegiatan' => $kegiatan->id_kegiatan,
                        'id_dosen' => $ketuaId,
                        'peran' => 'Ketua',
                    ],
                    [
                        'jenis_anggota' => 'dosen',
                    ]
                );

                // Seed Anggota: Dosen Pendamping
                foreach ($anggotaDosen as $idDosenAnggota) {
                    AnggotaKegiatan::firstOrCreate(
                        [
                            'id_kegiatan' => $kegiatan->id_kegiatan,
                            'id_dosen' => $idDosenAnggota,
                            'peran' => 'Anggota',
                        ],
                        [
                            'jenis_anggota' => 'dosen',
                        ]
                    );
                }

                // Seed Anggota: Mahasiswa
                foreach ($anggotaMhs as $idMahasiswa) {
                    AnggotaKegiatan::firstOrCreate(
                        [
                            'id_kegiatan' => $kegiatan->id_kegiatan,
                            'id_mahasiswa' => $idMahasiswa,
                            'peran' => 'Anggota',
                        ],
                        [
                            'id_dosen' => null,
                            'jenis_anggota' => 'mahasiswa',
                        ]
                    );
                }
            }

            // Seed Pendanaan Kegiatan
            if (Schema::hasTable('pendanaan_kegiatan')) {
                foreach ($pendanaanList as $dana) {
                    PendanaanKegiatan::firstOrCreate(
                        [
                            'id_kegiatan' => $kegiatan->id_kegiatan,
                            'sumber_dana' => $dana['sumber_dana'],
                            'tahun_pendanaan' => $dana['tahun_pendanaan'],
                        ],
                        array_merge($dana, ['id_kegiatan' => $kegiatan->id_kegiatan])
                    );
                }
            }
        }
    }
}
