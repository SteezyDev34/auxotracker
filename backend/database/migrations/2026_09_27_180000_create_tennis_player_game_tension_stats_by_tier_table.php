<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Variante de tennis_player_game_tension_stats segmentée par tier de
 * classement de l'ADVERSAIRE (top50 / 50-150 / 150+ / inconnu) — permet de
 * blender (shrinkage bayésien) le taux global d'un joueur avec son taux
 * spécifique face à un adversaire de tel niveau, quand l'échantillon
 * spécifique est trop petit pour être fiable seul.
 */
class CreateTennisPlayerGameTensionStatsByTierTable extends Migration
{
    public function up()
    {
        Schema::create('tennis_player_game_tension_stats_by_tier', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id');
            $table->string('opponent_tier', 20); // top50 | 50-150 | 150+ | inconnu
            $table->unsignedInteger('sample_matches')->default(0);
            $table->unsignedInteger('sample_games_set1')->default(0);
            $table->unsignedInteger('count_reach_15a')->default(0);
            $table->unsignedInteger('count_reach_30a')->default(0);
            $table->unsignedInteger('count_reach_40a')->default(0);
            $table->unsignedInteger('count_reach_30love')->default(0);
            $table->unsignedInteger('count_game_40_0')->default(0);
            $table->unsignedInteger('count_game_40_15')->default(0);
            $table->unsignedInteger('count_game_40_30')->default(0);
            $table->unsignedInteger('sample_service_games_set1')->default(0);
            $table->unsignedInteger('count_server_loss_to_love')->default(0);
            $table->unsignedInteger('count_led_15_0')->default(0);
            $table->unsignedInteger('count_lost_first_point_on_serve')->default(0);
            $table->timestamp('updated_from_cache_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'opponent_tier'], 'tension_stats_by_tier_team_tier_unique');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tennis_player_game_tension_stats_by_tier');
    }
}
