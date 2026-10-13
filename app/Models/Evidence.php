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
        'id_kerjasama',
        'id_sertifikasi',
        'id_kegiatan',
        'id_luaran',
        'jenis_dokumen',
        'nama_file',
        'tanggal_upload',
        'status_validasi',
        'catatan_validasi',
        'divalidasi_oleh',
        'tanggal_validasi',
    ];

    public function kerjaSama(): BelongsTo
    {
        return $this->belongsTo(KerjaSama::class, 'id_kerjasama', 'id_kerjasama');
    }

    public function sertifikasi(): BelongsTo
    {
        return $this->belongsTo(Sertifikasi::class, 'id_sertifikasi', 'id_sertifikasi');
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function luaran(): BelongsTo
    {
        return $this->belongsTo(Luaran::class, 'id_luaran', 'id_luaran');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh', 'id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EvidenceHistory::class, 'evidence_id', 'id_evidence');
    }
}
