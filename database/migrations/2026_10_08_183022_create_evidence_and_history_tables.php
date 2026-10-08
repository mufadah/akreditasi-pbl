<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel utama Evidence
        Schema::create('evidence', function (Blueprint $table) {
            $table->increments('id_evidence');
            $table->string('url_drive');
            $table->string('tipe_entitas'); // Contoh: 'Penelitian', 'Pkm', 'KerjaSama', 'Dosen'
            $table->unsignedInteger('id_entitas'); // ID dari data yang bersangkutan
            $table->string('jenis_dokumen'); // Misal: 'SK Tugas', 'Laporan Akhir', 'Sertifikat'
            $table->string('kode_indikator'); // Kode indikator dari K3 (misal: 'SDM-01')
            $table->integer('tahun')->nullable();
            $table->enum('status', ['Menunggu Validasi', 'Valid', 'Ditolak', 'Perlu Perbaikan'])
                  ->default('Menunggu Validasi');
            $table->text('catatan_penolakan')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(['tipe_entitas', 'id_entitas']);
            $table->index('kode_indikator');
        });

        // Tabel riwayat penggantian tautan (Fitur Ganti Link)
        Schema::create('evidence_history', function (Blueprint $table) {
            $table->increments('id_evidence_history');
            $table->unsignedInteger('id_evidence');
            $table->string('old_url_drive');
            $table->unsignedBigInteger('replaced_by')->nullable();
            $table->timestamps();

            $table->foreign('id_evidence')
                  ->references('id_evidence')
                  ->on('evidence')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_history');
        Schema::dropIfExists('evidence');
    }
};
