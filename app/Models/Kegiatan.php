<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kegiatan extends Model
{
    protected $table = 'kegiatan';
    protected $primaryKey = 'id_kegiatan';

    protected $fillable = [
        'id_jenis_kegiatan',
        'judul_kegiatan',
        'afiliasi',
        'lokasi_kegiatan',
        'litabmas_sebelumnya',
        'jenis_skim',
        'tahun_usulan',
        'tahun_kegiatan',
        'tahun_pelaksanaan',
        'lama_kegiatan',
        'tahun_pelaksanaan_ke',
        'nomor_sk_penugasan',
        'tanggal_sk_penugasan',
        'in_kind',
        'status_kegiatan',
    ];

    public function jenisKegiatan()
    {
        return $this->belongsTo(JenisKegiatan::class, 'id_jenis_kegiatan', 'id_jenis_kegiatan');
    }

    public function pendanaan()
    {
        return $this->hasMany(PendanaanKegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function anggota()
    {
        return $this->hasMany(AnggotaKegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function luaran()
    {
        return $this->hasMany(Luaran::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function evidence()
    {
        return $this->hasMany(Evidence::class, 'id_kegiatan', 'id_kegiatan');
    }
}
