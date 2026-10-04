<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penelitian extends Model
{
    protected $table = 'penelitian';

    protected $primaryKey = 'id_penelitian';

    protected $guarded = ['id_penelitian'];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }

    public function jenisPenelitian(): BelongsTo
    {
        return $this->belongsTo(JenisPenelitian::class, 'id_jenis_penelitian', 'id_jenis_penelitian');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'id_tahun_akademik', 'id_tahun_akademik');
    }

    public function pendanaan(): HasMany
    {
        return $this->hasMany(PendanaanPenelitian::class, 'id_penelitian', 'id_penelitian');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaPenelitian::class, 'id_penelitian', 'id_penelitian');
    }
}
