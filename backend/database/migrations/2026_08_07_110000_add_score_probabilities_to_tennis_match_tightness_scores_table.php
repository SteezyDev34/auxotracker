<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScoreProbabilitiesToTennisMatchTightnessScoresTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            // Probabilité (%) d'atteindre au moins une fois ce score dans le 1er set,
            // calculée analytiquement (modèle binomial par jeu) à partir du %
            // de points gagnés au service de chaque joueur.
            $table->decimal('prob_15a_set1', 5, 2)->nullable()->after('breakdown');
            $table->decimal('prob_30a_set1', 5, 2)->nullable()->after('prob_15a_set1');
            $table->decimal('prob_40a_set1', 5, 2)->nullable()->after('prob_30a_set1');
        });
    }

    public function down()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            $table->dropColumn(['prob_15a_set1', 'prob_30a_set1', 'prob_40a_set1']);
        });
    }
}
