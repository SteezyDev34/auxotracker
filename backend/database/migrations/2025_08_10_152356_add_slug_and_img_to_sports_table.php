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
        if (Schema::hasTable('sports')) {
            Schema::table('sports', function (Blueprint $table) {
                if (!Schema::hasColumn('sports', 'slug')) {
                    $table->string('slug')->unique()->after('name');
                }
                if (!Schema::hasColumn('sports', 'img')) {
                    $table->string('img')->nullable()->after('slug');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sports', function (Blueprint $table) {
            // Supprimer les colonnes si elles existent
            if (Schema::hasColumn('sports', 'img')) {
                $table->dropColumn('img');
            }
            
            if (Schema::hasColumn('sports', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
