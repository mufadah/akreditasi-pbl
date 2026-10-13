<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendanaanKegiatan extends Model
{
    protected $table = 'pendanaan_kegiatan';

    protected $primaryKey = 'id_pendanaan';

    protected $fillable = [
        'id_kegiatan',
        'sumber_dana',
        'jumlah_dana',
        'tahun_pendanaan',
        'jenis_pendanaan',
        'keterangan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah_dana' => 'decimal:2',
            'tahun_pendanaan' => 'integer',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }
}
