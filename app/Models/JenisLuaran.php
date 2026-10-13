<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisLuaran extends Model
{
    protected $table = 'jenis_luaran';

    protected $primaryKey = 'id_jenis_publikasi';

    protected $fillable = [
        'nama_jenis_luaran',
        'indeks',
    ];

    public function luaran(): HasMany
    {
        return $this->hasMany(Luaran::class, 'id_jenis_luaran', 'id_jenis_publikasi');
    }
}
