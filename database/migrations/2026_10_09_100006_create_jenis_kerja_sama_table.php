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
        if (!Schema::hasTable('jenis_kerja_sama')) {
            Schema::create('jenis_kerja_sama', function (Blueprint $table) {
                $table->id('id_jenis_kerjasama');
                $table->string('nama_jenis_kerjasama');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_kerja_sama');
    }
};
