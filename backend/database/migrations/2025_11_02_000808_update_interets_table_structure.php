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
        Schema::table('interets', function (Blueprint $table) {
            // Ajouter bankroll_id si pas déjà présent
            if (!Schema::hasColumn('interets', 'bankroll_id')) {
                $table->unsignedBigInteger('bankroll_id')->nullable()->after('user_id')->comment('ID de la bankroll (AUXOBOT par défaut)');
            }

            // Remplacer montant_investi_initial par montant_total_investi_date_versement
            if (Schema::hasColumn('interets', 'montant_investi_initial')) {
                $table->renameColumn('montant_investi_initial', 'montant_total_investi_date_versement');
            } else if (!Schema::hasColumn('interets', 'montant_total_investi_date_versement')) {
                $table->decimal('montant_total_investi_date_versement', 10, 2)->after('bankroll_id')->comment('Montant total investi à la date de versement');
            }

            // Ajouter taux_interet si pas déjà présent
            if (!Schema::hasColumn('interets', 'taux_interet')) {
                $table->decimal('taux_interet', 5, 2)->default(10.00)->after('montant_interet')->comment('Taux d\'intérêt appliqué (%)');
            }

            // Ajouter index sur bankroll_id si pas déjà présent
            if (!Schema::hasIndex('interets', ['user_id', 'bankroll_id'])) {
                $table->index(['user_id', 'bankroll_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interets', function (Blueprint $table) {
            // Supprimer les colonnes ajoutées
            if (Schema::hasColumn('interets', 'bankroll_id')) {
                $table->dropColumn('bankroll_id');
            }

            if (Schema::hasColumn('interets', 'taux_interet')) {
                $table->dropColumn('taux_interet');
            }

            // Remettre le nom original de la colonne
            if (Schema::hasColumn('interets', 'montant_total_investi_date_versement')) {
                $table->renameColumn('montant_total_investi_date_versement', 'montant_investi_initial');
            }

            // Supprimer l'index
            if (Schema::hasIndex('interets', ['user_id', 'bankroll_id'])) {
                $table->dropIndex(['user_id', 'bankroll_id']);
            }
        });
    }
};
