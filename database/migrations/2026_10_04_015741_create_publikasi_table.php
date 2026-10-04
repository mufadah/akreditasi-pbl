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
        Schema::create('publikasi', function (Blueprint $table) {
            $table->increments('id_publikasi');
            $table->unsignedInteger('id_dosen');
            $table->unsignedInteger('id_jenis_publikasi');
            $table->unsignedInteger('id_penelitian')->nullable();
            $table->unsignedInteger('id_pkm')->nullable();
            $table->string('judul_publikasi');
            $table->string('nama_jurnal')->nullable();
            $table->integer('tahun');
            $table->string('tautan')->nullable();
            $table->timestamps();

            $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->restrictOnDelete();
            $table->foreign('id_jenis_publikasi')->references('id_jenis_publikasi')->on('jenis_publikasi')->restrictOnDelete();
            $table->foreign('id_penelitian')->references('id_penelitian')->on('penelitian')->nullOnDelete();
            $table->foreign('id_pkm')->references('id_pkm')->on('pkm')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publikasi');
    }
};
