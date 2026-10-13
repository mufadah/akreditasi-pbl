<?php

namespace Database\Seeders;

use App\Models\JenisHki;
use Illuminate\Database\Seeder;

class JenisHkiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Hak Cipta',
            'Paten',
            'Merek',
            'Desain Industri',
        ];

        foreach ($data as $nama) {
            JenisHki::updateOrCreate(
                ['nama_hki' => $nama],
                ['nama_hki' => $nama]
            );
        }
    }
}
