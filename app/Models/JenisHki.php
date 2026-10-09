<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisHki extends Model
{
    protected $table = 'jenis_hki';

    protected $primaryKey = 'id_jenis_hki';

    protected $fillable = [
        'nama_hki',
    ];

    public function hki(): HasMany
    {
        return $this->hasMany(Hki::class, 'id_jenis_hki', 'id_jenis_hki');
    }
}
