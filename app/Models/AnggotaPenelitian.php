<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggotaPenelitian extends Model
{
    protected $table = 'anggota_penelitian';
    
    protected $primaryKey = 'id_anggota';

    protected $guarded = ['id_anggota'];

    public function penelitian(): BelongsTo
    {
        return $this->belongsTo(Penelitian::class, 'id_penelitian', 'id_penelitian');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }
}
