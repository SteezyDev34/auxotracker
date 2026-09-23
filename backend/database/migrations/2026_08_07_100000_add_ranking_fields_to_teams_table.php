<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRankingFieldsToTeamsTable extends Migration
{
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedInteger('ranking')->nullable()->after('coach');
            $table->unsignedInteger('utr_rating')->nullable()->after('ranking');
            $table->unsignedInteger('livetennis_ranking')->nullable()->after('utr_rating');
            $table->timestamp('ranking_updated_at')->nullable()->after('livetennis_ranking');
        });
    }

    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['ranking', 'utr_rating', 'livetennis_ranking', 'ranking_updated_at']);
        });
    }
}
