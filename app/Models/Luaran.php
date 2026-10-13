<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Luaran extends Model
{
    protected $table = 'luaran';
    protected $primaryKey = 'id_luaran';

    protected $fillable = [
        'id_kegiatan',
        'id_dosen',
        'id_jenis_luaran',

        // Buku
        'judul_buku',
        'isbn',
        'penerbit_buku',
        'tahun_terbit',
        'jenis_buku',
        'bidang_ilmu',
        'halaman_buku',

        // Jurnal
        'nama_jurnal',
        'issn',
        'volume',
        'tanggal_terbit',
        'doi',
        'nomor_edisi',
        'halaman_jurnal',
        'indeksasi',

        // Prosiding
        'nama_seminar',
        'nama_prosiding',
        'isbn_prosiding',
        'penyelenggara',
        'tanggal_seminar',
        'lokasi_seminar',
        'halaman_prosiding',
        'doi_prosiding',

        // HKI
        'jenis_hki',
        'judul_hak_cipta',
        'nomor_pendaftaran_hki',
        'nomor_sertifikat_hki',
        'tanggal_pendaftaran_hki',
        'tanggal_terbit_sertifikat_hki',
        'pemegang_hki',
        'status_hki',

        // Produk
        'nama_produk',
        'jenis_produk',
        'status_pengembangan',
        'tingkat_kesiapanterapan',
        'tanggal_pembuatan',
        'deskripsi_produk',
        'tautan_produk',
    ];

    public function kegiatan()
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }

    public function jenisLuaran()
    {
        return $this->belongsTo(JenisLuaran::class, 'id_jenis_luaran', 'id_jenis_luaran');
    }

    public function evidence()
    {
        return $this->hasMany(Evidence::class, 'id_luaran', 'id_luaran');
    }
}
