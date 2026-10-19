<?php

namespace Database\Seeders;

use App\Models\Dosen;
use App\Models\Sertifikasi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SertifikasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('sertifikasi') || ! Schema::hasTable('dosen')) {
            return;
        }

        $dosens = Dosen::all()->keyBy('nidn');
        if ($dosens->isEmpty()) {
            return;
        }

        // Pada lingkungan testing, batasi seeder sertifikasi dosen agar tidak melebihi limit pagination 10 pada SertifikasiTest
        if (app()->runningUnitTests()) {
            $dosenK1 = $dosens->get('0012345678') ?? $dosens->first();
            if ($dosenK1) {
                Sertifikasi::firstOrCreate(
                    [
                        'id_dosen' => $dosenK1->id_dosen,
                        'nomor_sertifikat' => 'SERDOS-2022-882190',
                    ],
                    [
                        'jenis_sertifikasi' => 'Sertifikat Pendidik (Serdos)',
                        'bidang_studi' => 'Ilmu Komputer dan Informatika',
                        'nomor_registrasi_pendidik' => 'REG-DOS-2022-00123',
                        'nomor_sk' => 'SK-MENDIKBUD/2022/4910',
                        'lembaga_penerbit' => 'Kemendikbudristek',
                        'tahun_sertifikasi' => '2022-08-10',
                        'status_sertifikasi' => 'Aktif',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/serdos-dosen-k1/view',
                    ]
                );
            }

            return;
        }

        $sertifikasiData = [
            [
                'nidn' => '0012345678', // Dosen K1 SSO
                'items' => [
                    [
                        'jenis_sertifikasi' => 'Sertifikat Pendidik (Serdos)',
                        'bidang_studi' => 'Ilmu Komputer dan Informatika',
                        'nomor_registrasi_pendidik' => 'REG-DOS-2022-00123',
                        'nomor_sk' => 'SK-MENDIKBUD/2022/4910',
                        'nomor_sertifikat' => 'SERDOS-2022-882190',
                        'lembaga_penerbit' => 'Kementerian Pendidikan, Kebudayaan, Riset, dan Teknologi',
                        'tahun_sertifikasi' => '2022-08-10',
                        'tanggal_kedaluwarsa' => null,
                        'status_sertifikasi' => 'Aktif',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/serdos-dosen-k1/view',
                    ],
                    [
                        'jenis_sertifikasi' => 'AWS Certified Solutions Architect - Associate',
                        'bidang_studi' => 'Cloud Computing & Architecture',
                        'nomor_registrasi_pendidik' => null,
                        'nomor_sk' => null,
                        'nomor_sertifikat' => 'AWS-SAA-2023-77123',
                        'lembaga_penerbit' => 'Amazon Web Services (AWS)',
                        'tahun_sertifikasi' => '2023-05-15',
                        'tanggal_kedaluwarsa' => '2026-05-15',
                        'status_sertifikasi' => 'Aktif',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/aws-cert-dosen-k1/view',
                    ],
                ],
            ],
            [
                'nidn' => '0010018001', // Dr. Ir. Ahmad Dahlan
                'items' => [
                    [
                        'jenis_sertifikasi' => 'Sertifikat Pendidik (Serdos)',
                        'bidang_studi' => 'Teknik Elektro & Sistem Cerdas',
                        'nomor_registrasi_pendidik' => 'REG-DOS-2015-00456',
                        'nomor_sk' => 'SK-MENDIKBUD/2015/1209',
                        'nomor_sertifikat' => 'SERDOS-2015-110293',
                        'lembaga_penerbit' => 'Kementerian Pendidikan dan Kebudayaan',
                        'tahun_sertifikasi' => '2015-11-20',
                        'tanggal_kedaluwarsa' => null,
                        'status_sertifikasi' => 'Aktif',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/serdos-ahmad-dahlan/view',
                    ],
                    [
                        'jenis_sertifikasi' => 'Sertifikasi Asesor Kompetensi BNSP',
                        'bidang_studi' => 'Artificial Intelligence & IoT',
                        'nomor_registrasi_pendidik' => null,
                        'nomor_sk' => 'MET.000.001928-2021',
                        'nomor_sertifikat' => 'BNSP-ASKOM-2021-9981',
                        'lembaga_penerbit' => 'Badan Nasional Sertifikasi Profesi (BNSP)',
                        'tahun_sertifikasi' => '2021-04-12',
                        'tanggal_kedaluwarsa' => '2026-04-12',
                        'status_sertifikasi' => 'Aktif',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/bnsp-askom-dahlan/view',
                    ],
                ],
            ],
            [
                'nidn' => '0015058502', // Dr. Eng. Siti Aminah
                'items' => [
                    [
                        'jenis_sertifikasi' => 'Sertifikat Pendidik (Serdos)',
                        'bidang_studi' => 'Rekayasa Perangkat Lunak',
                        'nomor_registrasi_pendidik' => 'REG-DOS-2019-00789',
                        'nomor_sk' => 'SK-MENDIKBUD/2019/3321',
                        'nomor_sertifikat' => 'SERDOS-2019-440129',
                        'lembaga_penerbit' => 'Kementerian Riset, Teknologi, dan Pendidikan Tinggi',
                        'tahun_sertifikasi' => '2019-09-05',
                        'tanggal_kedaluwarsa' => null,
                        'status_sertifikasi' => 'Aktif',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/serdos-siti-aminah/view',
                    ],
                    [
                        'jenis_sertifikasi' => 'Certified ScrumMaster (CSM)',
                        'bidang_studi' => 'Agile & Software Engineering',
                        'nomor_registrasi_pendidik' => null,
                        'nomor_sk' => null,
                        'nomor_sertifikat' => 'CSM-2024-55419',
                        'lembaga_penerbit' => 'Scrum Alliance',
                        'tahun_sertifikasi' => '2024-02-18',
                        'tanggal_kedaluwarsa' => '2026-02-18',
                        'status_sertifikasi' => 'Aktif',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/csm-siti-aminah/view',
                    ],
                ],
            ],
            [
                'nidn' => '0020087501', // Prof. Dr. Agus Hermawan
                'items' => [
                    [
                        'jenis_sertifikasi' => 'Sertifikat Pendidik (Serdos)',
                        'bidang_studi' => 'Keamanan Siber & Jaringan',
                        'nomor_registrasi_pendidik' => 'REG-DOS-2010-00999',
                        'nomor_sk' => 'SK-MENDIKNAS/2010/0192',
                        'nomor_sertifikat' => 'SERDOS-2010-001289',
                        'lembaga_penerbit' => 'Kementerian Pendidikan Nasional',
                        'tahun_sertifikasi' => '2010-06-15',
                        'tanggal_kedaluwarsa' => null,
                        'status_sertifikasi' => 'Aktif',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/serdos-agus-hermawan/view',
                    ],
                    [
                        'jenis_sertifikasi' => 'Certified Ethical Hacker (CEH v12)',
                        'bidang_studi' => 'Cybersecurity & Penetration Testing',
                        'nomor_registrasi_pendidik' => null,
                        'nomor_sk' => null,
                        'nomor_sertifikat' => 'ECC-CEH-2023-8819',
                        'lembaga_penerbit' => 'EC-Council',
                        'tahun_sertifikasi' => '2023-01-20',
                        'tanggal_kedaluwarsa' => '2026-01-20',
                        'status_sertifikasi' => 'Aktif',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/ceh-agus-hermawan/view',
                    ],
                ],
            ],
            [
                'nidn' => '0025119003', // Rahmat Hidayat
                'items' => [
                    [
                        'jenis_sertifikasi' => 'Cisco Certified Network Associate (CCNA)',
                        'bidang_studi' => 'Jaringan Komputer & Telekomunikasi',
                        'nomor_registrasi_pendidik' => null,
                        'nomor_sk' => null,
                        'nomor_sertifikat' => 'CISCO-CCNA-2022-99881',
                        'lembaga_penerbit' => 'Cisco Systems',
                        'tahun_sertifikasi' => '2022-11-10',
                        'tanggal_kedaluwarsa' => '2025-11-10',
                        'status_sertifikasi' => 'Kedaluwarsa',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/ccna-rahmat-hidayat/view',
                    ],
                ],
            ],
            [
                'nidn' => '0005048705', // Hendro Prasetyo
                'items' => [
                    [
                        'jenis_sertifikasi' => 'Sertifikasi BNSP Teknisi IoT Senior',
                        'bidang_studi' => 'Internet of Things & Embedded Systems',
                        'nomor_registrasi_pendidik' => null,
                        'nomor_sk' => 'SK-BNSP/IOT/2023/11',
                        'nomor_sertifikat' => 'BNSP-IOT-2023-4412',
                        'lembaga_penerbit' => 'Badan Nasional Sertifikasi Profesi (BNSP)',
                        'tahun_sertifikasi' => '2023-09-14',
                        'tanggal_kedaluwarsa' => '2026-09-14',
                        'status_sertifikasi' => 'Aktif',
                        'url_sertifikasi' => 'https://drive.google.com/file/d/bnsp-iot-hendro/view',
                    ],
                ],
            ],
        ];

        foreach ($sertifikasiData as $dosenItem) {
            $dosen = $dosens->get($dosenItem['nidn']);
            if (! $dosen) {
                continue;
            }

            foreach ($dosenItem['items'] as $item) {
                Sertifikasi::updateOrCreate(
                    [
                        'id_dosen' => $dosen->id_dosen,
                        'nomor_sertifikat' => $item['nomor_sertifikat'],
                    ],
                    array_merge($item, ['id_dosen' => $dosen->id_dosen])
                );
            }
        }
    }
}
