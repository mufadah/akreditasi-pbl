<?php

namespace Database\Seeders;

use App\Models\JenisKerjaSama;
use Illuminate\Database\Seeder;

class JenisKerjaSamaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'MoU',
            'MoA',
            'IA',
        ];

        foreach ($data as $nama) {
            JenisKerjaSama::updateOrCreate(
                ['nama_jenis_kerjasama' => $nama],
                ['nama_jenis_kerjasama' => $nama]
            );
        }
    }
}
