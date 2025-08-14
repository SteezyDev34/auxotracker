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
        if (Schema::hasTable('sports') && !Schema::hasColumn('sports', 'sofascore_id')) {
            Schema::table('sports', function (Blueprint $table) {
                $table->string('sofascore_id')->nullable()->after('img')->comment('ID Sofascore du sport');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sports', function (Blueprint $table) {
            // Supprimer la colonne sofascore_id si elle existe
            if (Schema::hasColumn('sports', 'sofascore_id')) {
                $table->dropColumn('sofascore_id');
            }
        });
    }
};
