<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * teams.sofascore_id et leagues.sofascore_id sont en varchar(255), alors que
 * matches.team_1_sofascore_id / team_2_sofascore_id / tournament_sofascore_id
 * sont en bigint unsigned. Cette incohérence de type empêche MySQL d'utiliser
 * l'index sur sofascore_id lors des jointures (conversion implicite requise),
 * forçant un scan complet de teams (~90k lignes) à chaque requête — mesuré à
 * 15-40s en prod. Toutes les valeurs existantes sont numériques (vérifié),
 * la conversion est donc sans perte.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ->change() nécessite doctrine/dbal, absent du projet — SQL brut à la place.
        DB::statement('ALTER TABLE teams MODIFY sofascore_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE leagues MODIFY sofascore_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teams MODIFY sofascore_id VARCHAR(255) NULL');
        DB::statement('ALTER TABLE leagues MODIFY sofascore_id VARCHAR(255) NULL');
    }
};
