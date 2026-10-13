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
        Schema::create('pendanaan_kegiatan', function (Blueprint $table) {
            $table->increments('id_pendanaan');
            $table->unsignedInteger('id_kegiatan');
            $table->string('sumber_dana');
            $table->decimal('jumlah_dana', 15, 2);
            $table->integer('tahun_pendanaan')->nullable();
            $table->string('jenis_pendanaan')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('id_kegiatan')->references('id_kegiatan')->on('kegiatan')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pendanaan_kegiatan');
    }
};
