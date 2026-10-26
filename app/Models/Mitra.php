<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mitra extends Model
{
    protected $table = 'mitra';

    protected $primaryKey = 'id_mitra';

    protected $guarded = ['id_mitra'];

    protected $appends = ['nama_instansi'];

    /**
     * Accessor alias untuk backward compatibility dengan Swagger / Postman (nama_instansi <-> nama_mitra).
     */
    public function getNamaInstansiAttribute(): ?string
    {
        return $this->attributes['nama_mitra'] ?? null;
    }

    public function kerjaSama(): HasMany
    {
        return $this->hasMany(KerjaSama::class, 'id_mitra', 'id_mitra');
    }
}
