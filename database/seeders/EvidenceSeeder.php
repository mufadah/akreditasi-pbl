<?php

namespace Database\Seeders;

use App\Models\Evidence;
use App\Models\EvidenceHistory;
use App\Models\Kegiatan;
use App\Models\KerjaSama;
use App\Models\Luaran;
use App\Models\Sertifikasi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class EvidenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('evidence')) {
            return;
        }

        $validator = User::where('role', 'ADMINISTRATOR')->orWhere('role', 'ASESOR')->first();
        $validatorId = $validator?->id ?? 1;

        $kegiatan = Kegiatan::first();
        $luaran = Luaran::first();
        $sertifikasi = Sertifikasi::first();
        $kerjaSama = KerjaSama::first();
        $idKs = $kerjaSama ? ($kerjaSama->id_kerja_sama ?: $kerjaSama->id_kerjasama ?: $kerjaSama->getKey()) : null;

        $evidenceList = [
            // Evidence 1: Kegiatan Penelitian (Valid)
            [
                'id_kegiatan' => $kegiatan?->id_kegiatan,
                'id_kerja_sama' => null,
                'id_sertifikasi' => null,
                'id_luaran' => null,
                'tipe_entitas' => 'kegiatan',
                'id_entitas' => $kegiatan?->id_kegiatan,
                'kode_indikator' => 'PEN-01',
                'jenis_dokumen' => 'Laporan Akhir',
                'nama_file' => 'Laporan_Akhir_Riset_IoT_Tambak_Udang_2025.pdf',
                'path_file' => 'evidences/kegiatan/laporan-akhir-iot-tambak.pdf',
                'url_drive' => 'https://drive.google.com/file/d/laporan-akhir-iot-tambak/view',
                'url_evidence' => 'https://drive.google.com/file/d/laporan-akhir-iot-tambak/view',
                'tanggal_upload' => '2025-11-25',
                'status_validasi' => 'Valid',
                'catatan_validasi' => 'Dokumen lengkap, bertanda tangan basah dan terdapat stempel mitra.',
                'divalidasi_oleh' => $validatorId,
                'tanggal_validasi' => '2025-12-01 10:15:00',
                'history' => [
                    [
                        'url_lama' => 'https://drive.google.com/file/d/draft-laporan-iot/view',
                        'url_baru' => 'https://drive.google.com/file/d/laporan-akhir-iot-tambak/view',
                        'alasan_perubahan' => 'Pembaruan lampiran lembar pengesahan dekanat dan nota dinas P3M.',
                        'diubah_oleh' => $validatorId,
                    ],
                ],
            ],
            // Evidence 2: Luaran Publikasi Jurnal (Valid)
            [
                'id_kegiatan' => null,
                'id_kerja_sama' => null,
                'id_sertifikasi' => null,
                'id_luaran' => $luaran?->id_luaran,
                'tipe_entitas' => 'luaran',
                'id_entitas' => $luaran?->id_luaran,
                'kode_indikator' => 'PEN-01',
                'jenis_dokumen' => 'Publikasi',
                'nama_file' => 'Artikel_IEEE_Access_IoT_Aquaculture.pdf',
                'path_file' => 'evidences/luaran/ieee-access-article.pdf',
                'url_drive' => 'https://drive.google.com/file/d/ieee-access-article/view',
                'url_evidence' => 'https://drive.google.com/file/d/ieee-access-article/view',
                'tanggal_upload' => '2025-07-01',
                'status_validasi' => 'Valid',
                'catatan_validasi' => 'Terverifikasi terindeks Scopus Q1 dengan DOI aktif.',
                'divalidasi_oleh' => $validatorId,
                'tanggal_validasi' => '2025-07-05 14:20:00',
            ],
            // Evidence 3: Sertifikasi Pendidik Dosen (Valid)
            [
                'id_kegiatan' => null,
                'id_kerja_sama' => null,
                'id_sertifikasi' => $sertifikasi?->id_sertifikasi,
                'id_luaran' => null,
                'tipe_entitas' => 'sertifikasi',
                'id_entitas' => $sertifikasi?->id_sertifikasi,
                'kode_indikator' => 'SDM-01',
                'jenis_dokumen' => 'Sertifikat',
                'nama_file' => 'Sertifikat_Pendidik_Serdos_Dosen.pdf',
                'path_file' => 'evidences/sertifikasi/serdos-dosen.pdf',
                'url_drive' => 'https://drive.google.com/file/d/serdos-dosen-pdf/view',
                'url_evidence' => 'https://drive.google.com/file/d/serdos-dosen-pdf/view',
                'tanggal_upload' => '2025-01-15',
                'status_validasi' => 'Valid',
                'catatan_validasi' => 'Nomor registrasi pendidik sesuai pddikti.',
                'divalidasi_oleh' => $validatorId,
                'tanggal_validasi' => '2025-01-18 09:00:00',
            ],
            // Evidence 4: Kerja Sama Industri (Menunggu Validasi)
            [
                'id_kegiatan' => null,
                'id_kerja_sama' => $idKs,
                'id_kerjasama' => $idKs,
                'id_sertifikasi' => null,
                'id_luaran' => null,
                'tipe_entitas' => 'kerja_sama',
                'id_entitas' => $idKs,
                'kode_indikator' => 'KS-01',
                'jenis_dokumen' => 'Dokumen MoU',
                'nama_file' => 'Dokumen_MoU_Kerjasama_Telkom_2025.pdf',
                'path_file' => 'evidences/kerjasama/mou-telkom-2025.pdf',
                'url_drive' => 'https://drive.google.com/file/d/mou-telkom-signed/view',
                'url_evidence' => 'https://drive.google.com/file/d/mou-telkom-signed/view',
                'tanggal_upload' => '2025-02-05',
                'status_validasi' => 'Menunggu',
                'catatan_validasi' => null,
                'divalidasi_oleh' => null,
                'tanggal_validasi' => null,
            ],
            // Evidence 5: Proposal Baru (Ditolak / Perlu Perbaikan)
            [
                'id_kegiatan' => $kegiatan?->id_kegiatan,
                'id_kerja_sama' => null,
                'id_sertifikasi' => null,
                'id_luaran' => null,
                'tipe_entitas' => 'kegiatan',
                'id_entitas' => $kegiatan?->id_kegiatan,
                'kode_indikator' => 'PEN-01',
                'jenis_dokumen' => 'Proposal',
                'nama_file' => 'Proposal_Penelitian_Blockchain_2026.pdf',
                'path_file' => 'evidences/kegiatan/proposal-blockchain.pdf',
                'url_drive' => 'https://drive.google.com/file/d/proposal-blockchain-draft/view',
                'url_evidence' => 'https://drive.google.com/file/d/proposal-blockchain-draft/view',
                'tanggal_upload' => '2026-02-10',
                'status_validasi' => 'Ditolak',
                'catatan_validasi' => 'Rencana Anggaran Biaya (RAB) belum mencantumkan standar biaya masukan (SBM) terbaru.',
                'divalidasi_oleh' => $validatorId,
                'tanggal_validasi' => '2026-02-14 11:30:00',
            ],
        ];

        $validColumns = array_flip(Schema::getColumnListing('evidence'));

        foreach ($evidenceList as $evData) {
            $history = $evData['history'] ?? [];
            unset($evData['history']);

            $filteredData = array_intersect_key($evData, $validColumns);

            $evidence = Evidence::firstOrCreate(
                [
                    'nama_file' => $filteredData['nama_file'],
                    'jenis_dokumen' => $filteredData['jenis_dokumen'],
                ],
                $filteredData
            );

            if (Schema::hasTable('evidence_histories') && ! empty($history)) {
                foreach ($history as $h) {
                    EvidenceHistory::firstOrCreate(
                        [
                            'evidence_id' => $evidence->id_evidence,
                            'url_baru' => $h['url_baru'],
                        ],
                        array_merge($h, ['evidence_id' => $evidence->id_evidence])
                    );
                }
            }
        }
    }
}
