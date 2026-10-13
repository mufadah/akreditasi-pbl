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
        Schema::create('tenaga_kependidikan', function (Blueprint $table) {
            $table->increments('id_tendik');
            $table->unsignedInteger('id_pendidikan');
            $table->string('nama_tendik');
            $table->string('jabatan_tendik');
            $table->string('nip');
            $table->string('sertifikasi')->nullable();
            $table->timestamps();

            $table->foreign('id_pendidikan')->references('id_pendidikan')->on('pendidikan')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenaga_kependidikan');
    }
};
