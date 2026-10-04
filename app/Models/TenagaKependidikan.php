<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenagaKependidikan extends Model
{
    protected $table = 'tenaga_kependidikan';
    protected $primaryKey = 'id_tendik';
    protected $guarded = ['id_tendik'];

    public function pendidikan(): BelongsTo
    {
        return $this->belongsTo(Pendidikan::class, 'id_pendidikan', 'id_pendidikan');
    }
}
