<?php

namespace Database\Seeders;

use App\Models\BidangKeahlian;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\Pendidikan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DosenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('dosen')) {
            return;
        }

        // Ambil atau buat master jabatan akademik
        $tp = JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Tenaga Pengajar']);
        $aa = JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Asisten Ahli']);
        $l = JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Lektor']);
        $lk = JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Lektor Kepala']);
        $gb = JabatanAkademik::firstOrCreate(['nama_jabatan' => 'Guru Besar']);

        // Ambil atau buat master pendidikan
        $pendS2El = Pendidikan::firstOrCreate(
            ['jenjang' => 'S2', 'program_studi' => 'Teknik Elektro dan Informatika'],
            ['universitas' => 'Institut Teknologi Bandung', 'tahun_lulus' => 2020]
        );
        $pendS2Cs = Pendidikan::firstOrCreate(
            ['jenjang' => 'S2', 'program_studi' => 'Ilmu Komputer'],
            ['universitas' => 'Universitas Gadjah Mada', 'tahun_lulus' => 2021]
        );
        $pendS3Undip = Pendidikan::firstOrCreate(
            ['jenjang' => 'S3', 'program_studi' => 'Teknik Sistem Komputer'],
            ['universitas' => 'Universitas Diponegoro', 'tahun_lulus' => 2023]
        );
        $pendS3Itb = Pendidikan::firstOrCreate(
            ['jenjang' => 'S3', 'program_studi' => 'Teknik Elektro dan Informatika'],
            ['universitas' => 'Institut Teknologi Bandung', 'tahun_lulus' => 2019]
        );
        $pendS3Ugm = Pendidikan::firstOrCreate(
            ['jenjang' => 'S3', 'program_studi' => 'Ilmu Komputer'],
            ['universitas' => 'Universitas Gadjah Mada', 'tahun_lulus' => 2018]
        );
        $pendS2Polines = Pendidikan::firstOrCreate(
            ['jenjang' => 'S2', 'program_studi' => 'Teknik Telekomunikasi & Jaringan'],
            ['universitas' => 'Politeknik Negeri Semarang', 'tahun_lulus' => 2022]
        );

        // Ambil master bidang keahlian
        $bkAi = BidangKeahlian::firstOrCreate(['nama_bidang' => 'Artificial Intelligence']);
        $bkIot = BidangKeahlian::firstOrCreate(['nama_bidang' => 'Internet of Things']);
        $bkCyber = BidangKeahlian::firstOrCreate(['nama_bidang' => 'Cybersecurity']);
        $bkSe = BidangKeahlian::firstOrCreate(['nama_bidang' => 'Software Engineering']);
        $bkCloud = BidangKeahlian::firstOrCreate(['nama_bidang' => 'Cloud Computing']);

        $dosenList = [
            [
                'nidn' => '0012345678',
                'nuptk' => '9912345678000001',
                'nama_dosen' => 'Dosen K1 SSO',
                'email' => 'dosen@example.com',
                'id_jabatan' => $l->id_jabatan,
                'id_pendidikan' => $pendS2Cs->id_pendidikan,
                'status_dosen' => 'DTPS',
                'keahlian' => [$bkSe->id_bidang_keahlian, $bkCloud->id_bidang_keahlian],
            ],
            [
                'nidn' => '0010018001',
                'nuptk' => '1001800100010002',
                'nama_dosen' => 'Dr. Ir. Ahmad Dahlan, M.T.',
                'email' => 'ahmad.dahlan@example.com',
                'id_jabatan' => $lk->id_jabatan,
                'id_pendidikan' => $pendS3Itb->id_pendidikan,
                'status_dosen' => 'DTPS',
                'keahlian' => [$bkAi->id_bidang_keahlian, $bkIot->id_bidang_keahlian],
            ],
            [
                'nidn' => '0015058502',
                'nuptk' => '1505850200020003',
                'nama_dosen' => 'Dr. Eng. Siti Aminah, S.Kom., M.Cs.',
                'email' => 'siti.aminah@example.com',
                'id_jabatan' => $l->id_jabatan,
                'id_pendidikan' => $pendS3Ugm->id_pendidikan,
                'status_dosen' => 'DTPS',
                'keahlian' => [$bkSe->id_bidang_keahlian, $bkAi->id_bidang_keahlian],
            ],
            [
                'nidn' => '0020087501',
                'nuptk' => '2008750100030004',
                'nama_dosen' => 'Prof. Dr. Agus Hermawan, M.Kom.',
                'email' => 'agus.hermawan@example.com',
                'id_jabatan' => $gb->id_jabatan,
                'id_pendidikan' => $pendS3Undip->id_pendidikan,
                'status_dosen' => 'DTPS',
                'keahlian' => [$bkCyber->id_bidang_keahlian, $bkCloud->id_bidang_keahlian],
            ],
            [
                'nidn' => '0025119003',
                'nuptk' => '2511900300040005',
                'nama_dosen' => 'Rahmat Hidayat, S.T., M.T.',
                'email' => 'rahmat.hidayat@example.com',
                'id_jabatan' => $aa->id_jabatan,
                'id_pendidikan' => $pendS2El->id_pendidikan,
                'status_dosen' => 'DTPS',
                'keahlian' => [$bkIot->id_bidang_keahlian, $bkCyber->id_bidang_keahlian],
            ],
            [
                'nidn' => '0030129204',
                'nuptk' => '3012920400050006',
                'nama_dosen' => 'Nurul Hidayati, S.Kom., M.Kom.',
                'email' => 'nurul.hidayati@example.com',
                'id_jabatan' => $tp->id_jabatan,
                'id_pendidikan' => $pendS2Cs->id_pendidikan,
                'status_dosen' => 'Non-DTPS',
                'keahlian' => [$bkSe->id_bidang_keahlian],
            ],
            [
                'nidn' => '0005048705',
                'nuptk' => '0504870500060007',
                'nama_dosen' => 'Hendro Prasetyo, S.T., M.Eng.',
                'email' => 'hendro.prasetyo@example.com',
                'id_jabatan' => $l->id_jabatan,
                'id_pendidikan' => $pendS2Polines->id_pendidikan,
                'status_dosen' => 'DTPS',
                'keahlian' => [$bkIot->id_bidang_keahlian, $bkCloud->id_bidang_keahlian],
            ],
            [
                'nidn' => '0012098406',
                'nuptk' => '1209840600070008',
                'nama_dosen' => 'Dr. Ratna Wulandari, M.Si.',
                'email' => 'ratna.wulandari@example.com',
                'id_jabatan' => $lk->id_jabatan,
                'id_pendidikan' => $pendS3Undip->id_pendidikan,
                'status_dosen' => 'DTPS',
                'keahlian' => [$bkAi->id_bidang_keahlian],
            ],
            [
                'nidn' => '0018079107',
                'nuptk' => '1807910700080009',
                'nama_dosen' => 'Eko Yulianto, S.Pd., M.Cs.',
                'email' => 'eko.yulianto@example.com',
                'id_jabatan' => $aa->id_jabatan,
                'id_pendidikan' => $pendS2Cs->id_pendidikan,
                'status_dosen' => 'Non-DTPS',
                'keahlian' => [$bkCloud->id_bidang_keahlian, $bkSe->id_bidang_keahlian],
            ],
        ];

        foreach ($dosenList as $item) {
            $keahlian = $item['keahlian'] ?? [];
            unset($item['keahlian']);

            $existing = Dosen::withTrashed()->where('nidn', $item['nidn'])->orWhere('email', $item['email'])->first();
            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update($item);
                $dosen = $existing;
            } else {
                $dosen = Dosen::create($item);
            }

            // Hubungkan ke bidang keahlian via pivot
            if (Schema::hasTable('bidang_keahlian_dosen') && ! empty($keahlian)) {
                $dosen->bidangKeahlian()->syncWithoutDetaching($keahlian);
            }
        }
    }
}
