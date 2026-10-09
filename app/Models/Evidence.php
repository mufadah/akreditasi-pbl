<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evidence extends Model
{
    protected $table = 'evidence';

    protected $primaryKey = 'id_evidence';

    protected $fillable = [
        'url_drive',
        'nama_file',
        'path_file',
        'tanggal_upload',
        'status_validasi',
        'id_penelitian',
        'id_pkm',
        'id_kerja_sama',
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

    public function penelitian(): BelongsTo
    {
        return $this->belongsTo(Penelitian::class, 'id_penelitian', 'id_penelitian');
    }

    public function pkm(): BelongsTo
    {
        return $this->belongsTo(Pkm::class, 'id_pkm', 'id_pkm');
    }

    public function kerjaSama(): BelongsTo
    {
        return $this->belongsTo(KerjaSama::class, 'id_kerja_sama', 'id_kerja_sama');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EvidenceHistory::class, 'id_evidence', 'id_evidence');
    }
}
