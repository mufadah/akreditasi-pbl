<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceHistory extends Model
{
    protected $table = 'evidence_history';
    protected $primaryKey = 'id_evidence_history';

    protected $fillable = [
        'id_evidence',
        'old_url_drive',
        'replaced_by',
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class, 'id_evidence', 'id_evidence');
    }
}
