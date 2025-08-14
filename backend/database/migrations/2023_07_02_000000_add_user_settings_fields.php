<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajout des champs de paramètres utilisateur supplémentaires.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ajout des nouvelles colonnes si elles n'existent pas
            if (!Schema::hasColumn('users', 'user_timezone')) {
                $table->string('user_timezone')->default('europe-paris')->after('user_welcome_page');
            }
            
            if (!Schema::hasColumn('users', 'user_display_dashboard')) {
                $table->string('user_display_dashboard')->default('global')->after('user_timezone');
            }
            
            if (!Schema::hasColumn('users', 'user_duplicate_bet_date')) {
                $table->string('user_duplicate_bet_date')->default('today')->after('user_display_dashboard');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Suppression des colonnes ajoutées
            $columns = [
                'user_timezone',
                'user_display_dashboard',
                'user_duplicate_bet_date'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};