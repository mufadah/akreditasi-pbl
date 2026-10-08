<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel pemetaan indikator K3 ke rumus metrik K2
        Schema::create('konfigurasi_metrik', function (Blueprint $table) {
            $table->increments('id_metrik');
            $table->string('kode_indikator')->unique();
            $table->string('nama_indikator');
            $table->string('bidang'); // 'SDM', 'Penelitian', 'PkM', 'Kerja Sama'
            $table->text('rumus_formula'); // Format ekspresi rumus
            $table->decimal('ambang_target', 8, 2)->nullable();
            $table->string('jenjang')->nullable(); // 'D3', 'D4', 'S1', dll.
            $table->timestamps();
        });

        // Tabel hasil assessment & analisis kesenjangan (Gap Analysis)
        Schema::create('assessment_hasil', function (Blueprint $table) {
            $table->increments('id_assessment');
            $table->string('kode_indikator');
            $table->string('versi_instrumen')->nullable();
            $table->decimal('nilai_metrik', 8, 2)->nullable();
            $table->decimal('skor_capaian', 8, 2)->nullable();
            $table->decimal('skor_target', 8, 2)->nullable();
            $table->enum('status', ['Terpenuhi', 'Gap Terdeteksi', 'Butuh Konfigurasi Admin'])
                  ->default('Butuh Konfigurasi Admin');
            $table->decimal('gap', 8, 2)->nullable();
            $table->text('rekomendasi')->nullable();
            $table->timestamp('dikirim_ke_k3_at')->nullable();
            $table->timestamps();

            $table->index('kode_indikator');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_hasil');
        Schema::dropIfExists('konfigurasi_metrik');
    }
};
