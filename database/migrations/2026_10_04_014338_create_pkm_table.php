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
        Schema::create('pkm', function (Blueprint $table) {
            $table->increments('id_pkm');
            $table->unsignedInteger('id_dosen');
            $table->unsignedInteger('id_tahun_akademik');
            $table->unsignedInteger('id_mitra');
            $table->string('judul_pkm');
            $table->string('lokasi')->nullable();
            $table->integer('tahun')->nullable();
            $table->string('status')->default('Selesai');
            $table->timestamps();

            $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->restrictOnDelete();
            $table->foreign('id_tahun_akademik')->references('id_tahun_akademik')->on('tahun_akademik')->restrictOnDelete();
            $table->foreign('id_mitra')->references('id_mitra')->on('mitra')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pkm');
    }
};
