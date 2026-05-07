<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_id')->unique()->nullable();
            $table->unsignedBigInteger('team_1_sofascore_id')->nullable();
            $table->unsignedBigInteger('team_2_sofascore_id')->nullable();
            $table->date('match_start_date')->nullable();
            $table->time('match_start_time')->nullable();
            $table->unsignedBigInteger('tournament_sofascore_id')->nullable();
            $table->unsignedBigInteger('sport_id')->nullable();
            $table->string('sofascore_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('matches');
    }
}
