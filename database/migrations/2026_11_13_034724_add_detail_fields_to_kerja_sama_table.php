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
        Schema::table('kerja_sama', function (Blueprint $table) {
            if (! Schema::hasColumn('kerja_sama', 'judul_kerja_sama')) {
                $table->string('judul_kerja_sama')->nullable();
            }

            if (! Schema::hasColumn('kerja_sama', 'tingkat')) {
                $table->string('tingkat', 100)->nullable();
            }

            if (! Schema::hasColumn('kerja_sama', 'bentuk_kegiatan')) {
                $table->string('bentuk_kegiatan')->nullable();
            }

            if (! Schema::hasColumn('kerja_sama', 'bukti_dokumen')) {
                $table->string('bukti_dokumen')->nullable();
            }
        });
    }

    /**
     * Keep the columns because they may have existed before this conditional migration.
     */
    public function down(): void {}
};
