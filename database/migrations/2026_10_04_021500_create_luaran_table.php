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
        Schema::create('luaran', function (Blueprint $table) {
            $table->increments('id_luaran');
            $table->unsignedInteger('id_kegiatan')->nullable();
            $table->unsignedInteger('id_dosen');
            $table->unsignedInteger('id_jenis_luaran');

            // 1. Buku
            $table->string('judul_buku')->nullable();
            $table->string('isbn')->nullable();
            $table->string('penerbit_buku')->nullable();
            $table->date('tahun_terbit')->nullable();
            $table->string('jenis_buku')->nullable();
            $table->string('bidang_ilmu')->nullable();
            $table->integer('halaman_buku')->nullable();

            // 2. Jurnal
            $table->string('nama_jurnal')->nullable();
            $table->string('issn')->nullable();
            $table->string('volume')->nullable();
            $table->date('tanggal_terbit')->nullable();
            $table->string('doi')->nullable();
            $table->string('nomor_edisi')->nullable();
            $table->integer('halaman_jurnal')->nullable();
            $table->string('indeksasi')->nullable();

            // 3. Prosiding / Seminar
            $table->string('nama_seminar')->nullable();
            $table->string('nama_prosiding')->nullable();
            $table->string('isbn_prosiding')->nullable();
            $table->string('penyelenggara')->nullable();
            $table->date('tanggal_seminar')->nullable();
            $table->string('lokasi_seminar')->nullable();
            $table->integer('halaman_prosiding')->nullable();
            $table->string('doi_prosiding')->nullable();

            // 4. HKI
            $table->string('jenis_hki')->nullable();
            $table->string('judul_hak_cipta')->nullable();
            $table->string('nomor_pendaftaran_hki')->nullable();
            $table->string('nomor_sertifikat_hki')->nullable();
            $table->date('tanggal_pendaftaran_hki')->nullable();
            $table->date('tanggal_terbit_sertifikat_hki')->nullable();
            $table->string('pemegang_hki')->nullable();
            $table->string('status_hki')->nullable();

            // 5. Produk / Teknologi
            $table->string('nama_produk')->nullable();
            $table->string('jenis_produk')->nullable();
            $table->string('status_pengembangan')->nullable();
            $table->integer('tingkat_kesiapanterapan')->nullable(); // TKT 1-9
            $table->date('tanggal_pembuatan')->nullable();
            $table->text('deskripsi_produk')->nullable();
            $table->string('tautan_produk')->nullable();

            $table->timestamps();

            $table->foreign('id_kegiatan')->references('id_kegiatan')->on('kegiatan')->cascadeOnDelete();
            $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->restrictOnDelete();
            $table->foreign('id_jenis_luaran')->references('id_jenis_luaran')->on('jenis_luaran')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luaran');
    }
};
