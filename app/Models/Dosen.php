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
        return $this->belongsToMany(BidangKeahlian::class, 'bidang_keahlian_dosen', 'id_dosen', 'id_bidang_keahlian')
            ->withPivot('nama_bidang_keahlian_dosen')
            ->withTimestamps();
    }

    public function sertifikasi(): HasMany
    {
        return $this->hasMany(Sertifikasi::class, 'id_dosen', 'id_dosen');
    }

    public function kerjaSama(): HasMany
    {
        return $this->hasMany(KerjaSama::class, 'id_dosen', 'id_dosen');
    }

    public function anggotaKegiatan(): HasMany
    {
        return $this->hasMany(AnggotaKegiatan::class, 'id_dosen', 'id_dosen');
    }

    public function luaran(): HasMany
    {
        return $this->hasMany(Luaran::class, 'id_dosen', 'id_dosen');
    }

    // Backward-compatibility aliases for analytics & controllers
    public function kegiatan(): HasMany
    {
        return $this->hasMany(AnggotaKegiatan::class, 'id_dosen', 'id_dosen');
    }

    public function publikasi(): HasMany
    {
        return $this->hasMany(Luaran::class, 'id_dosen', 'id_dosen');
    }

    public function buku(): HasMany
    {
        return $this->hasMany(Buku::class, 'id_dosen', 'id_dosen');
    }

    public function hki(): HasMany
    {
        return $this->hasMany(Hki::class, 'id_dosen', 'id_dosen');
    }

    public function penelitian(): BelongsToMany
    {
        return $this->belongsToMany(Kegiatan::class, 'anggota_kegiatan', 'id_dosen', 'id_kegiatan')
            ->whereHas('jenisKegiatan', function ($q) {
                $q->where('nama_jenis_kegiatan', 'like', '%Penelitian%');
            });
    }

    public function pkm(): BelongsToMany
    {
        return $this->belongsToMany(Kegiatan::class, 'anggota_kegiatan', 'id_dosen', 'id_kegiatan')
            ->whereHas('jenisKegiatan', function ($q) {
                $q->where('nama_jenis_kegiatan', 'like', '%PkM%');
            });
    }
}
