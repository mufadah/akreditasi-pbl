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
            $table->increments('id_kerja_sama');
            $table->unsignedInteger('id_mitra');
            $table->string('judul_kerja_sama');
            $table->string('tingkat');
            $table->string('bentuk_kegiatan');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('bukti_dokumen')->nullable();
            $table->timestamps();

            $table->foreign('id_mitra')->references('id_mitra')->on('mitra')->restrictOnDelete();
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
