<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aktivitas_kerja_sama', function (Blueprint $table) {
            $table->increments('id_aktivitas');
            $table->unsignedInteger('id_kerja_sama');
            $table->string('judul_aktivitas');
            $table->date('tanggal_pelaksanaan');
            $table->text('deskripsi')->nullable();
            $table->string('bukti_dokumen')->nullable();
            $table->timestamps();

            $table->foreign('id_kerja_sama')
                  ->references('id_kerja_sama')
                  ->on('kerja_sama')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas_kerja_sama');
    }
};
