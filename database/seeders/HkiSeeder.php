<?php

namespace Database\Seeders;

use App\Models\Dosen;
use App\Models\Hki;
use App\Models\JenisHki;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class HkiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('hki') || ! Schema::hasTable('dosen') || ! Schema::hasTable('jenis_hki')) {
            return;
        }

        $dosens = Dosen::all();
        if ($dosens->isEmpty()) {
            return;
        }

        $dosen1 = $dosens->first();
        $dosen2 = $dosens->skip(1)->first() ?? $dosen1;
        $dosen3 = $dosens->skip(2)->first() ?? $dosen1;

        $hakCipta = JenisHki::where('nama_hki', 'like', '%Hak Cipta%')->first()
            ?? JenisHki::create(['nama_hki' => 'Hak Cipta']);
        $paten = JenisHki::where('nama_hki', 'like', '%Paten%')->first()
            ?? JenisHki::create(['nama_hki' => 'Paten']);
        $desainIndustri = JenisHki::where('nama_hki', 'like', '%Desain%')->first()
            ?? JenisHki::create(['nama_hki' => 'Desain Industri']);

        $hkiList = [
            [
                'id_dosen' => $dosen1->id_dosen,
                'id_jenis_hki' => $hakCipta->id_jenis_hki,
                'judul_hki' => 'Sistem Informasi Manajemen Akreditasi Program Studi Berbasis Standar LAM INFOKOM (SIAKRE)',
                'nomor_hki' => 'EC00202511234',
                'tahun' => 2025,
            ],
            [
                'id_dosen' => $dosen2->id_dosen,
                'id_jenis_hki' => $paten->id_jenis_hki,
                'judul_hki' => 'Metode dan Alat Deteksi Anomali Kualitas Air Berbasis Wireless Sensor Network LoRa',
                'nomor_hki' => 'P00202508765',
                'tahun' => 2025,
            ],
            [
                'id_dosen' => $dosen3->id_dosen,
                'id_jenis_hki' => $desainIndustri->id_jenis_hki,
                'judul_hki' => 'Desain Casing Node Sensor Outdoor Tahan Cuaca Ekstrem untuk Pemantauan Pertanian Presisi',
                'nomor_hki' => 'IDD00202500432',
                'tahun' => 2025,
            ],
        ];

        foreach ($hkiList as $item) {
            Hki::firstOrCreate(
                ['nomor_hki' => $item['nomor_hki']],
                $item
            );
        }
    }
}
