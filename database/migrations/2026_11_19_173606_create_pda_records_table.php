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
        Schema::create('pda_records', function (Blueprint $table) {
            $table->id();
            $table->text('judul')->nullable();
            $table->text('penulis')->nullable();
            $table->string('tahun', 20)->nullable();
            $table->string('jenis', 100)->nullable();
            $table->string('link_universal', 2048)->nullable();
            $table->string('duplicate_key', 64)->nullable()->index();
            $table->string('verification_status', 30)->index();
            $table->json('verification_codes')->nullable();
            $table->text('verification_notes')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['duplicate_key', 'verification_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pda_records');
    }
};
