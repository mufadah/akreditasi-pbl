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
        if (!Schema::hasTable('buku')) {
            Schema::create('buku', function (Blueprint $table) {
                $table->id('id_buku');
                $table->unsignedInteger('id_dosen');
                $table->string('judul_buku');
                $table->string('isbn')->nullable();
                $table->string('penerbit')->nullable();
                $table->integer('tahun');
                $table->string('anggota')->nullable();
                $table->string('bidang_ilmu');
                $table->string('jenis_buku');
                $table->text('deskripsi')->nullable();
                $table->timestamps();

                if (Schema::hasTable('dosen')) {
                    $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->cascadeOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buku');
    }
};
