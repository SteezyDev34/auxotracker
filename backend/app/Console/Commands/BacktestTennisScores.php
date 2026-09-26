<?php

namespace App\Console\Commands;

use App\Models\MatchModel;
use App\Models\TennisMatchTightnessScore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BacktestTennisScores extends Command
{
    protected $signature = 'tennis:backtest-scores {--days=30 : Nombre de jours en arrière à analyser} {--limit=1000 : Nombre max de matchs à traiter}';

    protected $description = "Compare les probabilités/edges calculés avant chaque match (tennis_match_tightness_scores) à ce qui s'est réellement passé dans le point-by-point du 1er set, pour vérifier la calibration du modèle et des cotes de référence.";

    /**
     * Cotes de référence — dupliquées depuis ComputeTennisTightnessScores::MARKET_ODDS
     * (pas d'accès direct, la classe ne l'expose pas en public/const accessible ici
     * sans dépendance circulaire de commande à commande).
     */
    private const MARKET_ODDS = [
        '15a' => 1.85, '30a' => 2.40, '40a' => 3.00,
        '30love' => 2.00, 'g40_0' => 3.00, 'g40_15' => 3.00, 'g40_30' => 3.00,
        'leads' => 1.40, 'lost_serve' => 2.00,
    ];

    private const MIN_EDGE = 5;

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');

        $since = now()->subDays($days)->format('Y-m-d');
        $today = now()->format('Y-m-d');

        $matches = MatchModel::where('sport_id', 2)
            ->whereBetween('match_start_date', [$since, $today])
            ->whereNotNull('event_id')
            ->orderByDesc('match_start_date')
            ->limit($limit)
            ->get();

        $this->info("🎾 {$matches->count()} match(s) tennis entre {$since} et {$today}");

        $scoresByEventId = TennisMatchTightnessScore::whereIn('event_id', $matches->pluck('event_id'))
            ->get()
            ->keyBy('event_id');

        $agg = [];
        $marketKeys = ['15a', '30a', '40a', '30love', 'g40_0', 'g40_15', 'g40_30'];
        foreach ($marketKeys as $k) {
            $agg[$k] = ['games' => 0, 'hits' => 0, 'sum_predicted' => 0.0, 'n_matches' => 0,
                'bets' => 0, 'bets_won' => 0];
        }
        $agg['leads'] = ['games' => 0, 'hits' => 0, 'sum_predicted' => 0.0, 'n_matches' => 0,
            'bets' => 0, 'bets_won' => 0];
        $agg['lost_serve'] = ['service_games' => 0, 'hits' => 0, 'sum_predicted' => 0.0, 'n_matches' => 0,
            'bets' => 0, 'bets_won' => 0];

        $found = 0;
        $skippedNoScore = 0;
        $skippedNoPbp = 0;

        foreach ($matches as $match) {
            $score = $scoresByEventId->get($match->event_id);
            if (!$score) {
                $skippedNoScore++;
                continue;
            }

            $pbpFile = $this->locatePbpFile($match->event_id);
            if (!$pbpFile) {
                $skippedNoPbp++;
                continue;
            }

            $data = json_decode(file_get_contents($pbpFile), true);
            $homeId = $data['_home_team_id'] ?? null;
            $awayId = $data['_away_team_id'] ?? null;
            $set1 = collect($data['pointByPoint'] ?? [])->firstWhere('set', 1);
            if (!$set1 || empty($set1['games']) || (!$homeId && !$awayId)) {
                $skippedNoPbp++;
                continue;
            }

            $found++;
            $this->backtestOneMatch($match, $score, $set1['games'], $homeId, $awayId, $agg);
        }

        $this->newLine();
        $this->info("📊 Matchs analysés: {$found} | sans score enregistré: {$skippedNoScore} | pbp introuvable en cache: {$skippedNoPbp}");
        $this->newLine();

        $this->table(
            ['Marché', 'Cote réf.', 'Proba moy. prédite', 'Taux réel observé', 'Écart calibration', 'Value bets placés', 'Value bets gagnés', 'Taux de réussite'],
            collect($marketKeys)
                ->merge(['leads', 'lost_serve'])
                ->map(function ($k) use ($agg) {
                    $a = $agg[$k];
                    $trials = $a['games'] ?? ($a['service_games'] ?? 0);
                    $realRate = $trials > 0 ? round(100 * $a['hits'] / $trials, 2) : null;
                    $predAvg = $a['n_matches'] > 0 ? round($a['sum_predicted'] / $a['n_matches'], 2) : null;
                    $gap = ($realRate !== null && $predAvg !== null) ? round($realRate - $predAvg, 2) : null;
                    $winRate = $a['bets'] > 0 ? round(100 * $a['bets_won'] / $a['bets'], 2) : null;
                    return [
                        $k,
                        self::MARKET_ODDS[$k],
                        $predAvg !== null ? "{$predAvg}%" : '-',
                        $realRate !== null ? "{$realRate}%" : '-',
                        $gap !== null ? (($gap > 0 ? '+' : '') . "{$gap}pts") : '-',
                        $a['bets'],
                        $a['bets_won'],
                        $winRate !== null ? "{$winRate}%" : '-',
                    ];
                })
                ->toArray()
        );

        $this->newLine();
        $this->comment("Lecture : \"Taux de réussite\" = % des value bets (edge > " . self::MIN_EDGE . " pts, seuil aligné sur le frontend) qui auraient été gagnants. Pour être rentable au fil du temps sur une cote donnée, ce taux doit dépasser le seuil de rentabilité implicite (100/cote) — sinon la cote de référence est probablement mal calibrée pour ce marché.");

        return self::SUCCESS;
    }

    private function backtestOneMatch(MatchModel $match, TennisMatchTightnessScore $score, array $games, ?int $homeId, ?int $awayId, array &$agg): void
    {
        $team1IsHome = (string) $match->team_1_sofascore_id === (string) $homeId;

        $gamesReached = ['15a' => 0, '30a' => 0, '40a' => 0, '30love' => 0, 'g40_0' => 0, 'g40_15' => 0, 'g40_30' => 0];
        $totalGames = 0;
        $leaderHitsTeam1 = 0;
        $leaderHitsTeam2 = 0;
        $serviceGamesTeam1 = 0;
        $serviceGamesTeam2 = 0;
        $lostFirstPointTeam1 = 0;
        $lostFirstPointTeam2 = 0;

        foreach ($games as $game) {
            $points = $game['points'] ?? [];
            if (empty($points)) {
                continue;
            }
            $totalGames++;

            $reached15 = $reached30 = $reached40 = $reached30Love = false;
            foreach ($points as $p) {
                $h = $p['homePoint'] ?? null;
                $a = $p['awayPoint'] ?? null;
                if ($h === '15' && $a === '15') { $reached15 = true; }
                elseif ($h === '30' && $a === '30') { $reached30 = true; }
                elseif ($h === '40' && $a === '40') { $reached40 = true; }
                elseif (($h === '30' && $a === '0') || ($h === '0' && $a === '30')) { $reached30Love = true; }
            }
            $gamesReached['15a'] += $reached15 ? 1 : 0;
            $gamesReached['30a'] += $reached30 ? 1 : 0;
            $gamesReached['40a'] += $reached40 ? 1 : 0;
            $gamesReached['30love'] += $reached30Love ? 1 : 0;

            $first = $points[0];
            $firstH = $first['homePoint'] ?? null;
            $firstA = $first['awayPoint'] ?? null;
            $leaderIsHome = null;
            if ($firstH === '15' && $firstA === '0') { $leaderIsHome = true; }
            elseif ($firstA === '15' && $firstH === '0') { $leaderIsHome = false; }
            if ($leaderIsHome === true) { $team1IsHome ? $leaderHitsTeam1++ : $leaderHitsTeam2++; }
            elseif ($leaderIsHome === false) { $team1IsHome ? $leaderHitsTeam2++ : $leaderHitsTeam1++; }

            $last = end($points);
            $lastH = $last['homePoint'] ?? null;
            $lastA = $last['awayPoint'] ?? null;
            $bucket = null;
            $loserIsHome = null;
            if ($lastH === '40' && $lastA === '0') { $bucket = 'g40_0'; $loserIsHome = false; }
            elseif ($lastA === '40' && $lastH === '0') { $bucket = 'g40_0'; $loserIsHome = true; }
            elseif ($lastH === '40' && $lastA === '15') { $bucket = 'g40_15'; $loserIsHome = false; }
            elseif ($lastA === '40' && $lastH === '15') { $bucket = 'g40_15'; $loserIsHome = true; }
            elseif ($lastH === '40' && $lastA === '30') { $bucket = 'g40_30'; $loserIsHome = false; }
            elseif ($lastA === '40' && $lastH === '30') { $bucket = 'g40_30'; $loserIsHome = true; }
            if ($bucket) { $gamesReached[$bucket]++; }

            $serving = $game['score']['serving'] ?? null;
            if ($serving === 1 || $serving === 2) {
                $serverIsHome = $serving === 1;
                $team1IsHome ? ($serverIsHome ? $serviceGamesTeam1++ : $serviceGamesTeam2++)
                             : ($serverIsHome ? $serviceGamesTeam2++ : $serviceGamesTeam1++);
                if ($leaderIsHome !== null && $leaderIsHome !== $serverIsHome) {
                    $team1IsHome ? ($serverIsHome ? $lostFirstPointTeam1++ : $lostFirstPointTeam2++)
                                 : ($serverIsHome ? $lostFirstPointTeam2++ : $lostFirstPointTeam1++);
                }
            }
        }

        if ($totalGames === 0) {
            return;
        }

        // Marchés symétriques par jeu (15A/30A/40A/30-0/patterns de fin de jeu).
        $predicted = [
            '15a' => $score->prob_15a_set1, '30a' => $score->prob_30a_set1, '40a' => $score->prob_40a_set1,
            '30love' => $score->prob_30love_set1, 'g40_0' => $score->prob_game_40_0,
            'g40_15' => $score->prob_game_40_15, 'g40_30' => $score->prob_game_40_30,
        ];
        $edges = [
            '15a' => $score->edge_15a, '30a' => $score->edge_30a, '40a' => $score->edge_40a,
            '30love' => $score->edge_30love, 'g40_0' => $score->edge_g40_0,
            'g40_15' => $score->edge_g40_15, 'g40_30' => $score->edge_g40_30,
        ];
        foreach (['15a', '30a', '40a', '30love', 'g40_0', 'g40_15', 'g40_30'] as $k) {
            if ($predicted[$k] === null) {
                continue;
            }
            $agg[$k]['games'] += $totalGames;
            $agg[$k]['hits'] += $gamesReached[$k];
            $agg[$k]['sum_predicted'] += $predicted[$k];
            $agg[$k]['n_matches']++;
            if ($edges[$k] !== null && $edges[$k] > self::MIN_EDGE) {
                // "Gagné" = le taux RÉEL de jeux touchés dans CE match dépasse le
                // seuil de rentabilité de la cote — pas "au moins une fois dans le
                // match" (ce critère saturerait vers ~100% avec plusieurs jeux par
                // match, exactement le biais déjà corrigé ailleurs pour l'affichage).
                $agg[$k]['bets']++;
                $matchRate = $gamesReached[$k] / $totalGames;
                if ($matchRate > (1 / self::MARKET_ODDS[$k])) {
                    $agg[$k]['bets_won']++;
                }
            }
        }

        // Marchés asymétriques par joueur (mène 15-0, perd 1er point service).
        foreach ([1, 2] as $team) {
            $predLeads = $team === 1 ? $score->prob_team1_leads_15_0 : $score->prob_team2_leads_15_0;
            $edgeLeads = $team === 1 ? $score->edge_leads1 : $score->edge_leads2;
            $leaderHits = $team === 1 ? $leaderHitsTeam1 : $leaderHitsTeam2;
            if ($predLeads !== null) {
                $agg['leads']['games'] += $totalGames;
                $agg['leads']['hits'] += $leaderHits;
                $agg['leads']['sum_predicted'] += $predLeads;
                $agg['leads']['n_matches']++;
                if ($edgeLeads !== null && $edgeLeads > self::MIN_EDGE) {
                    $agg['leads']['bets']++;
                    if (($leaderHits / $totalGames) > (1 / self::MARKET_ODDS['leads'])) { $agg['leads']['bets_won']++; }
                }
            }

            $predLost = $team === 1 ? $score->prob_lost_serve1 : $score->prob_lost_serve2;
            $edgeLost = $team === 1 ? $score->edge_lost_serve1 : $score->edge_lost_serve2;
            $serviceGames = $team === 1 ? $serviceGamesTeam1 : $serviceGamesTeam2;
            $lostFirst = $team === 1 ? $lostFirstPointTeam1 : $lostFirstPointTeam2;
            if ($predLost !== null && $serviceGames > 0) {
                $agg['lost_serve']['service_games'] += $serviceGames;
                $agg['lost_serve']['hits'] += $lostFirst;
                $agg['lost_serve']['sum_predicted'] += $predLost;
                $agg['lost_serve']['n_matches']++;
                if ($edgeLost !== null && $edgeLost > self::MIN_EDGE) {
                    $agg['lost_serve']['bets']++;
                    if (($lostFirst / $serviceGames) > (1 / self::MARKET_ODDS['lost_serve'])) { $agg['lost_serve']['bets_won']++; }
                }
            }
        }
    }

    /**
     * Cherche pbp_{eventId}.json dans le cache actif, le dossier "processed"
     * (idempotence prod), et les snapshots archivés (après sync) — les 3
     * emplacements possibles selon le moment où le fichier a été traité.
     */
    private function locatePbpFile(string $eventId): ?string
    {
        $base = storage_path('app/sofascore_cache');
        $candidates = [
            "{$base}/tennis_players/players/point_by_point/pbp_{$eventId}.json",
            "{$base}/tennis_players/players/point_by_point/processed/pbp_{$eventId}.json",
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        $archiveMatches = glob("{$base}/archives/tennis_players/*/players/point_by_point/pbp_{$eventId}.json");
        if (!empty($archiveMatches)) {
            return $archiveMatches[0];
        }
        $archiveMatches = glob("{$base}/archives/tennis_players/*/players/point_by_point/processed/pbp_{$eventId}.json");
        if (!empty($archiveMatches)) {
            return $archiveMatches[0];
        }

        return null;
    }
}
