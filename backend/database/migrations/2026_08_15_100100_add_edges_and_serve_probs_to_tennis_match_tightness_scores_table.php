<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEdgesAndServeProbsToTennisMatchTightnessScoresTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            // Edges (cotes de référence : 30-0=2.2, jeu 40-0/15/30=3.0 chacun)
            $table->decimal('edge_30love', 5, 2)->nullable()->after('prob_team2_leads_15_0');
            $table->decimal('edge_g40_0', 5, 2)->nullable()->after('edge_30love');
            $table->decimal('edge_g40_15', 5, 2)->nullable()->after('edge_g40_0');
            $table->decimal('edge_g40_30', 5, 2)->nullable()->after('edge_g40_15');
            // Edge "mène 15-0" par joueur (cote 1.4)
            $table->decimal('edge_leads1', 5, 2)->nullable()->after('edge_g40_30');
            $table->decimal('edge_leads2', 5, 2)->nullable()->after('edge_leads1');
            // Nouvelle stat : perd le 1er point sur SON service (cote 2), par joueur
            $table->decimal('prob_lost_serve1', 5, 2)->nullable()->after('edge_leads2');
            $table->decimal('prob_lost_serve2', 5, 2)->nullable()->after('prob_lost_serve1');
            $table->decimal('edge_lost_serve1', 5, 2)->nullable()->after('prob_lost_serve2');
            $table->decimal('edge_lost_serve2', 5, 2)->nullable()->after('edge_lost_serve1');
        });
    }

    public function down()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            $table->dropColumn([
                'edge_30love', 'edge_g40_0', 'edge_g40_15', 'edge_g40_30',
                'edge_leads1', 'edge_leads2',
                'prob_lost_serve1', 'prob_lost_serve2', 'edge_lost_serve1', 'edge_lost_serve2',
            ]);
        });
    }
}
