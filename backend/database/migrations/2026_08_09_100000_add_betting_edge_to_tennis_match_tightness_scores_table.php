<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBettingEdgeToTennisMatchTightnessScoresTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            // Points de pourcentage au-dessus (positif) ou en-dessous (négatif)
            // du seuil de rentabilité (1/cote) pour un pari "martingale" par
            // jeu sur 15A/30A/40A. Cotes de référence : 1.85 / 2.40 / 3.00.
            $table->decimal('edge_15a', 5, 2)->nullable()->after('prob_40a_set1');
            $table->decimal('edge_30a', 5, 2)->nullable()->after('edge_15a');
            $table->decimal('edge_40a', 5, 2)->nullable()->after('edge_30a');
        });
    }

    public function down()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            $table->dropColumn(['edge_15a', 'edge_30a', 'edge_40a']);
        });
    }
}
