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
        Schema::table('sports', function (Blueprint $table) {
            // Ajouter le champ slug s'il n'existe pas
            if (!Schema::hasColumn('sports', 'slug')) {
                $table->string('slug')->nullable()->after('name')->comment('Slug généré à partir du nom du sport');
            }
            
            // Ajouter le champ img s'il n'existe pas
            if (!Schema::hasColumn('sports', 'img')) {
                $table->string('img')->nullable()->after('slug')->comment('Nom du fichier image SVG');
            }
        });
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
