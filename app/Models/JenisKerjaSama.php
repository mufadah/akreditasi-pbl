<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisKerjaSama extends Model
{
    protected $table = 'jenis_kerja_sama';

    protected $primaryKey = 'id_jenis_kerjasama';

    protected $fillable = [
        'nama_jenis_kerjasama',
    ];

    public function kerjaSama(): HasMany
    {
        return $this->hasMany(KerjaSama::class, 'id_jenis_kerjasama', 'id_jenis_kerjasama');
    }
}
