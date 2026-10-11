<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KerjaSama extends Model
{
    protected $table = 'kerja_sama';
    protected $primaryKey = 'id_kerja_sama';
    protected $guarded = ['id_kerja_sama'];

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class, 'id_mitra', 'id_mitra');
    }

    // RELASI KE AKTIVITAS IMPLEMENTASI KERJA SAMA
    public function aktivitas(): HasMany
    {
        return $this->hasMany(AktivitasKerjaSama::class, 'id_kerja_sama', 'id_kerja_sama');
    }
}
