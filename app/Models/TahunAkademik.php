<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class TahunAkademik extends Model
{
    protected $table = 'tahun_akademik';
    protected $primaryKey = 'id_tahun_akademik';
    protected $guarded = ['id_tahun_akademik'];

    public function penelitian(): HasMany
    {
        return $this->hasMany(Penelitian::class, 'id_tahun_akademik', 'id_tahun_akademik');
    }

    public function pkm(): HasMany
    {
        return $this->hasMany(Pkm::class, 'id_tahun_akademik', 'id_tahun_akademik');
    }
}
