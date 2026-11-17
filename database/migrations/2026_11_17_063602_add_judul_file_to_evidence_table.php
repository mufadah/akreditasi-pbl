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
                if (! Schema::hasColumn('evidence', 'judul_file')) {
                    $table->string('judul_file')->nullable()->after('id_evidence');
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
                if (Schema::hasColumn('evidence', 'judul_file')) {
                    $table->dropColumn('judul_file');
                }
            });
        }
    }
};
