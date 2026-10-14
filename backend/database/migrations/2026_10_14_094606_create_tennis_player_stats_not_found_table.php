<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTennisPlayerStatsNotFoundTable extends Migration
{
    public function up()
    {
        Schema::create('tennis_player_stats_not_found', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedSmallInteger('season_year');
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->unique(['team_id', 'season_year'], 'tennis_stats_not_found_team_year_unique');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tennis_player_stats_not_found');
    }
}
