<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\League;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

class ExtractMarketFromBet extends Command
{
    protected $signature = 'bet:extract-market {text} {league_id?}';
    protected $description = 'Extrait le champ market et les équipes d’un texte de pari, puis associe les IDs des équipes si correspondance.';

    public function handle()
    {
        $text = $this->argument('text');
        $leagueId = $this->argument('league_id');

        // Extraction de la partie équipes
        // On ignore tout ce qui précède la séquence "team1 vs team2" (y compris les emojis)
        $pattern = '/(?:[\p{So}\p{Sk}\p{Mn}\p{Me}\p{Cf}\p{P}\p{S}\p{Z}\p{C}]*\s*)?([\p{L}0-9 .\-]+)\s+vs\s+([\p{L}0-9 .\-]+)/u';
        if (preg_match($pattern, $text, $matches)) {
            $team1Name = trim($matches[1]);
            $team2Name = trim($matches[2]);
        } else {
            $this->error('Impossible d’extraire les équipes du texte.');
            return 1;
        }

        // Recherche des équipes dans la ligue avec priorité > 0
        $teamsQuery = Team::query();
        if ($leagueId) {
            $teamsQuery->whereHas('leagues', function ($q) use ($leagueId) {
                $q->where('leagues.id', $leagueId);
            });
        } else {
            // Recherche dans toutes les ligues avec priorité > 0
            $leagueIds = League::where('priority', '>', 0)->pluck('id')->toArray();
            $teamsQuery->whereHas('leagues', function ($q) use ($leagueIds) {
                $q->whereIn('leagues.id', $leagueIds);
            });
        }

        $team1 = $teamsQuery->where('name', 'LIKE', "%$team1Name%")->first();
        $team2 = $teamsQuery->where('name', 'LIKE', "%$team2Name%")->first();

        $result = [
            'market' => $text,
            'team1_name' => $team1Name,
            'team2_name' => $team2Name,
            'team1_id' => $team1 ? $team1->id : null,
            'team2_id' => $team2 ? $team2->id : null,
        ];

        $this->info('Extraction terminée :');
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return 0;
    }
}
