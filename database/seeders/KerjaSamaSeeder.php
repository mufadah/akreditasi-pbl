<?php

namespace Database\Seeders;

use App\Models\AktivitasKerjaSama;
use App\Models\Dosen;
use App\Models\JenisKerjaSama;
use App\Models\KerjaSama;
use App\Models\Mitra;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KerjaSamaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('kerja_sama') || ! Schema::hasTable('mitra') || ! Schema::hasTable('dosen')) {
            return;
        }

        $dosens = Dosen::all();
        $mitras = Mitra::all()->keyBy('nama_mitra');
        $jenisKs = JenisKerjaSama::all()->keyBy(function ($item) {
            if (str_contains($item->nama_jenis_kerjasama, 'MoU')) {
                return 'MoU';
            }
            if (str_contains($item->nama_jenis_kerjasama, 'MoA')) {
                return 'MoA';
            }
            if (str_contains($item->nama_jenis_kerjasama, 'IA')) {
                return 'IA';
            }

            return $item->nama_jenis_kerjasama;
        });

        if ($dosens->isEmpty() || $mitras->isEmpty() || $jenisKs->isEmpty()) {
            return;
        }

        $dosenPic1 = $dosens->first();
        $dosenPic2 = $dosens->skip(1)->first() ?? $dosenPic1;
        $dosenPic3 = $dosens->skip(2)->first() ?? $dosenPic1;

        $mou = $jenisKs->get('MoU') ?? $jenisKs->first();
        $moa = $jenisKs->get('MoA') ?? $jenisKs->first();
        $ia = $jenisKs->get('IA') ?? $jenisKs->first();

        $daftarKerjaSama = [
            [
                'mitra_name' => 'PT Telkom Indonesia (Persero) Tbk',
                'id_jenis_kerjasama' => $mou->id_jenis_kerjasama,
                'id_dosen' => $dosenPic1->id_dosen,
                'nomor_dokumen' => 'MOU/TELKOM-PBL/2025/001',
                'jenis_dokumen' => 'Nota Kesepahaman (MoU)',
                'tanggal_mulai' => '2025-01-10',
                'tanggal_selesai' => '2028-01-10',
                'status' => 'Aktif',
                'aktivitas' => [
                    [
                        'judul_aktivitas' => 'Kuliah Tamu Industri: Implementasi Cloud Native & Microservices',
                        'tanggal_pelaksanaan' => '2025-03-20',
                        'deskripsi' => 'Kuliah umum daring bagi seluruh mahasiswa semester 4 dan 6 menghadirkan Principal Architect Telkom.',
                        'bukti_dokumen' => 'https://drive.google.com/file/d/bukti-kuliah-tamu-telkom/view',
                    ],
                    [
                        'judul_aktivitas' => 'Program Magang Bersertifikat Industri (MSIB)',
                        'tanggal_pelaksanaan' => '2025-08-01',
                        'deskripsi' => 'Penempatan 10 mahasiswa program studi untuk magang intensif selama 1 semester di Digital Hub Telkom.',
                        'bukti_dokumen' => 'https://drive.google.com/file/d/bukti-magang-telkom/view',
                    ],
                ],
            ],
            [
                'mitra_name' => 'Dinas Komunikasi dan Informatika Provinsi Jawa Tengah',
                'id_jenis_kerjasama' => $moa->id_jenis_kerjasama,
                'id_dosen' => $dosenPic2->id_dosen,
                'nomor_dokumen' => 'MOA/DISKOMINFO-JTG/2025/042',
                'jenis_dokumen' => 'Perjanjian Kerja Sama (MoA)',
                'tanggal_mulai' => '2025-02-01',
                'tanggal_selesai' => '2026-02-01',
                'status' => 'Aktif',
                'aktivitas' => [
                    [
                        'judul_aktivitas' => 'Audit Keamanan Sistem Informasi e-Government Pemprov Jateng',
                        'tanggal_pelaksanaan' => '2025-05-12',
                        'deskripsi' => 'Pengujian penetrasi (pentest) dan rekomendasi penguatan CSIRT Pemprov Jateng oleh tim dosen dan mahasiswa.',
                        'bukti_dokumen' => 'https://drive.google.com/file/d/laporan-pentest-diskominfo/view',
                    ],
                ],
            ],
            [
                'mitra_name' => 'Google Indonesia',
                'id_jenis_kerjasama' => $ia->id_jenis_kerjasama,
                'id_dosen' => $dosenPic1->id_dosen,
                'nomor_dokumen' => 'IA/GOOGLE-ID/2025/088',
                'jenis_dokumen' => 'Implementation Arrangement (IA)',
                'tanggal_mulai' => '2025-06-01',
                'tanggal_selesai' => '2026-12-31',
                'status' => 'Aktif',
                'aktivitas' => [
                    [
                        'judul_aktivitas' => 'Pelatihan & Sertifikasi Google Career Certificates (GCC)',
                        'tanggal_pelaksanaan' => '2025-09-10',
                        'deskripsi' => 'Pemberian 100 beasiswa sertifikasi IT Support & Data Analytics bagi mahasiswa aktif.',
                        'bukti_dokumen' => 'https://drive.google.com/file/d/sertifikat-gcc-google/view',
                    ],
                ],
            ],
            [
                'mitra_name' => 'Universiti Teknologi Malaysia (UTM)',
                'id_jenis_kerjasama' => $mou->id_jenis_kerjasama,
                'id_dosen' => $dosenPic3->id_dosen,
                'nomor_dokumen' => 'MOU/UTM-INT/2024/015',
                'jenis_dokumen' => 'International Memorandum of Understanding',
                'tanggal_mulai' => '2024-04-15',
                'tanggal_selesai' => '2027-04-15',
                'status' => 'Aktif',
                'aktivitas' => [
                    [
                        'judul_aktivitas' => 'Joint Research & Publication on Smart Healthcare IoT',
                        'tanggal_pelaksanaan' => '2024-10-18',
                        'deskripsi' => 'Kolaborasi riset internasional menghasilkan 1 artikel jurnal internasional bereputasi Scopus Q1.',
                        'bukti_dokumen' => 'https://drive.google.com/file/d/joint-research-utm/view',
                    ],
                ],
            ],
            [
                'mitra_name' => 'PT Astra International Tbk',
                'id_jenis_kerjasama' => $moa->id_jenis_kerjasama,
                'id_dosen' => $dosenPic2->id_dosen,
                'nomor_dokumen' => 'MOA/ASTRA/2024/007',
                'jenis_dokumen' => 'Perjanjian Kerja Sama (MoA)',
                'tanggal_mulai' => '2024-01-01',
                'tanggal_selesai' => '2025-01-01',
                'status' => 'Kedaluwarsa',
                'aktivitas' => [
                    [
                        'judul_aktivitas' => 'Pengembangan Sistem Telemetri Kendaraan Logistik',
                        'tanggal_pelaksanaan' => '2024-06-25',
                        'deskripsi' => 'Pemberian hibah alat IoT dan pengembangan sistem tracking logistik.',
                        'bukti_dokumen' => 'https://drive.google.com/file/d/laporan-akhir-astra/view',
                    ],
                ],
            ],
        ];

        foreach ($daftarKerjaSama as $data) {
            $mitra = $mitras->get($data['mitra_name']);
            if (! $mitra) {
                continue;
            }

            $ks = KerjaSama::firstOrCreate(
                ['nomor_dokumen' => $data['nomor_dokumen']],
                [
                    'id_mitra' => $mitra->id_mitra,
                    'id_jenis_kerjasama' => $data['id_jenis_kerjasama'],
                    'id_dosen' => $data['id_dosen'],
                    'jenis_dokumen' => $data['jenis_dokumen'],
                    'tanggal_mulai' => $data['tanggal_mulai'],
                    'tanggal_selesai' => $data['tanggal_selesai'],
                    'status' => $data['status'],
                ]
            );

            // Dapatkan id_kerja_sama sebenarnya dari database
            $idKerjaSama = $ks->id_kerja_sama
                ?: $ks->id_kerjasama
                ?: $ks->getKey()
                ?: DB::table('kerja_sama')->where('nomor_dokumen', $data['nomor_dokumen'])->value('id_kerja_sama');

            if ($idKerjaSama) {
                DB::table('kerja_sama')
                    ->where('id_kerja_sama', $idKerjaSama)
                    ->update(['id_kerjasama' => $idKerjaSama]);
            }

            // Seed aktivitas kerja sama terkait
            if (Schema::hasTable('aktivitas_kerja_sama') && $idKerjaSama && ! empty($data['aktivitas'])) {
                foreach ($data['aktivitas'] as $akt) {
                    AktivitasKerjaSama::firstOrCreate(
                        [
                            'id_kerja_sama' => $idKerjaSama,
                            'judul_aktivitas' => $akt['judul_aktivitas'],
                        ],
                        array_merge($akt, [
                            'id_kerja_sama' => $idKerjaSama,
                            'id_kerjasama' => $idKerjaSama,
                        ])
                    );
                }
            }
        }
    }
}
