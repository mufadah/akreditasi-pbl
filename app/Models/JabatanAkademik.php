<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JabatanAkademik extends Model
{
    protected $table = 'jabatan_akademik';
    protected $primaryKey = 'id_jabatan';
    protected $guarded = ['id_jabatan'];

    public function dosen(): HasMany
    {
        return $this->hasMany(Dosen::class, 'id_jabatan', 'id_jabatan');
    }
}
