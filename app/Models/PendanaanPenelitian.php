<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendanaanPenelitian extends Model
{
    protected $table = 'pendanaan_penelitian';

    protected $primaryKey = 'id_pendanaan';

    protected $guarded = ['id_pendanaan'];

    public function penelitian(): BelongsTo
    {
        return $this->belongsTo(Penelitian::class, 'id_penelitian', 'id_penelitian');
    }
}
