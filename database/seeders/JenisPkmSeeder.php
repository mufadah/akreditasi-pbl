<?php

namespace Database\Seeders;

use App\Models\JenisPkm;
use Illuminate\Database\Seeder;

class JenisPkmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Pelatihan',
            'Penyuluhan',
            'Pendampingan',
        ];

        foreach ($data as $nama) {
            JenisPkm::updateOrCreate(
                ['nama_jenis_pkm' => $nama],
                [
                    'nama_jenis_pkm' => $nama,
                    'jenis_pkm'      => $nama,
                ]
            );
        }
    }
}
