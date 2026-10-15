<?php

namespace Database\Seeders;

use App\Models\Pendidikan;
use App\Models\Sertifikasi;
use App\Models\TenagaKependidikan;
use Illuminate\Database\Seeder;

class TenagaKependidikanSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan jenjang pendidikan untuk Tendik tersedia
        $pendS1 = Pendidikan::firstOrCreate(
            ['jenjang' => 'S1', 'program_studi' => 'Teknik Informatika'],
            ['universitas' => 'Politeknik Negeri Semarang', 'tahun_lulus' => 2017]
        );
        $pendD3 = Pendidikan::firstOrCreate(
            ['jenjang' => 'D3', 'program_studi' => 'Teknik Telekomunikasi & Jaringan'],
            ['universitas' => 'Politeknik Negeri Semarang', 'tahun_lulus' => 2019]
        );
        $pendPerpus = Pendidikan::firstOrCreate(
            ['jenjang' => 'D3', 'program_studi' => 'Ilmu Perpustakaan & Informasi'],
            ['universitas' => 'Universitas Diponegoro', 'tahun_lulus' => 2018]
        );
        $pendS1Adm = Pendidikan::firstOrCreate(
            ['jenjang' => 'S1', 'program_studi' => 'Administrasi Bisnis / Manajemen'],
            ['universitas' => 'Universitas Negeri Semarang', 'tahun_lulus' => 2016]
        );
        $pendSMA = Pendidikan::firstOrCreate(
            ['jenjang' => 'SMA/SMK', 'program_studi' => 'Teknik Komputer dan Jaringan'],
            ['universitas' => 'SMK Negeri 7 Semarang', 'tahun_lulus' => 2020]
        );

        // 2. Data Master Tenaga Kependidikan
        $dataTendik = [
            [
                'nip' => '198503152010121002',
                'nama_tendik' => 'Bambang Sutejo, S.Kom.',
                'jabatan_tendik' => 'Laboran Komputer',
                'id_pendidikan' => $pendS1->id_pendidikan,
                'sertifikasi' => 'Sertifikasi BNSP Teknisi Jaringan Komputer, Sertifikasi K3 Laboratorium',
                'sertifikasi_items' => [
                    [
                        'jenis_sertifikasi' => 'Sertifikasi BNSP Teknisi Jaringan',
                        'bidang_studi' => 'Jaringan Komputer & Cloud Infrastructure',
                        'nomor_sertifikat' => 'BNSP-TIK-2022-8871',
                        'lembaga_penerbit' => 'Badan Nasional Sertifikasi Profesi (BNSP)',
                        'tahun_sertifikasi' => 2022,
                        'status_sertifikasi' => 'Aktif',
                    ],
                    [
                        'jenis_sertifikasi' => 'Sertifikasi Keselamatan Kerja K3 Laboratorium',
                        'bidang_studi' => 'Kesehatan & Keselamatan Kerja Laboratorium Komputer',
                        'nomor_sertifikat' => 'K3-LAB-POLINES-2023',
                        'lembaga_penerbit' => 'Pusat K3 Kemnaker RI',
                        'tahun_sertifikasi' => 2023,
                        'status_sertifikasi' => 'Aktif',
                    ],
                ],
            ],
            [
                'nip' => '199008222015042001',
                'nama_tendik' => 'Siti Rahmawati, A.Md.',
                'jabatan_tendik' => 'Pustakawan',
                'id_pendidikan' => $pendPerpus->id_pendidikan,
                'sertifikasi' => 'Sertifikasi Pustakawan Ahli Pertama BNSP, E-Resource Management',
                'sertifikasi_items' => [
                    [
                        'jenis_sertifikasi' => 'Sertifikasi Pustakawan Ahli Pertama',
                        'bidang_studi' => 'Pengelolaan Repositori & Koleksi Digital Ilmiah',
                        'nomor_sertifikat' => 'PUS-BNSP-2021-4412',
                        'lembaga_penerbit' => 'LSP Perpustakaan Nasional RI',
                        'tahun_sertifikasi' => 2021,
                        'status_sertifikasi' => 'Aktif',
                    ],
                ],
            ],
            [
                'nip' => '198811102014021003',
                'nama_tendik' => 'Agus Prasetyo, S.T.',
                'jabatan_tendik' => 'Teknisi Jaringan & Server',
                'id_pendidikan' => $pendS1->id_pendidikan,
                'sertifikasi' => 'MikroTik Certified Network Associate (MTCNA), Cisco CCNA',
                'sertifikasi_items' => [
                    [
                        'jenis_sertifikasi' => 'MikroTik Certified Network Associate (MTCNA)',
                        'bidang_studi' => 'Routing, Switching, & Bandwidth Management',
                        'nomor_sertifikat' => 'MTCNA-2023-99120',
                        'lembaga_penerbit' => 'MikroTik SIA Latvia',
                        'tahun_sertifikasi' => 2023,
                        'status_sertifikasi' => 'Aktif',
                    ],
                ],
            ],
            [
                'nip' => '199205142018012002',
                'nama_tendik' => 'Dewi Lestari, S.E.',
                'jabatan_tendik' => 'Tenaga Administrasi Akademik',
                'id_pendidikan' => $pendS1Adm->id_pendidikan,
                'sertifikasi' => 'Sertifikasi Tata Kelola Administrasi Perguruan Tinggi',
                'sertifikasi_items' => [
                    [
                        'jenis_sertifikasi' => 'Tata Kelola Administrasi Perguruan Tinggi',
                        'bidang_studi' => 'Sistem Informasi Akademik & Akreditasi Prodi',
                        'nomor_sertifikat' => 'ADM-DIKTI-2022-771',
                        'lembaga_penerbit' => 'Pusdiklat Kemendikbudristek',
                        'tahun_sertifikasi' => 2022,
                        'status_sertifikasi' => 'Aktif',
                    ],
                ],
            ],
            [
                'nip' => '199304052019031001',
                'nama_tendik' => 'Eko Wahyudi, A.Md.',
                'jabatan_tendik' => 'Laboran Perangkat Keras & IoT',
                'id_pendidikan' => $pendD3->id_pendidikan,
                'sertifikasi' => 'Sertifikasi Embedded Systems & IoT Device Fabrication',
                'sertifikasi_items' => [
                    [
                        'jenis_sertifikasi' => 'Embedded Systems & IoT Hardware',
                        'bidang_studi' => 'Perakitan & Pemeliharaan Perangkat Keras Lab IoT',
                        'nomor_sertifikat' => 'IOT-IND-2023-301',
                        'lembaga_penerbit' => 'LSP Elektronika & Komputer Indonesia',
                        'tahun_sertifikasi' => 2023,
                        'status_sertifikasi' => 'Aktif',
                    ],
                ],
            ],
            [
                'nip' => '198907192016022004',
                'nama_tendik' => 'Nurul Hidayah, S.Sos.',
                'jabatan_tendik' => 'Arsiparis & Tata Usaha',
                'id_pendidikan' => $pendS1Adm->id_pendidikan,
                'sertifikasi' => 'Sertifikasi Pengelolaan Berkas Dinamis & Akreditasi',
                'sertifikasi_items' => [
                    [
                        'jenis_sertifikasi' => 'Pengelolaan Arsip Dinamis Perguruan Tinggi',
                        'bidang_studi' => 'Manajemen Arsip Akreditasi LAM INFOKOM',
                        'nomor_sertifikat' => 'ANRI-ARSIP-2022-550',
                        'lembaga_penerbit' => 'Arsip Nasional Republik Indonesia (ANRI)',
                        'tahun_sertifikasi' => 2022,
                        'status_sertifikasi' => 'Aktif',
                    ],
                ],
            ],
        ];

        foreach ($dataTendik as $t) {
            $sertifItems = $t['sertifikasi_items'] ?? [];
            unset($t['sertifikasi_items']);

            $tendik = TenagaKependidikan::withTrashed()->updateOrCreate(
                ['nip' => $t['nip']],
                $t
            );

            // Restore if was deleted
            if ($tendik->trashed()) {
                $tendik->restore();
            }

            // Hubungkan sertifikasi jika ada
            foreach ($sertifItems as $sItem) {
                $sItem['id_tendik'] = $tendik->id_tendik;
                Sertifikasi::firstOrCreate(
                    [
                        'id_tendik' => $tendik->id_tendik,
                        'nomor_sertifikat' => $sItem['nomor_sertifikat'],
                    ],
                    $sItem
                );
            }
        }
    }
}
