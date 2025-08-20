<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     */
    public function up(): void
    {
        Schema::table('users_bankrolls', function (Blueprint $table) {
            // Supprimer le champ bankroll_actual_amount
            $table->dropColumn('bankroll_actual_amount');
            
            // Ajouter le champ bankroll_benefits avec une valeur par défaut de 0
            $table->decimal('bankroll_benefits', 10, 2)->default(0.00)->after('bankroll_start_amount');
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::table('users_bankrolls', function (Blueprint $table) {
            // Supprimer le champ bankroll_benefits
            $table->dropColumn('bankroll_benefits');
            
            // Remettre le champ bankroll_actual_amount
            $table->decimal('bankroll_actual_amount', 10, 2)->default(0.00)->after('bankroll_start_amount');
        });
    }
};