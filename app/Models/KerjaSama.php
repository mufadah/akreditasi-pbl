<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KerjaSama extends Model
{
    protected $table = 'kerja_sama';

    protected $primaryKey = 'id_kerjasama';

    protected $guarded = ['id_kerjasama'];

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class, 'id_mitra', 'id_mitra');
    }

    public function jenisKerjaSama(): BelongsTo
    {
        return $this->belongsTo(JenisKerjaSama::class, 'id_jenis_kerjasama', 'id_jenis_kerjasama');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(AktivitasKerjaSama::class, 'id_kerjasama', 'id_kerjasama');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class, 'id_kerjasama', 'id_kerjasama');
    }
}
