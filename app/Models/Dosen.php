<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dosen extends Model
{
    use SoftDeletes;

    protected $table = 'dosen';

    protected $primaryKey = 'id_dosen';

    protected $guarded = ['id_dosen'];

    public function jabatanAkademik(): BelongsTo
    {
        return $this->belongsTo(JabatanAkademik::class, 'id_jabatan', 'id_jabatan');
    }

    public function pendidikan(): BelongsTo
    {
        return $this->belongsTo(Pendidikan::class, 'id_pendidikan', 'id_pendidikan');
    }

    public function bidangKeahlian(): BelongsToMany
    {
        return $this->belongsToMany(BidangKeahlian::class, 'bidang_keahlian_dosen', 'id_dosen', 'id_bidang_keahlian');
    }

    public function penelitian(): HasMany
    {
        return $this->hasMany(Penelitian::class, 'id_dosen', 'id_dosen');
    }

    public function pkm(): HasMany
    {
        return $this->hasMany(Pkm::class, 'id_dosen', 'id_dosen');
    }
}
