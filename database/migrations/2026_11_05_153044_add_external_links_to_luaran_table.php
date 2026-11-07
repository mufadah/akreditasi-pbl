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
        Schema::table('luaran', function (Blueprint $table) {
            $table->string('tautan_buku', 2048)->nullable();
            $table->string('tautan_jurnal', 2048)->nullable();
            $table->string('tautan_seminar', 2048)->nullable();
            $table->string('tautan_hki', 2048)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('luaran', function (Blueprint $table) {
            $table->dropColumn([
                'tautan_buku',
                'tautan_jurnal',
                'tautan_seminar',
                'tautan_hki',
            ]);
        });
    }
};
