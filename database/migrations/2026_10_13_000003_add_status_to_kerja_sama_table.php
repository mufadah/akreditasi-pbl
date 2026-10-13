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
        if (Schema::hasTable('kerja_sama')) {
            Schema::table('kerja_sama', function (Blueprint $table) {
                if (! Schema::hasColumn('kerja_sama', 'status')) {
                    $table->string('status', 50)->default('Aktif')->after('tanggal_selesai');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('kerja_sama')) {
            Schema::table('kerja_sama', function (Blueprint $table) {
                if (Schema::hasColumn('kerja_sama', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }
};
