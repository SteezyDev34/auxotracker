<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSampleSizeToTennisMatchTightnessScoresTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            // Taille d'échantillon (nb de jeux ou de matchs) derrière le calcul
            // des probas 15A/30A/40A — le plus petit des deux joueurs (maillon
            // faible). Permet de juger la fiabilité directement dans la vue.
            $table->unsignedInteger('sample_size')->nullable()->after('edge_40a');
        });
    }

    public function down()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            $table->dropColumn('sample_size');
        });
    }
}
