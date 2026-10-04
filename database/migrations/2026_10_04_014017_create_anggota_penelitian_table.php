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
        Schema::create('anggota_penelitian', function (Blueprint $table) {
            $table->increments('id_anggota');
            $table->unsignedInteger('id_penelitian');
            $table->unsignedInteger('id_dosen')->nullable();
            $table->string('mahasiswa')->nullable();
            $table->string('jenis_anggota');
            $table->timestamps();

            $table->foreign('id_penelitian')->references('id_penelitian')->on('penelitian')->cascadeOnDelete();
            $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggota_penelitian');
    }
};
