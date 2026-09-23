<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sofascore_event_id')->unique();
            $table->unsignedBigInteger('home_team_sofascore_id')->nullable();
            $table->unsignedBigInteger('away_team_sofascore_id')->nullable();
            $table->unsignedTinyInteger('winner_code')->nullable()->comment('1=home, 2=away, 3=draw');
            $table->string('status_type', 30)->default('unknown');
            $table->tinyInteger('home_sets')->nullable();
            $table->tinyInteger('away_sets')->nullable();
            $table->json('score_json')->nullable();
            $table->json('stats_json')->nullable();
            $table->json('point_by_point_json')->nullable();
            $table->timestamp('match_timestamp')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('sofascore_event_id')->nullable()->after('event_date');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('sofascore_event_id');
        });
        Schema::dropIfExists('match_results');
    }
};
