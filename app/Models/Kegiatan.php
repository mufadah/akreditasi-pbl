<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kegiatan extends Model
{
    protected $table = 'kegiatan';

    protected $primaryKey = 'id_kegiatan';

    protected $fillable = [
        'id_jenis_kegiatan',
        'judul_kegiatan',
        'lokasi_kegiatan',
        'tahun_usulan',
        'tahun_kegiatan',
        'tahun_pelaksanaan',
        'lama_kegiatan',
        'status_kegiatan',
        'in_kind',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahun_usulan' => 'integer',
            'tahun_kegiatan' => 'integer',
            'tahun_pelaksanaan' => 'integer',
        ];
    }

    public function jenisKegiatan(): BelongsTo
    {
        return $this->belongsTo(JenisKegiatan::class, 'id_jenis_kegiatan', 'id_jenis_kegiatan');
    }

    public function anggotaKegiatan(): HasMany
    {
        return $this->hasMany(AnggotaKegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaKegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function pendanaanKegiatan(): HasMany
    {
        return $this->hasMany(PendanaanKegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function pendanaan(): HasMany
    {
        return $this->hasMany(PendanaanKegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function luaran(): HasMany
    {
        return $this->hasMany(Luaran::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function ketua(): HasOne
    {
        return $this->hasOne(AnggotaKegiatan::class, 'id_kegiatan', 'id_kegiatan')
            ->where('peran', 'Ketua');
    }
}
