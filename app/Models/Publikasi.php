<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publikasi extends Model
{
    protected $table = 'publikasi';

    protected $primaryKey = 'id_publikasi';

    protected $guarded = ['id_publikasi'];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }

    public function jenisPublikasi(): BelongsTo
    {
        return $this->belongsTo(JenisPublikasi::class, 'id_jenis_publikasi', 'id_jenis_publikasi');
    }

    public function penelitian(): BelongsTo
    {
        return $this->belongsTo(Penelitian::class, 'id_penelitian', 'id_penelitian');
    }

    public function pkm(): BelongsTo
    {
        return $this->belongsTo(Pkm::class, 'id_pkm', 'id_pkm');
    }
}
