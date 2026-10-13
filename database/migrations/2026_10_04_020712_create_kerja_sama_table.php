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
        Schema::create('kerja_sama', function (Blueprint $table) {
            $table->increments('id_kerjasama');
            $table->unsignedInteger('id_mitra');
            $table->unsignedInteger('id_jenis_kerjasama');
            $table->unsignedInteger('id_dosen');
            $table->string('nomor_dokumen');
            $table->string('jenis_dokumen');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->string('status')->default('Aktif');
            $table->timestamps();

            $table->foreign('id_mitra')->references('id_mitra')->on('mitra')->restrictOnDelete();
            $table->foreign('id_jenis_kerjasama')->references('id_jenis_kerjasama')->on('jenis_kerja_sama')->restrictOnDelete();
            $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kerja_sama');
    }
};
