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
        // 1. Master Jenis Kegiatan
        if (! Schema::hasTable('jenis_kegiatan')) {
            Schema::create('jenis_kegiatan', function (Blueprint $table) {
                $table->increments('id_jenis_kegiatan');
                $table->string('nama_jenis_kegiatan');
                $table->text('deskripsi')->nullable();
                $table->timestamps();
            });
        }

        // 2. Master Jenis Luaran (Unified: Buku, Jurnal, Prosiding, HKI, Produk)
        if (! Schema::hasTable('jenis_luaran')) {
            Schema::create('jenis_luaran', function (Blueprint $table) {
                $table->increments('id_jenis_luaran');
                $table->string('nama_jenis_luaran');
                $table->string('indeks')->nullable();
                $table->timestamps();
            });
        }

        // 3. Kegiatan (Unified Tridharma: Pengganti tabel terpisah penelitian & pkm)
        if (! Schema::hasTable('kegiatan')) {
            Schema::create('kegiatan', function (Blueprint $table) {
                $table->increments('id_kegiatan');
                $table->unsignedInteger('id_jenis_kegiatan');
                $table->string('judul_kegiatan');
                $table->string('lokasi_kegiatan')->nullable();
                $table->integer('tahun_usulan')->nullable();
                $table->integer('tahun_kegiatan')->nullable();
                $table->integer('tahun_pelaksanaan')->nullable();
                $table->string('lama_kegiatan')->nullable();
                $table->string('status_kegiatan')->default('Usulan');
                $table->string('in_kind')->nullable();
                $table->timestamps();

                $table->foreign('id_jenis_kegiatan')
                    ->references('id_jenis_kegiatan')
                    ->on('jenis_kegiatan')
                    ->restrictOnDelete();
            });
        }

        // 4. Anggota Kegiatan (Dosen & Mahasiswa)
        if (! Schema::hasTable('anggota_kegiatan')) {
            Schema::create('anggota_kegiatan', function (Blueprint $table) {
                $table->increments('id_anggota_kegiatan');
                $table->unsignedInteger('id_kegiatan');
                $table->unsignedInteger('id_dosen')->nullable();
                $table->unsignedInteger('id_mahasiswa')->nullable();
                $table->enum('jenis_anggota', ['dosen', 'mahasiswa'])->default('dosen');
                $table->string('peran')->default('Anggota');
                $table->timestamps();

                $table->foreign('id_kegiatan')
                    ->references('id_kegiatan')
                    ->on('kegiatan')
                    ->cascadeOnDelete();

                if (Schema::hasTable('dosen')) {
                    $table->foreign('id_dosen')
                        ->references('id_dosen')
                        ->on('dosen')
                        ->nullOnDelete();
                }
            });
        }

        // 5. Pendanaan Kegiatan
        if (! Schema::hasTable('pendanaan_kegiatan')) {
            Schema::create('pendanaan_kegiatan', function (Blueprint $table) {
                $table->increments('id_pendanaan');
                $table->unsignedInteger('id_kegiatan');
                $table->string('sumber_dana');
                $table->decimal('jumlah_dana', 15, 2)->default(0);
                $table->integer('tahun_pendanaan');
                $table->string('jenis_pendanaan')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->foreign('id_kegiatan')
                    ->references('id_kegiatan')
                    ->on('kegiatan')
                    ->cascadeOnDelete();
            });
        }

        // 6. Luaran Unified (Buku, Jurnal, Prosiding, HKI, Produk)
        if (! Schema::hasTable('luaran')) {
            Schema::create('luaran', function (Blueprint $table) {
                $table->increments('id_luaran');
                $table->unsignedInteger('id_kegiatan')->nullable();
                $table->unsignedInteger('id_dosen');
                $table->unsignedInteger('id_jenis_luaran');

                // Field Buku
                $table->string('judul_buku')->nullable();
                $table->string('isbn')->nullable();
                $table->string('penerbit_buku')->nullable();
                $table->integer('tahun_terbit')->nullable();
                $table->string('jenis_buku')->nullable();
                $table->string('bidang_ilmu')->nullable();
                $table->string('halaman_buku')->nullable();

                // Field Jurnal
                $table->string('nama_jurnal')->nullable();
                $table->string('issn')->nullable();
                $table->string('volume')->nullable();
                $table->date('tanggal_terbit')->nullable();
                $table->string('doi')->nullable();
                $table->string('nomor_edisi')->nullable();
                $table->string('halaman_jurnal')->nullable();
                $table->string('indeksasi')->nullable();

                // Field Prosiding
                $table->string('nama_seminar')->nullable();
                $table->string('nama_prosiding')->nullable();
                $table->string('isbn_prosiding')->nullable();
                $table->string('penyelenggara')->nullable();
                $table->date('tanggal_seminar')->nullable();
                $table->string('lokasi_seminar')->nullable();
                $table->string('halaman_prosiding')->nullable();
                $table->string('doi_prosiding')->nullable();

                // Field HKI
                $table->string('jenis_hki')->nullable();
                $table->string('judul_hak_cipta')->nullable();
                $table->string('nomor_pendaftaran_hki')->nullable();
                $table->string('nomor_sertifikat_hki')->nullable();
                $table->date('tanggal_pendaftaran_hki')->nullable();
                $table->date('tanggal_terbit_sertifikat_hki')->nullable();
                $table->string('pemegang_hki')->nullable();
                $table->string('status_hki')->nullable();

                // Field Produk
                $table->string('nama_produk')->nullable();
                $table->string('jenis_produk')->nullable();
                $table->string('status_pengembangan')->nullable();
                $table->string('tingkat_kesiapanterapan')->nullable();
                $table->date('tanggal_pembuatan')->nullable();
                $table->text('deskripsi_produk')->nullable();
                $table->string('tautan_produk')->nullable();

                $table->timestamps();

                $table->foreign('id_kegiatan')
                    ->references('id_kegiatan')
                    ->on('kegiatan')
                    ->nullOnDelete();

                if (Schema::hasTable('dosen')) {
                    $table->foreign('id_dosen')
                        ->references('id_dosen')
                        ->on('dosen')
                        ->cascadeOnDelete();
                }

                $table->foreign('id_jenis_luaran')
                    ->references('id_jenis_luaran')
                    ->on('jenis_luaran')
                    ->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luaran');
        Schema::dropIfExists('pendanaan_kegiatan');
        Schema::dropIfExists('anggota_kegiatan');
        Schema::dropIfExists('kegiatan');
        Schema::dropIfExists('jenis_luaran');
        Schema::dropIfExists('jenis_kegiatan');
    }
};
