<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sertifikasi')) {
            Schema::table('sertifikasi', function (Blueprint $table) {
                if (! Schema::hasColumn('sertifikasi', 'url_sertifikasi')) {
                    $table->string('url_sertifikasi')->default('')->after('status_sertifikasi');
                }
            });

            // Ubah tipe tahun_sertifikasi menjadi date jika masih integer
            try {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE sertifikasi MODIFY COLUMN tahun_sertifikasi VARCHAR(20)');
                    DB::statement("UPDATE sertifikasi SET tahun_sertifikasi = CONCAT(tahun_sertifikasi, '-01-01') WHERE LENGTH(tahun_sertifikasi) = 4");
                    DB::statement('ALTER TABLE sertifikasi MODIFY COLUMN tahun_sertifikasi DATE');
                } else {
                    Schema::table('sertifikasi', function (Blueprint $table) {
                        $table->date('tahun_sertifikasi')->change();
                    });
                }
            } catch (Throwable $e) {
                // Ignore jika driver/data tidak mendukung direct change
            }
        }

        if (Schema::hasTable('evidence')) {
            Schema::table('evidence', function (Blueprint $table) {
                if (! Schema::hasColumn('evidence', 'url_evidence')) {
                    $table->string('url_evidence')->nullable()->after('path_file');
                }
                if (! Schema::hasColumn('evidence', 'url_drive')) {
                    $table->string('url_drive')->nullable()->after('path_file');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sertifikasi')) {
            Schema::table('sertifikasi', function (Blueprint $table) {
                if (Schema::hasColumn('sertifikasi', 'url_sertifikasi')) {
                    $table->dropColumn('url_sertifikasi');
                }
            });
        }

        if (Schema::hasTable('evidence')) {
            Schema::table('evidence', function (Blueprint $table) {
                if (Schema::hasColumn('evidence', 'url_evidence')) {
                    $table->dropColumn('url_evidence');
                }
            });
        }
    }
};
