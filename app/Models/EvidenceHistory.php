<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceHistory extends Model
{
    protected $table = 'evidence_histories';

    protected $fillable = [
        'evidence_id',
        'url_lama',
        'url_baru',
        'alasan_perubahan',
        'diubah_oleh',
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class, 'evidence_id', 'id_evidence');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh', 'id');
    }
}
