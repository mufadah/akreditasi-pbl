<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pendidikan extends Model
{
    protected $table = 'pendidikan';
    protected $primaryKey = 'id_pendidikan';
    protected $guarded = ['id_pendidikan'];

    public function dosen(): HasMany
    {
        return $this->hasMany(Dosen::class, 'id_pendidikan', 'id_pendidikan');
    }

    public function tenagaKependidikan(): HasMany
    {
        return $this->hasMany(TenagaKependidikan::class, 'id_pendidikan', 'id_pendidikan');
    }
}
