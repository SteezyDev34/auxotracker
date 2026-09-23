<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTennisPlayerGameTensionStatsTable extends Migration
{
    public function up()
    {
        Schema::create('tennis_player_game_tension_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id')->unique();
            $table->unsignedInteger('sample_matches')->default(0);
            $table->unsignedInteger('sample_games_set1')->default(0);
            $table->unsignedInteger('count_reach_15a')->default(0);
            $table->unsignedInteger('count_reach_30a')->default(0);
            $table->unsignedInteger('count_reach_40a')->default(0);
            $table->timestamp('updated_from_cache_at')->nullable();
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tennis_player_game_tension_stats');
    }
}
