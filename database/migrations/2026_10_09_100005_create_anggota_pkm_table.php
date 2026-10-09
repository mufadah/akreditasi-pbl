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
        if (!Schema::hasTable('anggota_pkm')) {
            Schema::create('anggota_pkm', function (Blueprint $table) {
                $table->id('id_anggota_pkm');
                $table->unsignedInteger('id_pkm');
                $table->integer('id_mahasiswa');
                $table->string('peran');
                $table->string('semester')->nullable();
                $table->timestamps();

                if (Schema::hasTable('pkm')) {
                    $table->foreign('id_pkm')->references('id_pkm')->on('pkm')->cascadeOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggota_pkm');
    }
};
