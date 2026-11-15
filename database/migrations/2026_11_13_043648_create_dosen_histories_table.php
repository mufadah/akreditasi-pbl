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
        Schema::create('dosen_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_dosen');
            $table->enum('sumber_data', ['PDA', 'MANUAL']);
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('jabatan_akademik')->nullable();
            $table->string('sertifikasi_dosen')->nullable();
            $table->timestamps();

            $table->foreign('id_dosen')->references('id_dosen')->on('dosen')->cascadeOnDelete();
            $table->index(['id_dosen', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosen_histories');
    }
};
