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
        Schema::create('penelitian', function (Blueprint $table) {
            $table->increments('id_penelitian');
            $table->unsignedInteger('id_dosen');
            $table->unsignedInteger('id_jenis_penelitian');
            $table->unsignedInteger('id_tahun_akademik');
            $table->string('judul_penelitian');
            $table->integer('tahun');
            $table->string('status')->default('Berjalan');
            $table->timestamps();


            $table->foreign('id_dosen')->references('id_dosen')->on('dosen');
            $table->foreign('id_jenis_penelitian')->references('id_jenis_penelitian')->on('jenis_penelitian')->restrictOnDelete();
            $table->foreign('id_tahun_akademik')->references('id_tahun_akademik')->on('tahun_akademik')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penelitian');
    }
};
