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
            $table->string('nidn')->unique();
            $table->string('nama_dosen');
            $table->string('email')->unique();
            $table->string('status_dosen')->default('DTPS');
            $table->string('sertifikasi')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_jabatan')->references('id_jabatan')->on('jabatan_akademik')->restrictOnDelete();
            $table->foreign('id_pendidikan')->references('id_pendidikan')->on('pendidikan')->restrictOnDelete();
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