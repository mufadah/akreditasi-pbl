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
        Schema::create('anggota_kegiatan', function (Blueprint $table) {
            $table->increments('id_anggota_kegiatan');
            $table->unsignedInteger('id_kegiatan');
            $table->unsignedInteger('id_dosen')->nullable();
            $table->string('id_mahasiswa')->nullable(); // NIM mahasiswa (API K1)
            $table->string('jenis_anggota'); // dosen / mahasiswa
            $table->string('peran'); // ketua / anggota
            $table->timestamps();

            $table->foreign('id_kegiatan')->references('id_kegiatan')->on('kegiatan')->cascadeOnDelete();
            $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggota_kegiatan');
    }
};
