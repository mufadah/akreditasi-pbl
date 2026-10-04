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
        Schema::create('bidang_keahlian_dosen', function (Blueprint $table) {
            $table->increments('id_bidang_keahlian_dosen');
            $table->string('nama_bidang_keahlian_dosen');
            $table->unsignedInteger('id_dosen');
            $table->unsignedInteger('id_bidang_keahlian');
            $table->timestamps();

            $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->restricOnDelete();
            $table->foreign('id_bidang_keahlian')->references('id_bidang_keahlian')->on('bidang_keahlian')->restricOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bidang_keahlian_dosen');
    }
};
