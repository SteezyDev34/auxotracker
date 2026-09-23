<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddXyzFooToTennisPlayerGameTensionStatsTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_player_game_tension_stats', function (Blueprint $table) {
            // Nb de jeux (sur sample_games_set1) où CE joueur a gagné le tout
            // premier point (mène 15-0), spécifique au joueur — pas symétrique.
            $table->unsignedInteger('count_led_15_0')->default(0)->after('count_server_loss_to_love');
        });
    }

    public function down()
    {
        Schema::table('tennis_player_game_tension_stats', function (Blueprint $table) {
            $table->dropColumn('count_led_15_0');
        });
    }
}
