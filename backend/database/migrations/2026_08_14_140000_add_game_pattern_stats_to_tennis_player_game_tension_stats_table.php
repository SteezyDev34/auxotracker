<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGamePatternStatsToTennisPlayerGameTensionStatsTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_player_game_tension_stats', function (Blueprint $table) {
            // Sur sample_games_set1 (tous les jeux, service + retour) : combien
            // ont vu l'un des deux joueurs mener 30-0 (2 points à 0) à un moment.
            $table->unsignedInteger('count_reach_30love')->default(0)->after('count_reach_40a');
            // Répartition des jeux terminés SANS prolongation (deuce/avantage) —
            // score du perdant au moment du point décisif.
            $table->unsignedInteger('count_game_40_0')->default(0)->after('count_reach_30love');
            $table->unsignedInteger('count_game_40_15')->default(0)->after('count_game_40_0');
            $table->unsignedInteger('count_game_40_30')->default(0)->after('count_game_40_15');
            // Dénominateur + numérateur spécifiques au service de CE joueur
            // (pas symétrique comme les autres compteurs).
            $table->unsignedInteger('sample_service_games_set1')->default(0)->after('count_game_40_30');
            $table->unsignedInteger('count_server_loss_to_love')->default(0)->after('sample_service_games_set1');
        });
    }

    public function down()
    {
        Schema::table('tennis_player_game_tension_stats', function (Blueprint $table) {
            $table->dropColumn([
                'count_reach_30love',
                'count_game_40_0',
                'count_game_40_15',
                'count_game_40_30',
                'sample_service_games_set1',
                'count_server_loss_to_love',
            ]);
        });
    }
}
