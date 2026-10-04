<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mitra extends Model
{
    protected $table = 'mitra';
    protected $primaryKey = 'id_mitra';
    protected $guarded = ['id_mitra'];

    public function pkm(): HasMany
    {
        return $this->hasMany(Pkm::class, 'id_mitra', 'id_mitra');
    }
    
    public function kerjaSama(): HasMany
    {
        return $this->hasMany(KerjaSama::class, 'id_mitra', 'id_mitra');
    }
}
