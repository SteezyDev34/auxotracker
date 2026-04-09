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
        if (Schema::hasTable('teams') && !Schema::hasColumn('teams', 'short_name')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->string('short_name')->nullable()->after('nickname')->comment('Nom court / abréviation de l\'équipe');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('teams') && Schema::hasColumn('teams', 'short_name')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropColumn('short_name');
            });
        }
    }
};
