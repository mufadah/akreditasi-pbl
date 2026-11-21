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
        'judul_file',       // kolom opsional lama, dipertahankan untuk kompatibilitas
        'id_kerjasama',
        'id_kerja_sama',
        'id_sertifikasi',
        'id_kegiatan',
        'id_luaran',
        'id_penelitian',
        'id_pkm',
        'tipe_entitas',
        'id_entitas',
        'jenis_dokumen',
        'nama_file',        // nama/judul dokumen (teks yang diisi pengguna)
        'path_file',
        'url_drive',        // tautan bukti (URL)
        'url_evidence',     // alias tautan bukti (URL)
        'tanggal_upload',
        'status_validasi',
        'catatan_validasi',
        'divalidasi_oleh',
        'tanggal_validasi',
    ];

    /**
     * Accessor: nama_file mengembalikan nama dokumen teks.
     * Untuk data lama yang masih menyimpan URL di nama_file,
     * kembalikan nilai apa adanya agar tidak merusak data.
     */
    public function getNamaFileAttribute($value): ?string
    {
        return $value;
    }

    /**
     * Accessor: url_drive mengembalikan tautan bukti.
     * Fallback ke url_evidence, lalu ke nama_file jika berupa URL (data lama).
     */
    public function getUrlDriveAttribute($value): ?string
    {
        if (! empty($value)) {
            return $value;
        }

        if (! empty($this->attributes['url_evidence'])) {
            return $this->attributes['url_evidence'];
        }

        // Kompatibilitas data lama: nama_file mungkin berisi URL
        $namaFile = $this->attributes['nama_file'] ?? null;
        if (! empty($namaFile) && filter_var($namaFile, FILTER_VALIDATE_URL)) {
            return $namaFile;
        }

        return null;
    }

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
        return $this->belongsTo(KerjaSama::class, 'id_kerja_sama', 'id_kerja_sama');
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
