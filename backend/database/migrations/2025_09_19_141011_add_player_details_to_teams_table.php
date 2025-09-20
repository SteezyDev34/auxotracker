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
        Schema::table('teams', function (Blueprint $table) {
            // Ajouter les colonnes de détails des joueurs si elles n'existent pas
            if (!Schema::hasColumn('teams', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('country_code')->comment('Date de naissance du joueur');
            }
            
            if (!Schema::hasColumn('teams', 'height')) {
                $table->integer('height')->nullable()->after('date_of_birth')->comment('Taille du joueur en cm');
            }
            
            if (!Schema::hasColumn('teams', 'weight')) {
                $table->integer('weight')->nullable()->after('height')->comment('Poids du joueur en kg');
            }
            
            if (!Schema::hasColumn('teams', 'playing_hand')) {
                $table->string('playing_hand')->nullable()->after('weight')->comment('Main dominante (left/right)');
            }
            
            if (!Schema::hasColumn('teams', 'backhand')) {
                $table->string('backhand')->nullable()->after('playing_hand')->comment('Type de revers (one-handed/two-handed)');
            }
            
            if (!Schema::hasColumn('teams', 'birthplace')) {
                $table->string('birthplace')->nullable()->after('backhand')->comment('Lieu de naissance');
            }
            
            if (!Schema::hasColumn('teams', 'residence')) {
                $table->string('residence')->nullable()->after('birthplace')->comment('Lieu de résidence');
            }
            
            if (!Schema::hasColumn('teams', 'coach')) {
                $table->string('coach')->nullable()->after('residence')->comment('Entraîneur');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Supprimer les colonnes ajoutées
            $columns = [
                'coach',
                'residence', 
                'birthplace',
                'backhand',
                'playing_hand',
                'weight',
                'height',
                'date_of_birth'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('teams', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
