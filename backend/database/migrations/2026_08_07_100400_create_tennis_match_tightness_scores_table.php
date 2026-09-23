<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTennisMatchTightnessScoresTable extends Migration
{
    public function up()
    {
        Schema::create('tennis_match_tightness_scores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_id')->unique();
            $table->unsignedTinyInteger('score');
            $table->json('breakdown')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tennis_match_tightness_scores');
    }
}
