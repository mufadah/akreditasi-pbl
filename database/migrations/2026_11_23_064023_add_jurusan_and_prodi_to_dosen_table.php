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
        Schema::table('dosen', function (Blueprint $table) {
            if (! Schema::hasColumn('dosen', 'jurusan')) {
                $table->string('jurusan')->nullable()->after('sertifikasi');
            }

            if (! Schema::hasColumn('dosen', 'prodi')) {
                $table->string('prodi')->nullable()->after('jurusan');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosen', function (Blueprint $table) {
            if (Schema::hasColumn('dosen', 'prodi')) {
                $table->dropColumn('prodi');
            }

            if (Schema::hasColumn('dosen', 'jurusan')) {
                $table->dropColumn('jurusan');
            }
        });
    }
};
