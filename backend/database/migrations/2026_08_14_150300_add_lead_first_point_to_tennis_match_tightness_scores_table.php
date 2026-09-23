<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLeadFirstPointToTennisMatchTightnessScoresTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            // Proba (%) que CHAQUE joueur (team_1/team_2 du match) mène 15-0
            // à un moment — asymétrique par nature, pas de moyenne fusionnée.
            $table->decimal('prob_team1_leads_15_0', 5, 2)->nullable()->after('prob_server_loss_to_love');
            $table->decimal('prob_team2_leads_15_0', 5, 2)->nullable()->after('prob_team1_leads_15_0');
        });
    }

    public function down()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            $table->dropColumn(['prob_team1_leads_15_0', 'prob_team2_leads_15_0']);
        });
    }
}
