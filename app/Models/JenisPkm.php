<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisPkm extends Model
{
    protected $table = 'jenis_pkm';

    protected $primaryKey = 'id_jenis_pkm';

    protected $fillable = [
        'nama_jenis_pkm',
        'jenis_pkm',
    ];

    public function pkm(): HasMany
    {
        return $this->hasMany(Pkm::class, 'id_jenis_pkm', 'id_jenis_pkm');
    }
}
