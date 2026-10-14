<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pkm extends Model
{
    protected $table = 'pkm';

    protected $primaryKey = 'id_pkm';

    protected $guarded = ['id_pkm'];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class, 'id_mitra', 'id_mitra');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'id_tahun_akademik', 'id_tahun_akademik');
    }

    public function publikasi(): HasMany
    {
        return $this->hasMany(Publikasi::class, 'id_pkm', 'id_pkm');
    }

    public function jenisPkm(): BelongsTo
    {
        return $this->belongsTo(JenisPkm::class, 'id_jenis_pkm', 'id_jenis_pkm');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaPkm::class, 'id_pkm', 'id_pkm');
    }

    public function anggotaPkm(): HasMany
    {
        return $this->hasMany(AnggotaPkm::class, 'id_pkm', 'id_pkm');
    }
}
