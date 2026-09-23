<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLostFirstPointOnServeToTennisPlayerGameTensionStatsTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_player_game_tension_stats', function (Blueprint $table) {
            // Sur sample_service_games_set1 (jeux où CE joueur sert) : combien
            // de fois il a perdu le tout premier point (mène 0-15 sur son
            // propre service) — spécifique au service, pas mélangé au retour.
            $table->unsignedInteger('count_lost_first_point_on_serve')->default(0)->after('count_led_15_0');
        });
    }

    public function down()
    {
        Schema::table('tennis_player_game_tension_stats', function (Blueprint $table) {
            $table->dropColumn('count_lost_first_point_on_serve');
        });
    }
}
