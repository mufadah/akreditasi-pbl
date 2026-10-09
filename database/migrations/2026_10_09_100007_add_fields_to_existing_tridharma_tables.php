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
        // 1. Tambah kolom pada tabel publikasi
        if (Schema::hasTable('publikasi')) {
            Schema::table('publikasi', function (Blueprint $table) {
                if (!Schema::hasColumn('publikasi', 'jumlah_sitasi')) {
                    $table->integer('jumlah_sitasi')->default(0)->nullable();
                }
            });
        }

        // 2. Tambah kolom pada tabel kerja_sama
        if (Schema::hasTable('kerja_sama')) {
            Schema::table('kerja_sama', function (Blueprint $table) {
                if (!Schema::hasColumn('kerja_sama', 'id_jenis_kerjasama')) {
                    $table->unsignedBigInteger('id_jenis_kerjasama')->nullable();
                    if (Schema::hasTable('jenis_kerja_sama') && DB::getDriverName() !== 'sqlite') {
                        $table->foreign('id_jenis_kerjasama')
                              ->references('id_jenis_kerjasama')
                              ->on('jenis_kerja_sama')
                              ->nullOnDelete();
                    }
                }

                if (!Schema::hasColumn('kerja_sama', 'id_dosen')) {
                    $table->unsignedInteger('id_dosen')->nullable();
                    if (Schema::hasTable('dosen') && DB::getDriverName() !== 'sqlite') {
                        $table->foreign('id_dosen')
                              ->references('id_dosen')
                              ->on('dosen')
                              ->nullOnDelete();
                    }
                }

                if (!Schema::hasColumn('kerja_sama', 'nomor_dokumen')) {
                    $table->string('nomor_dokumen')->nullable();
                }

                if (!Schema::hasColumn('kerja_sama', 'jenis_dokumen')) {
                    $table->string('jenis_dokumen')->nullable();
                }
            });
        }

        // 3. Tambah kolom pada tabel pkm
        if (Schema::hasTable('pkm')) {
            Schema::table('pkm', function (Blueprint $table) {
                if (!Schema::hasColumn('pkm', 'id_jenis_pkm')) {
                    $table->unsignedBigInteger('id_jenis_pkm')->nullable();
                    if (Schema::hasTable('jenis_pkm') && DB::getDriverName() !== 'sqlite') {
                        $table->foreign('id_jenis_pkm')
                              ->references('id_jenis_pkm')
                              ->on('jenis_pkm')
                              ->nullOnDelete();
                    }
                }

                if (!Schema::hasColumn('pkm', 'jenis_pelaksana')) {
                    $table->string('jenis_pelaksana')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Rollback tabel pkm
        if (Schema::hasTable('pkm')) {
            Schema::table('pkm', function (Blueprint $table) {
                if (Schema::hasColumn('pkm', 'id_jenis_pkm')) {
                    if (DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign(['id_jenis_pkm']);
                    }
                    $table->dropColumn('id_jenis_pkm');
                }
                if (Schema::hasColumn('pkm', 'jenis_pelaksana')) {
                    $table->dropColumn('jenis_pelaksana');
                }
            });
        }

        // 2. Rollback tabel kerja_sama
        if (Schema::hasTable('kerja_sama')) {
            Schema::table('kerja_sama', function (Blueprint $table) {
                if (Schema::hasColumn('kerja_sama', 'id_jenis_kerjasama')) {
                    if (DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign(['id_jenis_kerjasama']);
                    }
                    $table->dropColumn('id_jenis_kerjasama');
                }
                if (Schema::hasColumn('kerja_sama', 'id_dosen')) {
                    if (DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign(['id_dosen']);
                    }
                    $table->dropColumn('id_dosen');
                }
                if (Schema::hasColumn('kerja_sama', 'nomor_dokumen')) {
                    $table->dropColumn('nomor_dokumen');
                }
                if (Schema::hasColumn('kerja_sama', 'jenis_dokumen')) {
                    $table->dropColumn('jenis_dokumen');
                }
            });
        }

        // 3. Rollback tabel publikasi
        if (Schema::hasTable('publikasi')) {
            Schema::table('publikasi', function (Blueprint $table) {
                if (Schema::hasColumn('publikasi', 'jumlah_sitasi')) {
                    $table->dropColumn('jumlah_sitasi');
                }
            });
        }
    }
};
