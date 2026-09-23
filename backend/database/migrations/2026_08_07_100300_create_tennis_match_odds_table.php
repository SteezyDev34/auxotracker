<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTennisMatchOddsTable extends Migration
{
    public function up()
    {
        Schema::create('tennis_match_odds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('market_id')->nullable();
            $table->string('market_name')->nullable();
            $table->string('choice_name')->nullable();
            $table->string('fractional_value')->nullable();
            $table->decimal('decimal_value', 8, 3)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'market_id', 'choice_name'], 'tennis_odds_event_market_choice_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tennis_match_odds');
    }
}
