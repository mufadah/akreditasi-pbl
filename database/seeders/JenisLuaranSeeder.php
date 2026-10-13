<?php

namespace Database\Seeders;

use App\Models\JenisLuaran;
use Illuminate\Database\Seeder;

class JenisLuaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'nama_jenis_luaran' => 'Buku',
                'indeks' => null,
            ],
            [
                'nama_jenis_luaran' => 'Jurnal',
                'indeks' => 'Scopus',
            ],
            [
                'nama_jenis_luaran' => 'Jurnal',
                'indeks' => 'Sinta',
            ],
            [
                'nama_jenis_luaran' => 'Jurnal',
                'indeks' => 'Google Scholar',
            ],
            [
                'nama_jenis_luaran' => 'Prosiding',
                'indeks' => 'Scopus',
            ],
            [
                'nama_jenis_luaran' => 'Prosiding',
                'indeks' => 'Google Scholar',
            ],
            [
                'nama_jenis_luaran' => 'HKI',
                'indeks' => null,
            ],
            [
                'nama_jenis_luaran' => 'Produk',
                'indeks' => null,
            ],
        ];

        foreach ($data as $item) {
            JenisLuaran::firstOrCreate(
                [
                    'nama_jenis_luaran' => $item['nama_jenis_luaran'],
                    'indeks' => $item['indeks'],
                ]
            );
        }
    }
}
