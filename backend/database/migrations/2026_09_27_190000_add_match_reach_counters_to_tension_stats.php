<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteurs "au moins une fois dans le set" (pas par jeu) — pour un
 * indicateur simple : fréquence historique réelle que 15A/30A/40A/30love
 * apparaisse au moins une fois dans le 1er set d'un match de ce joueur.
 */
class AddMatchReachCountersToTensionStats extends Migration
{
    public function up()
    {
        foreach (['tennis_player_game_tension_stats', 'tennis_player_game_tension_stats_by_tier'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedInteger('count_matches_reach_15a')->default(0)->after('count_reach_15a');
                $t->unsignedInteger('count_matches_reach_30a')->default(0)->after('count_reach_30a');
                $t->unsignedInteger('count_matches_reach_40a')->default(0)->after('count_reach_40a');
                $t->unsignedInteger('count_matches_reach_30love')->default(0)->after('count_reach_30love');
            });
        }

        Schema::table('tennis_match_tightness_scores', function (Blueprint $t) {
            $t->decimal('prob_15a_in_set', 5, 2)->nullable()->after('prob_15a_set1');
            $t->decimal('prob_30a_in_set', 5, 2)->nullable()->after('prob_30a_set1');
            $t->decimal('prob_40a_in_set', 5, 2)->nullable()->after('prob_40a_set1');
            $t->decimal('prob_30love_in_set', 5, 2)->nullable()->after('prob_30love_set1');
        });
    }

    public function down()
    {
        foreach (['tennis_player_game_tension_stats', 'tennis_player_game_tension_stats_by_tier'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['count_matches_reach_15a', 'count_matches_reach_30a', 'count_matches_reach_40a', 'count_matches_reach_30love']);
            });
        }
        Schema::table('tennis_match_tightness_scores', function (Blueprint $t) {
            $t->dropColumn(['prob_15a_in_set', 'prob_30a_in_set', 'prob_40a_in_set', 'prob_30love_in_set']);
        });
    }
}
