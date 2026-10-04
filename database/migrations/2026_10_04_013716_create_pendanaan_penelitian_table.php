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
        Schema::create('pendanaan_penelitian', function (Blueprint $table) {
            $table->increments('id_pendanaan');
            $table->unsignedInteger('id_penelitian');
            $table->string('sumber_dana');
            $table->decimal('nominal', 15, 2);
            $table->integer('tahun');
            $table->timestamps();

            $table->foreign('id_penelitian')->references('id_penelitian')->on('penelitian')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pendanaan_penelitian');
    }
};
