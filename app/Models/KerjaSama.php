<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class KerjaSama extends Model
{
    protected $table = 'kerja_sama';

    protected $primaryKey = 'id_kerja_sama';

    protected $guarded = ['id_kerja_sama'];

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class, 'id_mitra', 'id_mitra');
    }
}
