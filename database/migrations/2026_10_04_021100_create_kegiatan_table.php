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
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->increments('id_kegiatan');
            $table->unsignedInteger('id_jenis_kegiatan');
            $table->string('judul_kegiatan');
            $table->string('afiliasi')->nullable();
            $table->string('lokasi_kegiatan')->nullable();
            $table->string('litabmas_sebelumnya')->nullable();
            $table->string('jenis_skim')->nullable();
            $table->integer('tahun_usulan')->nullable();
            $table->integer('tahun_kegiatan')->nullable();
            $table->integer('tahun_pelaksanaan')->nullable();
            $table->string('lama_kegiatan')->nullable();
            $table->integer('tahun_pelaksanaan_ke')->nullable();
            $table->string('nomor_sk_penugasan')->nullable();
            $table->date('tanggal_sk_penugasan')->nullable();
            $table->text('in_kind')->nullable();
            $table->string('status_kegiatan')->default('Usulan');
            $table->timestamps();

            $table->foreign('id_jenis_kegiatan')->references('id_jenis_kegiatan')->on('jenis_kegiatan')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kegiatan');
    }
};
