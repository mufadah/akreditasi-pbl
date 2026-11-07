<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('luaran', 'tautan_prosiding')) {
            return;
        }

        if (Schema::hasColumn('luaran', 'tautan_seminar')) {
            DB::table('luaran')
                ->whereNull('tautan_seminar')
                ->whereNotNull('tautan_prosiding')
                ->update(['tautan_seminar' => DB::raw('tautan_prosiding')]);

            Schema::table('luaran', function (Blueprint $table) {
                $table->dropColumn('tautan_prosiding');
            });

            return;
        }

        Schema::table('luaran', function (Blueprint $table) {
            $table->renameColumn('tautan_prosiding', 'tautan_seminar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('luaran', 'tautan_seminar') || Schema::hasColumn('luaran', 'tautan_prosiding')) {
            return;
        }

        Schema::table('luaran', function (Blueprint $table) {
            $table->renameColumn('tautan_seminar', 'tautan_prosiding');
        });
    }
};
