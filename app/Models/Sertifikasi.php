<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sertifikasi extends Model
{
    protected $table = 'sertifikasi';
    protected $primaryKey = 'id_sertifikasi';

    protected $fillable = [
        'id_dosen',
        'id_tendik',
        'jenis_sertifikasi',
        'bidang_studi',
        'nomor_registrasi_pendidik',
        'nomor_sk',
        'nomor_sertifikat',
        'lembaga_penerbit',
        'tahun_sertifikasi',
        'tanggal_kedaluwarsa',
        'status_sertifikasi',
        'url_sertifikasi',
    ];

    protected $attributes = [
        'status_sertifikasi' => 'Aktif',
        'url_sertifikasi' => '',
    ];

    protected function casts(): array
    {
        return [
            'tahun_sertifikasi' => 'date:Y-m-d',
            'tanggal_kedaluwarsa' => 'date:Y-m-d',
        ];
    }

    /**
     * Mutator agar jika tahun_sertifikasi diisi format 4 digit tahun (YYYY),
     * otomatis dinormalisasi menjadi format tanggal valid (YYYY-01-01).
     */
    public function setTahunSertifikasiAttribute($value): void
    {
        if ($value !== null && $value !== '') {
            if (is_numeric($value) && strlen((string) $value) === 4) {
                $this->attributes['tahun_sertifikasi'] = $value . '-01-01';
                return;
            }
        }
        $this->attributes['tahun_sertifikasi'] = $value;
    }

    protected static function booted(): void
    {
        static::saved(function (Sertifikasi $sertifikasi) {
            $origDosen = $sertifikasi->getOriginal('id_dosen');
            $origTendik = $sertifikasi->getOriginal('id_tendik');

            static::syncProfil($sertifikasi->id_dosen, $sertifikasi->id_tendik);

            if ($origDosen && $origDosen != $sertifikasi->id_dosen) {
                static::syncProfil($origDosen, null);
            }
            if ($origTendik && $origTendik != $sertifikasi->id_tendik) {
                static::syncProfil(null, $origTendik);
            }
        });

        static::deleted(function (Sertifikasi $sertifikasi) {
            static::syncProfil($sertifikasi->id_dosen, $sertifikasi->id_tendik);
        });
    }

    /**
     * Sinkronkan daftar sertifikasi ke kolom `sertifikasi` pada tabel dosen atau tenaga_kependidikan.
     */
    public static function syncProfil(?int $idDosen, ?int $idTendik): void
    {
        if ($idDosen) {
            $dosen = Dosen::find($idDosen);
            if ($dosen) {
                $daftar = static::where('id_dosen', $idDosen)
                    ->pluck('jenis_sertifikasi')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                $dosen->update(['sertifikasi' => $daftar ?: null]);
            }
        }

        if ($idTendik) {
            $tendik = TenagaKependidikan::find($idTendik);
            if ($tendik) {
                $daftar = static::where('id_tendik', $idTendik)
                    ->pluck('jenis_sertifikasi')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                $tendik->update(['sertifikasi' => $daftar ?: null]);
            }
        }
    }

    /**
     * Sinkronkan seluruh data sertifikasi ke semua profil Dosen & Tendik.
     */
    public static function syncAllProfil(): array
    {
        $dosenIds = static::whereNotNull('id_dosen')->distinct()->pluck('id_dosen');
        $tendikIds = static::whereNotNull('id_tendik')->distinct()->pluck('id_tendik');

        foreach ($dosenIds as $idDosen) {
            static::syncProfil($idDosen, null);
        }

        foreach ($tendikIds as $idTendik) {
            static::syncProfil(null, $idTendik);
        }

        return [
            'total_dosen_disinkron' => $dosenIds->count(),
            'total_tendik_disinkron' => $tendikIds->count(),
        ];
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }

    public function tendik()
    {
        return $this->belongsTo(TenagaKependidikan::class, 'id_tendik', 'id_tendik');
    }

    public function evidence()
    {
        return $this->hasMany(Evidence::class, 'id_sertifikasi', 'id_sertifikasi');
    }
}

