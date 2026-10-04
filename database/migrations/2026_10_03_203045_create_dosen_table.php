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
        Schema::create('dosen', function (Blueprint $table) {
            $table->increments('id_dosen');
            $table->unsignedInteger('id_jabatan');
            $table->unsignedInteger('id_pendidikan');
            $table->unsignedInteger('id_bidang_keahlian');
            $table->string('nidn');
            $table->string('nama_dosen');
            $table->string('email');
            $table->string('status_dosen')->default('DTPS');
            $table->string('sertifikasi')->nullable();
            $table->timestamps();

            $table->foreign('id_jabatan')->references('id_jabatan')->on('jabatan_akademik')->restricOnDelete();
            $table->foreign('id_pendidikan')->references('id_pendidikan')->on('pendidikan')->restricOnDelete();
            $table->foreign('id_bidang_keahlian')->references('id_bidang_keahlian')->on('bidang_keahlian')->restricOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosen');
    }
};