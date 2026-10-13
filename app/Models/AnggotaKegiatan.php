<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggotaKegiatan extends Model
{
    protected $table = 'anggota_kegiatan';

    protected $primaryKey = 'id_anggota_kegiatan';

    protected $fillable = [
        'id_kegiatan',
        'id_dosen',
        'id_mahasiswa',
        'jenis_anggota',
        'peran',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_mahasiswa' => 'integer',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }
}
