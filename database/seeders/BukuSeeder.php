<?php

namespace Database\Seeders;

use App\Models\Buku;
use App\Models\Dosen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BukuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('buku') || ! Schema::hasTable('dosen')) {
            return;
        }

        $dosens = Dosen::all();
        if ($dosens->isEmpty()) {
            return;
        }

        $dosen1 = $dosens->first();
        $dosen2 = $dosens->skip(1)->first() ?? $dosen1;
        $dosen3 = $dosens->skip(2)->first() ?? $dosen1;

        $daftarBuku = [
            [
                'id_dosen' => $dosen1->id_dosen,
                'judul_buku' => 'Arsitektur Backend Modern dengan Framework Laravel dan Microservices',
                'isbn' => '978-623-01-4401-1',
                'penerbit' => 'Informatika Bandung',
                'tahun' => 2024,
                'anggota' => 'Muhammad Faiz, Ahmad Fauzan',
                'bidang_ilmu' => 'Rekayasa Perangkat Lunak',
                'jenis_buku' => 'Buku Referensi',
                'deskripsi' => 'Membahas arsitektur RESTful API, event-driven, JWT authentication, dan deployment container.',
            ],
            [
                'id_dosen' => $dosen2->id_dosen,
                'judul_buku' => 'Dasar-Dasar Kecerdasan Buatan dan Penerapan Computer Vision',
                'isbn' => '978-623-01-4402-2',
                'penerbit' => 'Penerbit Andi Yogyakarta',
                'tahun' => 2025,
                'anggota' => 'Siti Aminah, Agus Hermawan',
                'bidang_ilmu' => 'Artificial Intelligence',
                'jenis_buku' => 'Buku Ajar',
                'deskripsi' => 'Buku ajar mata kuliah Kecerdasan Buatan untuk mahasiswa teknik informatika semester 5.',
            ],
            [
                'id_dosen' => $dosen3->id_dosen,
                'judul_buku' => 'Keamanan Jaringan Komputer dan Analisis Serangan Siber',
                'isbn' => '978-623-01-4403-3',
                'penerbit' => 'Polines Press',
                'tahun' => 2025,
                'anggota' => 'Agus Hermawan, Rahmat Hidayat',
                'bidang_ilmu' => 'Cybersecurity',
                'jenis_buku' => 'Monograf',
                'deskripsi' => 'Hasil riset mendalam tentang pola serangan Distributed Denial of Service (DDoS) dan teknik mitigasi.',
            ],
        ];

        foreach ($daftarBuku as $buku) {
            Buku::firstOrCreate(
                ['isbn' => $buku['isbn']],
                $buku
            );
        }
    }
}
