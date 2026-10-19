<?php

namespace Database\Seeders;

use App\Models\Dosen;
use App\Models\JenisLuaran;
use App\Models\Kegiatan;
use App\Models\Luaran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LuaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('luaran') || ! Schema::hasTable('dosen') || ! Schema::hasTable('jenis_luaran')) {
            return;
        }

        $dosens = Dosen::all();
        if ($dosens->isEmpty()) {
            return;
        }

        $dosen1 = $dosens->first();
        $dosen2 = $dosens->skip(1)->first() ?? $dosen1;
        $dosen3 = $dosens->skip(2)->first() ?? $dosen1;

        $kegiatans = Kegiatan::all();
        $kegiatan1 = $kegiatans->first();
        $kegiatan2 = $kegiatans->skip(1)->first() ?? $kegiatan1;

        // Ambil jenis luaran
        $jurnalScopus = JenisLuaran::where('nama_jenis_luaran', 'like', '%Jurnal%')->where('indeks', 'Scopus')->first()
            ?? JenisLuaran::where('nama_jenis_luaran', 'like', '%Jurnal%')->first()
            ?? JenisLuaran::create(['nama_jenis_luaran' => 'Jurnal', 'indeks' => 'Scopus']);

        $jurnalSinta = JenisLuaran::where('nama_jenis_luaran', 'like', '%Jurnal%')->where('indeks', 'Sinta')->first()
            ?? JenisLuaran::where('nama_jenis_luaran', 'like', '%Jurnal%')->first();

        $prosidingScopus = JenisLuaran::where('nama_jenis_luaran', 'like', '%Prosiding%')->first()
            ?? JenisLuaran::create(['nama_jenis_luaran' => 'Prosiding', 'indeks' => 'IEEE/Scopus']);

        $jenisHki = JenisLuaran::where('nama_jenis_luaran', 'like', '%HKI%')->first()
            ?? JenisLuaran::create(['nama_jenis_luaran' => 'HKI', 'indeks' => 'DJKI']);

        $jenisBuku = JenisLuaran::where('nama_jenis_luaran', 'like', '%Buku%')->first()
            ?? JenisLuaran::create(['nama_jenis_luaran' => 'Buku', 'indeks' => 'ISBN']);

        $jenisProduk = JenisLuaran::where('nama_jenis_luaran', 'like', '%Produk%')->first()
            ?? JenisLuaran::create(['nama_jenis_luaran' => 'Produk', 'indeks' => 'TKT']);

        $luaranList = [
            // 1. Jurnal Internasional Bereputasi Scopus
            [
                'id_kegiatan' => $kegiatan1?->id_kegiatan,
                'id_dosen' => $dosen1->id_dosen,
                'id_jenis_luaran' => $jurnalScopus->id_jenis_luaran,
                'nama_jurnal' => 'IEEE Access',
                'issn' => '2169-3536',
                'volume' => 'Vol. 13',
                'tanggal_terbit' => '2025-06-15',
                'doi' => '10.1109/ACCESS.2025.1098234',
                'nomor_edisi' => 'Issue 2',
                'halaman_jurnal' => '14520-14532',
                'indeksasi' => 'Scopus Q1',
            ],
            // 2. Jurnal Nasional Terakreditasi SINTA
            [
                'id_kegiatan' => $kegiatan2?->id_kegiatan,
                'id_dosen' => $dosen2->id_dosen,
                'id_jenis_luaran' => $jurnalSinta->id_jenis_luaran,
                'nama_jurnal' => 'Jurnal Nasional Teknik Elektro dan Teknologi Informasi (JNTETI)',
                'issn' => '2301-4156',
                'volume' => 'Vol. 14, No. 3',
                'tanggal_terbit' => '2025-08-30',
                'doi' => '10.22146/jnteti.v14i3.8912',
                'nomor_edisi' => 'No. 3',
                'halaman_jurnal' => '210-218',
                'indeksasi' => 'SINTA 2',
            ],
            // 3. Prosiding Seminar Internasional IEEE
            [
                'id_kegiatan' => $kegiatan1?->id_kegiatan,
                'id_dosen' => $dosen1->id_dosen,
                'id_jenis_luaran' => $prosidingScopus->id_jenis_luaran,
                'nama_seminar' => '2025 8th International Conference on Information Technology, Information Systems and Electrical Engineering (ICITISEE)',
                'nama_prosiding' => 'Proceedings of 2025 ICITISEE IEEE Conference',
                'isbn_prosiding' => '979-8-3503-4912-3',
                'penyelenggara' => 'IEEE Indonesia Section',
                'tanggal_seminar' => '2025-10-22',
                'lokasi_seminar' => 'Yogyakarta, Indonesia',
                'halaman_prosiding' => '88-93',
                'doi_prosiding' => '10.1109/ICITISEE62419.2025.10744123',
            ],
            // 4. Hak Cipta / HKI
            [
                'id_kegiatan' => $kegiatan1?->id_kegiatan,
                'id_dosen' => $dosen1->id_dosen,
                'id_jenis_luaran' => $jenisHki->id_jenis_luaran,
                'jenis_hki' => 'Hak Cipta Program Komputer',
                'judul_hak_cipta' => 'Software Monitoring Kualitas Air Tambak Pintar Berbasis Web & Mobile',
                'nomor_pendaftaran_hki' => 'EC00202598124',
                'nomor_sertifikat_hki' => '000512891',
                'tanggal_pendaftaran_hki' => '2025-09-01',
                'tanggal_terbit_sertifikat_hki' => '2025-09-15',
                'pemegang_hki' => 'Politeknik Negeri Semarang',
                'status_hki' => 'Granted / Terbit',
            ],
            // 5. Buku Ajar
            [
                'id_kegiatan' => null,
                'id_dosen' => $dosen3->id_dosen,
                'id_jenis_luaran' => $jenisBuku->id_jenis_luaran,
                'judul_buku' => 'Panduan Praktis Perancangan Jaringan IoT Berbasis LoRaWAN',
                'isbn' => '978-623-01-3891-2',
                'penerbit_buku' => 'Penerbit Andi Offset',
                'tahun_terbit' => 2025,
                'jenis_buku' => 'Buku Ajar',
                'bidang_ilmu' => 'Teknik Komputer & Jaringan',
                'halaman_buku' => '198 halaman',
            ],
            // 6. Produk Teknologi / Prototipe
            [
                'id_kegiatan' => $kegiatan1?->id_kegiatan,
                'id_dosen' => $dosen1->id_dosen,
                'id_jenis_luaran' => $jenisProduk->id_jenis_luaran,
                'nama_produk' => 'Smart Shrimp Aquaculture IoT Kit (AquaSmart)',
                'jenis_produk' => 'Perangkat Keras IoT & Dashboard Cloud',
                'status_pengembangan' => 'Prototipe Teruji',
                'tingkat_kesiapanterapan' => 'TKT 7',
                'tanggal_pembuatan' => '2025-11-20',
                'deskripsi_produk' => 'Alat telemetri kualitas air tambak dengan sensor DO, pH, salinitas, dan suhu air berbasis transmisi LoRa.',
                'tautan_produk' => 'https://aquasmart.polines.ac.id',
            ],
        ];

        foreach ($luaranList as $luaranData) {
            $checkKey = [];
            if (! empty($luaranData['doi'])) {
                $checkKey = ['doi' => $luaranData['doi']];
            } elseif (! empty($luaranData['nomor_sertifikat_hki'])) {
                $checkKey = ['nomor_sertifikat_hki' => $luaranData['nomor_sertifikat_hki']];
            } elseif (! empty($luaranData['isbn'])) {
                $checkKey = ['isbn' => $luaranData['isbn']];
            } elseif (! empty($luaranData['nama_produk'])) {
                $checkKey = ['nama_produk' => $luaranData['nama_produk']];
            } else {
                $checkKey = ['id_dosen' => $luaranData['id_dosen'], 'id_jenis_luaran' => $luaranData['id_jenis_luaran']];
            }

            Luaran::firstOrCreate($checkKey, $luaranData);
        }
    }
}
