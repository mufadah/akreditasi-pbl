<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sertifikasi extends Model
{
    protected $table = 'sertifikasi';
    protected $primaryKey = 'id_sertifikasi';

    protected $fillable = [
        'id_dosen',
        'id_tendik',
        'jenis_sertifikasi',
        'bidang_studi',
        'nomor_registrasi_pendidik',
        'nomor_sk',
        'nomor_sertifikat',
        'lembaga_penerbit',
        'tahun_sertifikasi',
        'tanggal_kedaluwarsa',
        'status_sertifikasi',
    ];

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }

    public function tendik()
    {
        return $this->belongsTo(TenagaKependidikan::class, 'id_tendik', 'id_tendik');
    }

    public function evidence()
    {
        return $this->hasMany(Evidence::class, 'id_sertifikasi', 'id_sertifikasi');
    }
}
