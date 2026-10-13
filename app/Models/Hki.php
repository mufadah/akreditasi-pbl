<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hki extends Model
{
    protected $table = 'hki';

    protected $primaryKey = 'id_hki';

    protected $fillable = [
        'id_penelitian',
        'id_dosen',
        'id_jenis_hki',
        'judul_hki',
        'nomor_hki',
        'tahun',
        'id_evidence',
    ];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }

    public function jenisHki(): BelongsTo
    {
        return $this->belongsTo(JenisHki::class, 'id_jenis_hki', 'id_jenis_hki');
    }

    public function penelitian(): BelongsTo
    {
        return $this->belongsTo(Penelitian::class, 'id_penelitian', 'id_penelitian');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class, 'id_evidence', 'id_evidence');
    }
}
