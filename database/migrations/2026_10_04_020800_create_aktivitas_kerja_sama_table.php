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
        Schema::create('aktivitas_kerja_sama', function (Blueprint $table) {
            $table->increments('id_aktivitas');
            $table->unsignedInteger('id_kerjasama');
            $table->string('judul_aktivitas')->nullable();
            $table->date('tanggal_pelaksanaan')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('bukti_dokumen')->nullable();
            $table->timestamps();

            $table->foreign('id_kerjasama')->references('id_kerjasama')->on('kerja_sama')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aktivitas_kerja_sama');
    }
};
