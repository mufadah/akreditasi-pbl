<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggotaPkm extends Model
{
    protected $table = 'anggota_pkm';

    protected $primaryKey = 'id_anggota_pkm';

    protected $fillable = [
        'id_pkm',
        'id_mahasiswa',
        'peran',
        'semester',
    ];

    public function pkm(): BelongsTo
    {
        return $this->belongsTo(Pkm::class, 'id_pkm', 'id_pkm');
    }
}
