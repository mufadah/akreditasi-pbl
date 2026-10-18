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
        Schema::create('evidence', function (Blueprint $table) {
            $table->increments('id_evidence');
            $table->unsignedInteger('id_kerja_sama')->nullable();
            $table->unsignedInteger('id_kerjasama')->nullable();
            $table->unsignedInteger('id_sertifikasi')->nullable();
            $table->unsignedInteger('id_kegiatan')->nullable();
            $table->unsignedInteger('id_luaran')->nullable();

            $table->string('jenis_dokumen'); // Proposal, Kontrak, Laporan, Publikasi, HKI, Sertifikat
            $table->string('nama_file');    // URL Google Drive / path dokumen
            $table->string('path_file')->nullable();
            $table->string('url_drive')->nullable();
            $table->string('url_evidence')->nullable();
            $table->date('tanggal_upload')->useCurrent();
            $table->string('status_validasi')->default('Menunggu'); // Menunggu, Valid, Ditolak, Perlu Perbaikan
            $table->text('catatan_validasi')->nullable();
            $table->unsignedBigInteger('divalidasi_oleh')->nullable();
            $table->timestamp('tanggal_validasi')->nullable();
            $table->timestamps();

            $table->foreign('id_kerja_sama')->references('id_kerja_sama')->on('kerja_sama')->cascadeOnDelete();
            $table->foreign('id_sertifikasi')->references('id_sertifikasi')->on('sertifikasi')->cascadeOnDelete();
            $table->foreign('id_kegiatan')->references('id_kegiatan')->on('kegiatan')->cascadeOnDelete();
            $table->foreign('id_luaran')->references('id_luaran')->on('luaran')->cascadeOnDelete();
        });

        Schema::create('evidence_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('evidence_id');
            $table->string('url_lama');
            $table->string('url_baru');
            $table->string('alasan_perubahan')->nullable();
            $table->unsignedBigInteger('diubah_oleh')->nullable();
            $table->timestamps();

            $table->foreign('evidence_id')->references('id_evidence')->on('evidence')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_histories');
        Schema::dropIfExists('evidence');
    }
};
