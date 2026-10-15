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
            if (! Schema::hasColumn('dosen', 'nuptk')) {
                $table->string('nuptk', 50)->nullable()->after('nidn');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosen', function (Blueprint $table) {
            if (Schema::hasColumn('dosen', 'nuptk')) {
                $table->dropColumn('nuptk');
            }
        });
    }
};
