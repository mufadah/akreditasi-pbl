<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisPenelitian extends Model
{
    protected $table = 'jenis_penelitian';
    protected $primaryKey = 'id_jenis_penelitian';
    protected $guarded = ['id_jenis_penelitian'];

    public function penelitian_penelitian(): HasMany
    {
        return $this->hasMany(Penelitian::class, 'id_jenis_penelitian', 'id_jenis_penelitian');
    }

    public function publikasi(): HasMany
    {
        return $this->hasMany(Publikasi::class, 'id_jenis_penelitian', 'id_jenis_penelitian');
    }
}
