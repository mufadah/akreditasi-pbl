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
        'id_kerja_sama',
        'id_sertifikasi',
        'id_kegiatan',
        'id_luaran',
        'id_penelitian',
        'id_pkm',
        'tipe_entitas',
        'id_entitas',
        'kode_indikator',
        'jenis_dokumen',
        'nama_file',
        'path_file',
        'url_drive',
        'tanggal_upload',
        'status_validasi',
        'catatan_validasi',
        'divalidasi_oleh',
        'tanggal_validasi',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function luaran(): BelongsTo
    {
        return $this->belongsTo(Luaran::class, 'id_luaran', 'id_luaran');
    }

    public function sertifikasi(): BelongsTo
    {
        return $this->belongsTo(Sertifikasi::class, 'id_sertifikasi', 'id_sertifikasi');
    }

    public function kerjaSama(): BelongsTo
    {
        $fk = ! empty($this->id_kerjasama) ? 'id_kerjasama' : 'id_kerja_sama';

        return $this->belongsTo(KerjaSama::class, $fk, 'id_kerjasama');
    }

    public function penelitian(): BelongsTo
    {
        return $this->belongsTo(Penelitian::class, 'id_penelitian', 'id_penelitian');
    }

    public function pkm(): BelongsTo
    {
        return $this->belongsTo(Pkm::class, 'id_pkm', 'id_pkm');
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
