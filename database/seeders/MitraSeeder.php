<?php

namespace Database\Seeders;

use App\Models\Mitra;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MitraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('mitra')) {
            return;
        }

        $mitraList = [
            [
                'nama_mitra' => 'PT Telkom Indonesia (Persero) Tbk',
                'jenis_mitra' => 'BUMN / Telekomunikasi',
                'alamat' => 'Jl. Japati No. 1, Bandung, Jawa Barat',
                'email' => 'corporate@telkom.co.id',
                'telepon' => '022-4521510',
            ],
            [
                'nama_mitra' => 'PT Astra International Tbk',
                'jenis_mitra' => 'Industri Otomotif & Teknologi',
                'alamat' => 'Menara Astra, Jl. Jend. Sudirman Kav. 5-6, Jakarta Pusat',
                'email' => 'csr@astra.co.id',
                'telepon' => '021-50843888',
            ],
            [
                'nama_mitra' => 'Dinas Komunikasi dan Informatika Provinsi Jawa Tengah',
                'jenis_mitra' => 'Instansi Pemerintah Daerah',
                'alamat' => 'Jl. Menteri Supeno I No. 2, Semarang, Jawa Tengah',
                'email' => 'diskominfo@jatengprov.go.id',
                'telepon' => '024-8449411',
            ],
            [
                'nama_mitra' => 'Google Indonesia',
                'jenis_mitra' => 'Industri Teknologi Multinasional',
                'alamat' => 'Pacific Century Place Tower Level 45, SCBD Lot 10, Jakarta Selatan',
                'email' => 'university-relations@google.com',
                'telepon' => '021-29707600',
            ],
            [
                'nama_mitra' => 'Universiti Teknologi Malaysia (UTM)',
                'jenis_mitra' => 'Perguruan Tinggi Luar Negeri',
                'alamat' => 'Skudai, 81310 Johor Bahru, Johor, Malaysia',
                'email' => 'international@utm.my',
                'telepon' => '+60-7-5533333',
            ],
            [
                'nama_mitra' => 'PT Bank Central Asia Tbk (BCA)',
                'jenis_mitra' => 'Perbankan & Financial Technology',
                'alamat' => 'Menara BCA, Grand Indonesia, Jl. M.H. Thamrin No. 1, Jakarta Pusat',
                'email' => 'it-recruitment@bca.co.id',
                'telepon' => '021-23588000',
            ],
            [
                'nama_mitra' => 'PT Global Digital Niaga (Blibli.com)',
                'jenis_mitra' => 'Industri E-Commerce',
                'alamat' => 'Kuningan Barat, Mampang Prapatan, Jakarta Selatan',
                'email' => 'campus-relations@blibli.com',
                'telepon' => '021-80864200',
            ],
            [
                'nama_mitra' => 'Kamar Dagang dan Industri (KADIN) Jawa Tengah',
                'jenis_mitra' => 'Organisasi Dunia Usaha',
                'alamat' => 'Jl. Majapahit No. 120, Semarang, Jawa Tengah',
                'email' => 'sekretariat@kadinjateng.or.id',
                'telepon' => '024-6721990',
            ],
        ];

        foreach ($mitraList as $mitra) {
            Mitra::firstOrCreate(
                ['nama_mitra' => $mitra['nama_mitra']],
                $mitra
            );
        }
    }
}
