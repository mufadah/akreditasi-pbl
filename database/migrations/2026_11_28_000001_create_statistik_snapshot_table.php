<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistik_snapshot', function (Blueprint $table) {
            $table->id('id_snapshot');
            $table->date('tanggal');
            $table->unsignedSmallInteger('tahun');
            $table->json('data');
            $table->timestamps();

            $table->unique(['tanggal', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistik_snapshot');
    }
};