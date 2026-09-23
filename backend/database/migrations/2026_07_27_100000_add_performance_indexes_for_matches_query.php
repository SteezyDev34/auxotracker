<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index manquants sur les colonnes utilisées par MatchController::today()
 * (filtre + jointures). Sans eux, chaque requête fait un scan complet des
 * tables matches/teams/leagues — mesuré à 15-30s en prod avant ce fix,
 * malgré une pagination à 50 lignes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->index('match_start_date');
            $table->index('sport_id');
            $table->index('team_1_sofascore_id');
            $table->index('team_2_sofascore_id');
            $table->index('tournament_sofascore_id');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->index('sofascore_id');
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->index('sofascore_id');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['match_start_date']);
            $table->dropIndex(['sport_id']);
            $table->dropIndex(['team_1_sofascore_id']);
            $table->dropIndex(['team_2_sofascore_id']);
            $table->dropIndex(['tournament_sofascore_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['sofascore_id']);
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->dropIndex(['sofascore_id']);
        });
    }
};
