<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHoldBreak030ToTennisMatchTightnessScoresTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            // Proba (modèle théorique uniquement, pas d'historique point-by-point
            // agrégé pour ces marchés pour l'instant, cf. prob_server_loss_to_love
            // déjà théorique-only) qu'un jeu DONNÉ soit tenu par le serveur (hold)
            // ou cassé par le relanceur (break = 1 - hold, complémentaire exact).
            $table->decimal('prob_hold', 5, 2)->nullable()->after('edge_lost_serve2');
            $table->decimal('prob_break', 5, 2)->nullable()->after('prob_hold');
            $table->decimal('edge_hold', 5, 2)->nullable()->after('prob_break');
            $table->decimal('edge_break', 5, 2)->nullable()->after('edge_hold');
            // "Au moins un hold/break dans les 10 premiers jeux" (≈5 jeux de
            // service par joueur), même principe de compoundage que prob_*_in_set.
            $table->decimal('prob_hold_in_set', 5, 2)->nullable()->after('edge_break');
            $table->decimal('prob_break_in_set', 5, 2)->nullable()->after('prob_hold_in_set');
            // Proba que le serveur se retrouve mené 0-30 sur son propre jeu —
            // pas de cote de référence pour l'instant (marché moins standard que
            // hold/break, edge laissé null tant qu'on n'a pas de cote observée).
            $table->decimal('prob_030', 5, 2)->nullable()->after('prob_break_in_set');
        });
    }

    public function down()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            $table->dropColumn([
                'prob_hold', 'prob_break', 'edge_hold', 'edge_break',
                'prob_hold_in_set', 'prob_break_in_set', 'prob_030',
            ]);
        });
    }
}
