<?php

namespace Database\Seeders;

use App\Models\JenisKegiatan;
use Illuminate\Database\Seeder;

class JenisKegiatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'nama_jenis_kegiatan' => 'Penelitian',
                'deskripsi' => 'Kegiatan penelitian ilmiah, terapan, dan pengembangan oleh civitas akademika.',
            ],
            [
                'nama_jenis_kegiatan' => 'PkM',
                'deskripsi' => 'Kegiatan pengabdian kepada masyarakat untuk penerapan iptek dan pemberdayaan masyarakat.',
            ],
        ];

        foreach ($data as $item) {
            JenisKegiatan::firstOrCreate(
                ['nama_jenis_kegiatan' => $item['nama_jenis_kegiatan']],
                ['deskripsi' => $item['deskripsi']]
            );
        }
    }
}
