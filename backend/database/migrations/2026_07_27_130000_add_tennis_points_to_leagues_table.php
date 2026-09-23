<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('leagues', function (Blueprint $table) {
            // Points ATP/WTA du tournoi (uniqueTournament.tennisPoints côté Sofascore :
            // 250/500 pour un tour normal, 1000 pour un Masters, 2000 pour un Grand Chelem).
            // Utilisé pour trier "Matchs du jour" tennis avec les Masters en tête, avant
            // le reste des tournois ATP réguliers.
            $table->integer('tennis_points')->nullable()->after('priority');
        });
    }

    public function down()
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('tennis_points');
        });
    }
};
