<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    ];

    protected function casts(): array
    {
        return [
            'id_dosen' => 'integer',
            'id_tendik' => 'integer',
            'tanggal_kedaluwarsa' => 'date:Y-m-d',
        ];
    }

    /**
     * Sinkronkan skema tabel sertifikasi di database MySQL jika masih menggunakan struktur lama.
     */
    public static function syncDatabaseSchema(): void
    {
        static $synced = false;
        if ($synced) {
            return;
        }

        try {
            if (Schema::hasTable('sertifikasi')) {
                // 1. Pastikan kolom url_sertifikasi ada
                if (! Schema::hasColumn('sertifikasi', 'url_sertifikasi')) {
                    Schema::table('sertifikasi', function (Blueprint $table) {
                        $table->string('url_sertifikasi', 2048)->default('')->after('status_sertifikasi');
                    });
                }

                // 2. Jika tipe tahun_sertifikasi masih int/year di MySQL, coba ubah ke date
                if (DB::getDriverName() === 'mysql') {
                    $col = DB::selectOne("
                        SELECT DATA_TYPE 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'sertifikasi' 
                          AND COLUMN_NAME = 'tahun_sertifikasi'
                    ");
                    if ($col && in_array(strtolower($col->DATA_TYPE), ['int', 'integer', 'year', 'smallint', 'bigint'])) {
                        try {
                            DB::statement('ALTER TABLE `sertifikasi` MODIFY COLUMN `tahun_sertifikasi` VARCHAR(20) NULL');
                            DB::statement("UPDATE `sertifikasi` SET `tahun_sertifikasi` = CONCAT(`tahun_sertifikasi`, '-01-01') WHERE LENGTH(`tahun_sertifikasi`) = 4");
                            DB::statement('ALTER TABLE `sertifikasi` MODIFY COLUMN `tahun_sertifikasi` DATE NULL');
                        } catch (\Throwable $e) {
                            // Abaikan jika tidak punya hak akses ALTER
                        }
                    }
                }
            }
            $synced = true;
        } catch (\Throwable $e) {
            $synced = true;
        }
    }

    /**
     * Dapatkan tipe data kolom tahun_sertifikasi di database MySQL saat ini ('int', 'date', dsb).
     */
    public static function getTahunSertifikasiColumnType(): string
    {
        static $type = null;
        if ($type !== null) {
            return $type;
        }

        try {
            if (DB::getDriverName() === 'mysql') {
                $col = DB::selectOne("
                    SELECT DATA_TYPE 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'sertifikasi' 
                      AND COLUMN_NAME = 'tahun_sertifikasi'
                ");
                if ($col) {
                    $type = strtolower($col->DATA_TYPE);

                    return $type;
                }
            }
        } catch (\Throwable $e) {
            // fallback
        }

        $type = 'date';

        return $type;
    }

    /**
     * Pastikan kolom url_sertifikasi ada di tabel database MySQL,
     * atau lakukan auto-migration jika tabel belum memilikinya.
     */
    public static function hasUrlSertifikasiColumn(): bool
    {
        static::syncDatabaseSchema();

        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        try {
            if (Schema::hasTable('sertifikasi')) {
                $hasColumn = Schema::hasColumn('sertifikasi', 'url_sertifikasi');
            } else {
                $hasColumn = false;
            }
        } catch (\Throwable $e) {
            $hasColumn = false;
        }

        return (bool) $hasColumn;
    }

    /**
     * Accessor untuk url_sertifikasi dengan fallback ke tabel evidence jika kolom DB belum tersedia.
     */
    public function getUrlSertifikasiAttribute($value): ?string
    {
        if ($value !== null && $value !== '') {
            return $value;
        }

        if ($this->relationLoaded('evidence')) {
            $evidence = $this->evidence->first();

            return $evidence?->url_evidence ?? $evidence?->url_drive ?? $value;
        }

        return $value;
    }

    /**
     * Accessor untuk tahun_sertifikasi agar selalu mengembalikan format YYYY-MM-DD
     * meskipun disimpan sebagai integer (YYYY) di database MySQL legacy.
     */
    public function getTahunSertifikasiAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = (string) $value;
        if (preg_match('/^\d{4}$/', $str)) {
            return $str.'-01-01';
        }

        if (strlen($str) >= 10) {
            return substr($str, 0, 10);
        }

        return $str;
    }

    /**
     * Mutator agar jika tahun_sertifikasi diisi format 4 digit tahun (YYYY),
     * otomatis dinormalisasi menjadi format tanggal valid (YYYY-01-01).
     */
    public function setTahunSertifikasiAttribute($value): void
    {
        if ($value !== null && $value !== '') {
            if (is_numeric($value) && strlen((string) $value) === 4) {
                $this->attributes['tahun_sertifikasi'] = $value.'-01-01';

                return;
            }
        }
        $this->attributes['tahun_sertifikasi'] = $value;
    }

    protected static function booted(): void
    {
        static::saving(function (Sertifikasi $sertifikasi) {
            static::syncDatabaseSchema();

            if (! static::hasUrlSertifikasiColumn()) {
                unset($sertifikasi->attributes['url_sertifikasi']);
            }

            // Jika di database MySQL kolom tahun_sertifikasi masih bertipe int / integer legacy:
            $colType = static::getTahunSertifikasiColumnType();
            if (in_array($colType, ['int', 'integer', 'year', 'smallint', 'bigint'])) {
                $rawYear = $sertifikasi->attributes['tahun_sertifikasi'] ?? null;
                if ($rawYear) {
                    $sertifikasi->attributes['tahun_sertifikasi'] = (int) substr((string) $rawYear, 0, 4);
                }
            }
        });

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
