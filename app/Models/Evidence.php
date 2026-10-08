<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evidence extends Model
{
    protected $table = 'evidence';
    protected $primaryKey = 'id_evidence';

    protected $fillable = [
        'url_drive',
        'tipe_entitas',
        'id_entitas',
        'jenis_dokumen',
        'kode_indikator',
        'tahun',
        'status',
        'catatan_penolakan',
        'uploaded_by',
        'validated_by',
        'validated_at',
    ];

    public function history(): HasMany
    {
        return $this->hasMany(EvidenceHistory::class, 'id_evidence', 'id_evidence');
    }
}
