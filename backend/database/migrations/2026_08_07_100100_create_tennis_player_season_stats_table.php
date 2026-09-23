<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTennisPlayerSeasonStatsTable extends Migration
{
    public function up()
    {
        Schema::create('tennis_player_season_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id');
            $table->string('ground_type');
            $table->unsignedSmallInteger('season_year');
            $table->unsignedInteger('aces')->nullable();
            $table->unsignedInteger('double_faults')->nullable();
            $table->unsignedInteger('first_serve_points_scored')->nullable();
            $table->unsignedInteger('first_serve_points_total')->nullable();
            $table->unsignedInteger('second_serve_points_scored')->nullable();
            $table->unsignedInteger('second_serve_points_total')->nullable();
            $table->unsignedInteger('break_points_scored')->nullable();
            $table->unsignedInteger('break_points_total')->nullable();
            $table->unsignedInteger('opponent_break_points_scored')->nullable();
            $table->unsignedInteger('opponent_break_points_total')->nullable();
            $table->unsignedInteger('tiebreaks_won')->nullable();
            $table->unsignedInteger('tiebreaks_losses')->nullable();
            $table->unsignedInteger('wins')->nullable();
            $table->unsignedInteger('matches_count')->nullable();
            $table->unsignedInteger('tournaments_played')->nullable();
            $table->unsignedInteger('tournaments_won')->nullable();
            $table->unsignedInteger('winners_total')->nullable();
            $table->unsignedInteger('unforced_errors_total')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'ground_type', 'season_year'], 'tennis_stats_team_ground_year_unique');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tennis_player_season_stats');
    }
}
