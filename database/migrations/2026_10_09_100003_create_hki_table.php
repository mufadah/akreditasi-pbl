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
        if (!Schema::hasTable('hki')) {
            Schema::create('hki', function (Blueprint $table) {
                $table->id('id_hki');
                $table->unsignedInteger('id_penelitian')->nullable();
                $table->unsignedInteger('id_dosen');
                $table->unsignedBigInteger('id_jenis_hki');
                $table->string('judul_hki');
                $table->string('nomor_hki')->nullable();
                $table->integer('tahun');
                $table->unsignedInteger('id_evidence')->nullable();
                $table->timestamps();

                if (Schema::hasTable('penelitian')) {
                    $table->foreign('id_penelitian')->references('id_penelitian')->on('penelitian')->nullOnDelete();
                }
                if (Schema::hasTable('dosen')) {
                    $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->cascadeOnDelete();
                }
                if (Schema::hasTable('jenis_hki')) {
                    $table->foreign('id_jenis_hki')->references('id_jenis_hki')->on('jenis_hki')->cascadeOnDelete();
                }
                if (Schema::hasTable('evidence')) {
                    $table->foreign('id_evidence')->references('id_evidence')->on('evidence')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hki');
    }
};
