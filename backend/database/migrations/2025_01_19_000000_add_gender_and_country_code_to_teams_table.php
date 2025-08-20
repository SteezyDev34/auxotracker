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
        if (Schema::hasTable('teams')) {
            Schema::table('teams', function (Blueprint $table) {
                // Ajouter la colonne gender si elle n'existe pas
                if (!Schema::hasColumn('teams', 'gender')) {
                    $table->string('gender')->nullable()->after('nickname')->comment('Genre du joueur (M/F) pour le tennis');
                }
                
                // Ajouter la colonne country_code si elle n'existe pas
                if (!Schema::hasColumn('teams', 'country_code')) {
                    $table->string('country_code', 3)->nullable()->after('gender')->comment('Code pays du joueur (ex: FR, US)');
                }
                
                // Modifier la contrainte de clé étrangère league_id pour permettre NULL
                // Ceci est nécessaire pour les joueurs de tennis qui n'appartiennent pas à une ligue
                if (Schema::hasColumn('teams', 'league_id')) {
                    $table->foreignId('league_id')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Supprimer les colonnes ajoutées
            if (Schema::hasColumn('teams', 'country_code')) {
                $table->dropColumn('country_code');
            }
            
            if (Schema::hasColumn('teams', 'gender')) {
                $table->dropColumn('gender');
            }
            
            // Remettre la contrainte NOT NULL sur league_id
            if (Schema::hasColumn('teams', 'league_id')) {
                $table->foreignId('league_id')->nullable(false)->change();
            }
        });
    }
};