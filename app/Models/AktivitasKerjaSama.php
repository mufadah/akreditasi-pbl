<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AktivitasKerjaSama extends Model
{
    protected $table = 'aktivitas_kerja_sama';
    protected $primaryKey = 'id_aktivitas';

    protected $fillable = [
        'id_kerja_sama',
        'judul_aktivitas',
        'tanggal_pelaksanaan',
        'deskripsi',
        'bukti_dokumen',
    ];

    public function kerjaSama(): BelongsTo
    {
        return $this->belongsTo(KerjaSama::class, 'id_kerja_sama', 'id_kerja_sama');
    }
}
