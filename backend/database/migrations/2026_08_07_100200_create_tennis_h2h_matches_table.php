<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTennisH2hMatchesTable extends Migration
{
    public function up()
    {
        Schema::create('tennis_h2h_matches', function (Blueprint $table) {
            $table->bigIncrements('id');
            // event_id du match du jour pour lequel ce H2H a été récupéré
            $table->unsignedBigInteger('current_event_id');
            // event_id de la confrontation passée
            $table->unsignedBigInteger('h2h_event_id');
            $table->string('tournament_name')->nullable();
            $table->string('ground_type')->nullable();
            $table->date('played_at')->nullable();
            $table->unsignedBigInteger('home_team_sofascore_id')->nullable();
            $table->unsignedBigInteger('away_team_sofascore_id')->nullable();
            $table->json('home_score')->nullable();
            $table->json('away_score')->nullable();
            $table->unsignedTinyInteger('winner_code')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['current_event_id', 'h2h_event_id'], 'tennis_h2h_current_event_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tennis_h2h_matches');
    }
}
