<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('evidence')) {
            Schema::table('evidence', function (Blueprint $table) {
                if (!Schema::hasColumn('evidence', 'nama_file')) {
                    $table->string('nama_file')->nullable()->after('jenis_dokumen');
                }
                if (!Schema::hasColumn('evidence', 'path_file')) {
                    $table->string('path_file')->nullable()->after('nama_file');
                }
                if (!Schema::hasColumn('evidence', 'tanggal_upload')) {
                    $table->date('tanggal_upload')->nullable()->after('path_file');
                }
                if (!Schema::hasColumn('evidence', 'status_validasi')) {
                    $table->string('status_validasi')->default('Aktif')->nullable()->after('tanggal_upload');
                }
                if (!Schema::hasColumn('evidence', 'id_penelitian')) {
                    $table->unsignedInteger('id_penelitian')->nullable()->after('status_validasi');
                    if (Schema::hasTable('penelitian')) {
                        $table->foreign('id_penelitian')->references('id_penelitian')->on('penelitian')->nullOnDelete();
                    }
                }
                if (!Schema::hasColumn('evidence', 'id_pkm')) {
                    $table->unsignedInteger('id_pkm')->nullable()->after('id_penelitian');
                    if (Schema::hasTable('pkm')) {
                        $table->foreign('id_pkm')->references('id_pkm')->on('pkm')->nullOnDelete();
                    }
                }
                if (!Schema::hasColumn('evidence', 'id_kerja_sama')) {
                    $table->unsignedInteger('id_kerja_sama')->nullable()->after('id_pkm');
                    if (Schema::hasTable('kerja_sama')) {
                        $table->foreign('id_kerja_sama')->references('id_kerja_sama')->on('kerja_sama')->nullOnDelete();
                    }
                }
                if (Schema::hasColumn('evidence', 'url_drive')) {
                    $table->string('url_drive')->nullable()->change();
                }
                if (Schema::hasColumn('evidence', 'tipe_entitas')) {
                    $table->string('tipe_entitas')->nullable()->change();
                }
                if (Schema::hasColumn('evidence', 'id_entitas')) {
                    $table->unsignedInteger('id_entitas')->nullable()->change();
                }
                if (Schema::hasColumn('evidence', 'kode_indikator')) {
                    $table->string('kode_indikator')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('evidence')) {
            Schema::table('evidence', function (Blueprint $table) {
                if (Schema::hasColumn('evidence', 'id_kerja_sama')) {
                    $table->dropForeign(['id_kerja_sama']);
                    $table->dropColumn('id_kerja_sama');
                }
                if (Schema::hasColumn('evidence', 'id_pkm')) {
                    $table->dropForeign(['id_pkm']);
                    $table->dropColumn('id_pkm');
                }
                if (Schema::hasColumn('evidence', 'id_penelitian')) {
                    $table->dropForeign(['id_penelitian']);
                    $table->dropColumn('id_penelitian');
                }
                if (Schema::hasColumn('evidence', 'status_validasi')) {
                    $table->dropColumn('status_validasi');
                }
                if (Schema::hasColumn('evidence', 'tanggal_upload')) {
                    $table->dropColumn('tanggal_upload');
                }
                if (Schema::hasColumn('evidence', 'path_file')) {
                    $table->dropColumn('path_file');
                }
                if (Schema::hasColumn('evidence', 'nama_file')) {
                    $table->dropColumn('nama_file');
                }
            });
        }
    }
};
