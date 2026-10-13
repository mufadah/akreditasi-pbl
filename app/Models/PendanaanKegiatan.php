<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendanaanKegiatan extends Model
{
    protected $table = 'pendanaan_kegiatan';
    protected $primaryKey = 'id_pendanaan';

    protected $fillable = [
        'id_kegiatan',
        'sumber_dana',
        'jumlah_dana',
        'tahun_pendanaan',
        'jenis_pendanaan',
        'keterangan',
    ];

    public function kegiatan()
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }
}
