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
        if (!Schema::hasTable('jenis_pkm')) {
            Schema::create('jenis_pkm', function (Blueprint $table) {
                $table->id('id_jenis_pkm');
                $table->string('nama_jenis_pkm');
                $table->string('jenis_pkm');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_pkm');
    }
};
