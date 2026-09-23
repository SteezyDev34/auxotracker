<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGamePatternProbsToTennisMatchTightnessScoresTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            $table->decimal('prob_30love_set1', 5, 2)->nullable()->after('sample_size');
            $table->decimal('prob_game_40_0', 5, 2)->nullable()->after('prob_30love_set1');
            $table->decimal('prob_game_40_15', 5, 2)->nullable()->after('prob_game_40_0');
            $table->decimal('prob_game_40_30', 5, 2)->nullable()->after('prob_game_40_15');
            // Proba qu'un joueur perde son propre jeu de service à zéro (0-40).
            $table->decimal('prob_server_loss_to_love', 5, 2)->nullable()->after('prob_game_40_30');
        });
    }

    public function down()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            $table->dropColumn([
                'prob_30love_set1',
                'prob_game_40_0',
                'prob_game_40_15',
                'prob_game_40_30',
                'prob_server_loss_to_love',
            ]);
        });
    }
}
