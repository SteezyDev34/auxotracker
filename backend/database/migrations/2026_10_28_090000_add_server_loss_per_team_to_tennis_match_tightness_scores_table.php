<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServerLossPerTeamToTennisMatchTightnessScoresTable extends Migration
{
    public function up()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            // Pendant du prob_server_loss_to_love déjà existant (moyenne des 2
            // joueurs), mais PAR JOUEUR : proba que CE joueur précis perde son
            // propre jeu de service 0-40 (le relanceur gagne les 4 premiers
            // points, pas de deuce). Nécessaire pour cibler dynamiquement un
            // scriptType martingale selon QUI sert (demandé le 2026-10-28, cf.
            // 15V1/15V2 côté bot mrtingal — serveur vs relanceur du jeu en cours).
            // Pas de cote de référence confirmée -> pas d'edge, comme prob_030.
            $table->decimal('prob_server_loss1', 5, 2)->nullable()->after('prob_030');
            $table->decimal('prob_server_loss2', 5, 2)->nullable()->after('prob_server_loss1');
        });
    }

    public function down()
    {
        Schema::table('tennis_match_tightness_scores', function (Blueprint $table) {
            $table->dropColumn(['prob_server_loss1', 'prob_server_loss2']);
        });
    }
}
