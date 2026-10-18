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
        Schema::create('sertifikasi', function (Blueprint $table) {
            $table->increments('id_sertifikasi');
            $table->unsignedInteger('id_dosen')->nullable();
            $table->unsignedInteger('id_tendik')->nullable();
            $table->string('jenis_sertifikasi');
            $table->string('bidang_studi')->nullable();
            $table->string('nomor_registrasi_pendidik')->nullable();
            $table->string('nomor_sk')->nullable();
            $table->string('nomor_sertifikat')->nullable();
            $table->string('lembaga_penerbit')->nullable();
            $table->date('tahun_sertifikasi');
            $table->date('tanggal_kedaluwarsa')->nullable();
            $table->string('status_sertifikasi')->default('Aktif');
            $table->string('url_sertifikasi')->default('');
            $table->timestamps();

            $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->cascadeOnDelete();
            $table->foreign('id_tendik')->references('id_tendik')->on('tenaga_kependidikan')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sertifikasi');
    }
};
