<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étend les compteurs "au moins une fois dans le set" aux marchés restants
 * (patterns de fin de jeu, mène 15-0, perd 1er point service) — même logique
 * que count_matches_reach_15a/30a/40a/30love, tous ces marchés étant sujets
 * à la même mécanique de martingale jouée sur tout le set.
 */
class AddMoreMatchReachCountersToTensionStats extends Migration
{
    public function up()
    {
        foreach (['tennis_player_game_tension_stats', 'tennis_player_game_tension_stats_by_tier'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedInteger('count_matches_reach_g40_0')->default(0)->after('count_game_40_0');
                $t->unsignedInteger('count_matches_reach_g40_15')->default(0)->after('count_game_40_15');
                $t->unsignedInteger('count_matches_reach_g40_30')->default(0)->after('count_game_40_30');
                $t->unsignedInteger('count_matches_led_15_0')->default(0)->after('count_led_15_0');
                $t->unsignedInteger('count_matches_lost_first_point_on_serve')->default(0)->after('count_lost_first_point_on_serve');
            });
        }

        Schema::table('tennis_match_tightness_scores', function (Blueprint $t) {
            $t->decimal('prob_game_40_0_in_set', 5, 2)->nullable()->after('prob_game_40_0');
            $t->decimal('prob_game_40_15_in_set', 5, 2)->nullable()->after('prob_game_40_15');
            $t->decimal('prob_game_40_30_in_set', 5, 2)->nullable()->after('prob_game_40_30');
            $t->decimal('prob_team1_leads_15_0_in_set', 5, 2)->nullable()->after('prob_team1_leads_15_0');
            $t->decimal('prob_team2_leads_15_0_in_set', 5, 2)->nullable()->after('prob_team2_leads_15_0');
            $t->decimal('prob_lost_serve1_in_set', 5, 2)->nullable()->after('prob_lost_serve1');
            $t->decimal('prob_lost_serve2_in_set', 5, 2)->nullable()->after('prob_lost_serve2');
        });
    }

    public function down()
    {
        foreach (['tennis_player_game_tension_stats', 'tennis_player_game_tension_stats_by_tier'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn([
                    'count_matches_reach_g40_0', 'count_matches_reach_g40_15', 'count_matches_reach_g40_30',
                    'count_matches_led_15_0', 'count_matches_lost_first_point_on_serve',
                ]);
            });
        }
        Schema::table('tennis_match_tightness_scores', function (Blueprint $t) {
            $t->dropColumn([
                'prob_game_40_0_in_set', 'prob_game_40_15_in_set', 'prob_game_40_30_in_set',
                'prob_team1_leads_15_0_in_set', 'prob_team2_leads_15_0_in_set',
                'prob_lost_serve1_in_set', 'prob_lost_serve2_in_set',
            ]);
        });
    }
}
