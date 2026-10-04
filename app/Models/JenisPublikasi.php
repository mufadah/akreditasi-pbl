<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisPublikasi extends Model
{
    protected $table = 'jenis_publikasi';

    protected $primaryKey = 'id_jenis_publikasi';
    protected $guarded = ['id_jenis_publikasi'];

    public function publikasi(): HasMany
    {
        return $this->hasMany(Publikasi::class, 'id_jenis_publikasi', 'id_jenis_publikasi');
    }
}
