<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use Illuminate\Support\Str;

class NbaFrenchNicknamesSeeder extends Seeder
{
    /**
     * Met à jour le champ `nickname` des équipes dont `league_id` = 18749
     * en ajoutant après une virgule le short name (forme courte) et
     * associe chaque équipe aussi à la league 16917 via la table pivot.
     */
    public function run(): void
    {
        // mapping clés possibles -> nom français normalisé
        $mapping = [
            'hawks' => "Hawks d'Atlanta",
            'atlanta' => "Hawks d'Atlanta",

            'celtics' => 'Celtics de Boston',
            'boston' => 'Celtics de Boston',

            'grizzlies' => 'Grizzlies de Memphis',
            'memphis' => 'Grizzlies de Memphis',

            'suns' => 'Suns de Phoenix',
            'phoenix' => 'Suns de Phoenix',

            'spurs' => 'Spurs de San Antonio',
            'san antonio' => 'Spurs de San Antonio',

            'bulls' => 'Bulls de Chicago',
            'chicago' => 'Bulls de Chicago',

            'mavericks' => 'Mavericks de Dallas',
            'dallas' => 'Mavericks de Dallas',

            'timberwolves' => 'Timberwolves du Minnesota',
            'minnesota' => 'Timberwolves du Minnesota',

            'jazz' => "Jazz de l'Utah",
            'utah' => "Jazz de l'Utah",

            'cavaliers' => 'Cavaliers de Cleveland',
            'cleveland' => 'Cavaliers de Cleveland',

            'thunder' => "Thunder d'Oklahoma City",
            'oklahoma city' => "Thunder d'Oklahoma City",

            'pistons' => 'Pistons de Détroit',
            'detroit' => 'Pistons de Détroit',

            'lakers' => 'Lakers de Los Angeles',
            'los angeles lakers' => 'Lakers de Los Angeles',

            'wizards' => 'Wizards de Washington',
            'washington' => 'Wizards de Washington',

            'magic' => "Magic d'Orlando",
            'orlando' => "Magic d'Orlando",

            'nets' => 'Nets de Brooklyn',
            'brooklyn' => 'Nets de Brooklyn',

            'hornets' => 'Hornets de Charlotte',
            'charlotte' => 'Hornets de Charlotte',

            'bucks' => 'Bucks de Milwaukee',
            'milwaukee' => 'Bucks de Milwaukee',

            'rockets' => 'Rockets de Houston',
            'houston' => 'Rockets de Houston',

            'knicks' => 'Knicks de New York',
            'new york' => 'Knicks de New York',

            'clippers' => 'Clippers de Los Angeles',
            'los angeles clippers' => 'Clippers de Los Angeles',

            'trail blazers' => 'Trail Blazers de Portland',
            'trail-blazers' => 'Trail Blazers de Portland',
            'trailblazers' => 'Trail Blazers de Portland',
            'portland' => 'Trail Blazers de Portland',

            'nuggets' => 'Nuggets de Denver',
            'denver' => 'Nuggets de Denver',

            'kings' => 'Kings de Sacramento',
            'sacramento' => 'Kings de Sacramento',

            'sixers' => '76ers de Philadelphie',
            '76ers' => '76ers de Philadelphie',
            'philadelphia' => '76ers de Philadelphie'
        ];

        // mapping full french name -> short name
        $shorts = [
            "Hawks d'Atlanta" => 'Hawks',
            'Celtics de Boston' => 'Celtics',
            'Grizzlies de Memphis' => 'Grizzlies',
            'Suns de Phoenix' => 'Suns',
            'Spurs de San Antonio' => 'Spurs',
            'Bulls de Chicago' => 'Bulls',
            'Mavericks de Dallas' => 'Mavericks',
            'Timberwolves du Minnesota' => 'Timberwolves',
            "Jazz de l'Utah" => 'Jazz',
            'Cavaliers de Cleveland' => 'Cavaliers',
            "Thunder d'Oklahoma City" => 'Thunder',
            'Pistons de Détroit' => 'Pistons',
            'Lakers de Los Angeles' => 'Lakers',
            'Wizards de Washington' => 'Wizards',
            "Magic d'Orlando" => 'Magic',
            'Nets de Brooklyn' => 'Nets',
            'Hornets de Charlotte' => 'Hornets',
            'Bucks de Milwaukee' => 'Bucks',
            'Rockets de Houston' => 'Rockets',
            'Knicks de New York' => 'Knicks',
            'Clippers de Los Angeles' => 'Clippers',
            'Trail Blazers de Portland' => 'Trail Blazers',
            'Nuggets de Denver' => 'Nuggets',
            'Kings de Sacramento' => 'Kings',
            '76ers de Philadelphie' => '76ers'
        ];

        // Récupérer les équipes via la table pivot pour tenir compte des équipes appartenant à plusieurs ligues
        $teams = Team::whereHas('leagues', function ($q) {
            $q->where('leagues.id', 18749);
        })->get();

        foreach ($teams as $team) {
            $source = mb_strtolower(trim($team->name ?? ''));
            $slug = mb_strtolower(trim($team->slug ?? ''));
            $nick = mb_strtolower(trim($team->nickname ?? ''));

            $found = null;

            foreach ([$source, $slug, $nick] as $candidate) {
                if (!$candidate) continue;
                foreach ($mapping as $k => $v) {
                    if (Str::contains($candidate, $k)) {
                        $found = $v;
                        break 2;
                    }
                }
            }

            $finalFull = $found ?? $team->name;

            // déterminer short
            $short = $shorts[$finalFull] ?? null;
            if (!$short) {
                $parts = preg_split('/\s+/', trim($team->name));
                $short = $parts[0] ?? $team->name;
            }

            // Ne jamais écraser nickname existant.
            // Récupérer les segments actuels séparés par des virgules,
            // ajouter le nom français et le short s'ils sont absents.
            $existing = (string) $team->nickname;
            $parts = array_filter(array_map(function ($p) {
                return trim($p);
            }, explode(',', $existing)));

            $lowerParts = array_map('mb_strtolower', $parts);

            // ajouter le nom français si absent
            if (!in_array(mb_strtolower($finalFull), $lowerParts, true)) {
                $parts[] = $finalFull;
                $lowerParts[] = mb_strtolower($finalFull);
            }

            // ajouter le short si absent
            if (!in_array(mb_strtolower($short), $lowerParts, true)) {
                $parts[] = $short;
            }

            $team->nickname = implode(', ', $parts);

            $team->save();

            // associer à league 16917 sans détacher
            try {
                $team->leagues()->syncWithoutDetaching([16917]);
            } catch (\Throwable $e) {
                // silencieux
            }
        }
    }
}
