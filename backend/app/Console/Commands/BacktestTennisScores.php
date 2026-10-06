<?php

namespace App\Console\Commands;

use App\Models\MatchModel;
use App\Models\Team;
use App\Models\TennisMatchTightnessScore;
use App\Models\TennisPlayerGameTensionStat;
use App\Models\TennisPlayerGameTensionStatByTier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BacktestTennisScores extends Command
{
    protected $signature = 'tennis:backtest-scores
                            {--days=30 : Nombre de jours en arrière à analyser}
                            {--limit=1000 : Nombre max de matchs à traiter}
                            {--leave-one-out : Recalcule la prédiction en excluant la contribution de CE match de ses propres stats agrégées, pour un vrai test hors échantillon (sinon le score stocké peut avoir "vu" son propre résultat s\'il a été recalculé après ingestion de ce match)}';

    protected $description = "Compare les probabilités/edges calculés avant chaque match (tennis_match_tightness_scores) à ce qui s'est réellement passé dans le point-by-point du 1er set, pour vérifier la calibration du modèle et des cotes de référence.";

    /** Doit rester identique à ComputeTennisTightnessScores::TIER_BLEND_K. */
    private const TIER_BLEND_K = 5;

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

    /** Doit rester identique à ImportTennisPlayersFromCache::MATCH_FILTER_MAX_GAMES. */
    private const MARTINGALE_WINDOW_GAMES = 10;

    /** Doit rester identique au seuil vert du frontend (IN_SET_GREEN_THRESHOLD, TodayMatches.vue). */
    private const IN_SET_GREEN_THRESHOLD = 85;

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $looMode = (bool) $this->option('leave-one-out');

        $since = now()->subDays($days)->format('Y-m-d');
        $today = now()->format('Y-m-d');

        $matches = MatchModel::where('sport_id', 2)
            ->whereBetween('match_start_date', [$since, $today])
            ->whereNotNull('event_id')
            ->orderByDesc('match_start_date')
            ->limit($limit)
            ->get();

        $this->info("🎾 {$matches->count()} match(s) tennis entre {$since} et {$today}" . ($looMode ? " (mode leave-one-out)" : ""));

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

        // Indicateur "au moins 1x dans les N premiers jeux" (badge vert du
        // frontend) — unité = MATCH (pas jeu) : hits/n_matches est le vrai
        // taux de réussite du badge, sum_predicted/n_matches sa moyenne prédite.
        $aggInSet = [];
        foreach (['15a', '30a', '40a', '30love', 'g40_0', 'g40_15', 'g40_30', 'leads', 'lost_serve'] as $k) {
            $aggInSet[$k] = ['n_matches' => 0, 'hits' => 0, 'sum_predicted' => 0.0,
                'green_n' => 0, 'green_hits' => 0];
        }

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
            $this->backtestOneMatch($match, $score, $set1['games'], $homeId, $awayId, $agg, $looMode, $aggInSet);
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

        $this->newLine();
        $this->info("=== Indicateur \"au moins 1x dans les " . self::MARTINGALE_WINDOW_GAMES . " premiers jeux\" (badge vert, seuil " . self::IN_SET_GREEN_THRESHOLD . "%) ===");
        $this->table(
            ['Marché', 'Matchs', 'Proba moy. prédite', 'Taux réel (match)', 'Écart', 'Matchs "verts"', 'Taux réel si vert'],
            collect(['15a', '30a', '40a', '30love', 'g40_0', 'g40_15', 'g40_30', 'leads', 'lost_serve'])
                ->map(function ($k) use ($aggInSet) {
                    $a = $aggInSet[$k];
                    $realRate = $a['n_matches'] > 0 ? round(100 * $a['hits'] / $a['n_matches'], 2) : null;
                    $predAvg = $a['n_matches'] > 0 ? round($a['sum_predicted'] / $a['n_matches'], 2) : null;
                    $gap = ($realRate !== null && $predAvg !== null) ? round($realRate - $predAvg, 2) : null;
                    $greenRealRate = $a['green_n'] > 0 ? round(100 * $a['green_hits'] / $a['green_n'], 2) : null;
                    return [
                        $k,
                        $a['n_matches'],
                        $predAvg !== null ? "{$predAvg}%" : '-',
                        $realRate !== null ? "{$realRate}%" : '-',
                        $gap !== null ? (($gap > 0 ? '+' : '') . "{$gap}pts") : '-',
                        $a['green_n'],
                        $greenRealRate !== null ? "{$greenRealRate}%" : '-',
                    ];
                })
                ->toArray()
        );
        $this->comment("Lecture : \"Taux réel si vert\" = parmi les matchs où le badge était vert (>= " . self::IN_SET_GREEN_THRESHOLD . "%), quelle proportion a réellement atteint l'événement dans les " . self::MARTINGALE_WINDOW_GAMES . " premiers jeux — c'est la vraie fiabilité du filtre \"on évite les matchs pas à 100%\".");

        return self::SUCCESS;
    }

    private function backtestOneMatch(MatchModel $match, TennisMatchTightnessScore $score, array $games, ?int $homeId, ?int $awayId, array &$agg, bool $looMode = false, array &$aggInSet = []): void
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

        // "Au moins 1x dans les N premiers jeux" — mêmes clés que $gamesReached
        // mais bornées à self::MARTINGALE_WINDOW_GAMES (voir badge frontend).
        $inSetReached = ['15a' => false, '30a' => false, '40a' => false, '30love' => false, 'g40_0' => false, 'g40_15' => false, 'g40_30' => false];
        $inSetLeadsTeam1 = false;
        $inSetLeadsTeam2 = false;
        $inSetLostServeTeam1 = false;
        $inSetLostServeTeam2 = false;

        foreach ($games as $game) {
            $points = $game['points'] ?? [];
            if (empty($points)) {
                continue;
            }
            $totalGames++;
            $withinWindow = $totalGames <= self::MARTINGALE_WINDOW_GAMES;

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
            if ($withinWindow) {
                if ($reached15) { $inSetReached['15a'] = true; }
                if ($reached30) { $inSetReached['30a'] = true; }
                if ($reached40) { $inSetReached['40a'] = true; }
                if ($reached30Love) { $inSetReached['30love'] = true; }
            }

            $first = $points[0];
            $firstH = $first['homePoint'] ?? null;
            $firstA = $first['awayPoint'] ?? null;
            $leaderIsHome = null;
            if ($firstH === '15' && $firstA === '0') { $leaderIsHome = true; }
            elseif ($firstA === '15' && $firstH === '0') { $leaderIsHome = false; }
            if ($leaderIsHome === true) { $team1IsHome ? $leaderHitsTeam1++ : $leaderHitsTeam2++; }
            elseif ($leaderIsHome === false) { $team1IsHome ? $leaderHitsTeam2++ : $leaderHitsTeam1++; }
            if ($withinWindow && $leaderIsHome !== null) {
                $isTeam1 = $leaderIsHome === $team1IsHome;
                if ($isTeam1) { $inSetLeadsTeam1 = true; } else { $inSetLeadsTeam2 = true; }
            }

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
            if ($bucket && $withinWindow) { $inSetReached[$bucket] = true; }

            $serving = $game['score']['serving'] ?? null;
            if ($serving === 1 || $serving === 2) {
                $serverIsHome = $serving === 1;
                $team1IsHome ? ($serverIsHome ? $serviceGamesTeam1++ : $serviceGamesTeam2++)
                             : ($serverIsHome ? $serviceGamesTeam2++ : $serviceGamesTeam1++);
                if ($leaderIsHome !== null && $leaderIsHome !== $serverIsHome) {
                    $team1IsHome ? ($serverIsHome ? $lostFirstPointTeam1++ : $lostFirstPointTeam2++)
                                 : ($serverIsHome ? $lostFirstPointTeam2++ : $lostFirstPointTeam1++);
                    if ($withinWindow) {
                        $serverIsTeam1 = $serverIsHome === $team1IsHome;
                        if ($serverIsTeam1) { $inSetLostServeTeam1 = true; } else { $inSetLostServeTeam2 = true; }
                    }
                }
            }
        }

        if ($totalGames === 0) {
            return;
        }

        if ($looMode) {
            $score = $this->computeLeaveOneOutScore(
                $match, $homeId, $awayId, $totalGames, $gamesReached,
                $leaderHitsTeam1, $leaderHitsTeam2,
                $serviceGamesTeam1, $serviceGamesTeam2,
                $lostFirstPointTeam1, $lostFirstPointTeam2,
                $team1IsHome,
                $inSetReached, $inSetLeadsTeam1, $inSetLeadsTeam2,
                $inSetLostServeTeam1, $inSetLostServeTeam2
            );
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

        // Indicateur "au moins 1x dans les 10 premiers jeux" (badge vert du
        // frontend, seuil 85%) — en mode --leave-one-out, $score a été
        // remplacé par computeLeaveOneOutScore() qui calcule aussi ces champs
        // hors échantillon (contribution de CE match exclue des compteurs).
        $inSetPredicted = [
            '15a' => $score->prob_15a_in_set ?? null, '30a' => $score->prob_30a_in_set ?? null,
            '40a' => $score->prob_40a_in_set ?? null, '30love' => $score->prob_30love_in_set ?? null,
            'g40_0' => $score->prob_game_40_0_in_set ?? null, 'g40_15' => $score->prob_game_40_15_in_set ?? null,
            'g40_30' => $score->prob_game_40_30_in_set ?? null,
        ];
        foreach (['15a', '30a', '40a', '30love', 'g40_0', 'g40_15', 'g40_30'] as $k) {
            if ($inSetPredicted[$k] === null) {
                continue;
            }
            $aggInSet[$k]['n_matches']++;
            $aggInSet[$k]['hits'] += $inSetReached[$k] ? 1 : 0;
            $aggInSet[$k]['sum_predicted'] += $inSetPredicted[$k];
            if ($inSetPredicted[$k] >= self::IN_SET_GREEN_THRESHOLD) {
                $aggInSet[$k]['green_n']++;
                $aggInSet[$k]['green_hits'] += $inSetReached[$k] ? 1 : 0;
            }
        }

        foreach ([1, 2] as $team) {
            $predLeadsInSet = $team === 1 ? ($score->prob_team1_leads_15_0_in_set ?? null) : ($score->prob_team2_leads_15_0_in_set ?? null);
            $leadsHit = $team === 1 ? $inSetLeadsTeam1 : $inSetLeadsTeam2;
            if ($predLeadsInSet !== null) {
                $aggInSet['leads']['n_matches']++;
                $aggInSet['leads']['hits'] += $leadsHit ? 1 : 0;
                $aggInSet['leads']['sum_predicted'] += $predLeadsInSet;
                if ($predLeadsInSet >= self::IN_SET_GREEN_THRESHOLD) {
                    $aggInSet['leads']['green_n']++;
                    $aggInSet['leads']['green_hits'] += $leadsHit ? 1 : 0;
                }
            }

            $predLostInSet = $team === 1 ? ($score->prob_lost_serve1_in_set ?? null) : ($score->prob_lost_serve2_in_set ?? null);
            $lostHit = $team === 1 ? $inSetLostServeTeam1 : $inSetLostServeTeam2;
            if ($predLostInSet !== null) {
                $aggInSet['lost_serve']['n_matches']++;
                $aggInSet['lost_serve']['hits'] += $lostHit ? 1 : 0;
                $aggInSet['lost_serve']['sum_predicted'] += $predLostInSet;
                if ($predLostInSet >= self::IN_SET_GREEN_THRESHOLD) {
                    $aggInSet['lost_serve']['green_n']++;
                    $aggInSet['lost_serve']['green_hits'] += $lostHit ? 1 : 0;
                }
            }
        }
    }

    /**
     * Recalcule la prédiction (probas + edges) pour CE match en excluant sa
     * propre contribution des stats agrégées (globales ET par tier) avant de
     * blender — sinon, comme ces stats sont cumulatives et incluent déjà ce
     * match dès qu'il a été importé, le score stocké peut avoir "vu" son
     * propre résultat (fuite de données), gonflant artificiellement le taux
     * de réussite apparent du backtest.
     *
     * Approximation : le tier de l'adversaire est recalculé avec le
     * classement ACTUEL (pas celui au moment du match) — acceptable tant que
     * les classements ATP/WTA ne bougent pas radicalement d'un jour à l'autre.
     */
    private function computeLeaveOneOutScore(
        MatchModel $match,
        ?int $homeId,
        ?int $awayId,
        int $totalGames,
        array $gamesReached,
        int $leaderHitsTeam1,
        int $leaderHitsTeam2,
        int $serviceGamesTeam1,
        int $serviceGamesTeam2,
        int $lostFirstPointTeam1,
        int $lostFirstPointTeam2,
        bool $team1IsHome,
        array $inSetReached = [],
        bool $inSetLeadsTeam1 = false,
        bool $inSetLeadsTeam2 = false,
        bool $inSetLostServeTeam1 = false,
        bool $inSetLostServeTeam2 = false
    ): \stdClass {
        $teamA = Team::where('sofascore_id', $match->team_1_sofascore_id)->first();
        $teamB = Team::where('sofascore_id', $match->team_2_sofascore_id)->first();

        $result = new \stdClass();
        $marketCols = [
            '15a' => 'count_reach_15a', '30a' => 'count_reach_30a', '40a' => 'count_reach_40a',
            '30love' => 'count_reach_30love', 'g40_0' => 'count_game_40_0',
            'g40_15' => 'count_game_40_15', 'g40_30' => 'count_game_40_30',
        ];
        $resultKeys = [
            '15a' => 'prob_15a_set1', '30a' => 'prob_30a_set1', '40a' => 'prob_40a_set1',
            '30love' => 'prob_30love_set1', 'g40_0' => 'prob_game_40_0',
            'g40_15' => 'prob_game_40_15', 'g40_30' => 'prob_game_40_30',
        ];
        $edgeKeys = [
            '15a' => 'edge_15a', '30a' => 'edge_30a', '40a' => 'edge_40a',
            '30love' => 'edge_30love', 'g40_0' => 'edge_g40_0',
            'g40_15' => 'edge_g40_15', 'g40_30' => 'edge_g40_30',
        ];

        $inSetResultKeys = [
            '15a' => 'prob_15a_in_set', '30a' => 'prob_30a_in_set', '40a' => 'prob_40a_in_set',
            '30love' => 'prob_30love_in_set', 'g40_0' => 'prob_game_40_0_in_set',
            'g40_15' => 'prob_game_40_15_in_set', 'g40_30' => 'prob_game_40_30_in_set',
        ];

        if (!$teamA || !$teamB) {
            foreach ($resultKeys as $k => $prop) { $result->$prop = null; $result->{$edgeKeys[$k]} = null; }
            foreach ($inSetResultKeys as $prop) { $result->$prop = null; }
            $result->prob_team1_leads_15_0 = $result->prob_team2_leads_15_0 = null;
            $result->edge_leads1 = $result->edge_leads2 = null;
            $result->prob_lost_serve1 = $result->prob_lost_serve2 = null;
            $result->edge_lost_serve1 = $result->edge_lost_serve2 = null;
            $result->prob_team1_leads_15_0_in_set = $result->prob_team2_leads_15_0_in_set = null;
            $result->prob_lost_serve1_in_set = $result->prob_lost_serve2_in_set = null;
            return $result;
        }

        $tensionA = TennisPlayerGameTensionStat::where('team_id', $teamA->id)->first();
        $tensionB = TennisPlayerGameTensionStat::where('team_id', $teamB->id)->first();
        $tierOfB = TennisPlayerGameTensionStatByTier::tierForRanking($teamB->ranking);
        $tierOfA = TennisPlayerGameTensionStatByTier::tierForRanking($teamA->ranking);
        $tierStatA = TennisPlayerGameTensionStatByTier::where('team_id', $teamA->id)->where('opponent_tier', $tierOfB)->first();
        $tierStatB = TennisPlayerGameTensionStatByTier::where('team_id', $teamB->id)->where('opponent_tier', $tierOfA)->first();

        foreach ($marketCols as $k => $countCol) {
            $rateA = $this->looRate($tensionA, $tierStatA, $countCol, 'sample_games_set1', $totalGames, $gamesReached[$k]);
            $rateB = $this->looRate($tensionB, $tierStatB, $countCol, 'sample_games_set1', $totalGames, $gamesReached[$k]);
            $predicted = ($rateA === null || $rateB === null) ? null : round((($rateA + $rateB) / 2) * 100, 2);
            $result->{$resultKeys[$k]} = $predicted;
            $result->{$edgeKeys[$k]} = $predicted === null ? null : round($predicted - (100 / self::MARKET_ODDS[$k]), 2);
        }

        // Mène 15-0 (asymétrique par joueur, dénominateur = jeux totaux).
        $rateLeads1 = $this->looRate($tensionA, $tierStatA, 'count_led_15_0', 'sample_games_set1', $totalGames, $leaderHitsTeam1);
        $rateLeads2 = $this->looRate($tensionB, $tierStatB, 'count_led_15_0', 'sample_games_set1', $totalGames, $leaderHitsTeam2);
        $result->prob_team1_leads_15_0 = $rateLeads1 === null ? null : round($rateLeads1 * 100, 2);
        $result->prob_team2_leads_15_0 = $rateLeads2 === null ? null : round($rateLeads2 * 100, 2);
        $result->edge_leads1 = $result->prob_team1_leads_15_0 === null ? null : round($result->prob_team1_leads_15_0 - (100 / self::MARKET_ODDS['leads']), 2);
        $result->edge_leads2 = $result->prob_team2_leads_15_0 === null ? null : round($result->prob_team2_leads_15_0 - (100 / self::MARKET_ODDS['leads']), 2);

        // Perd le 1er point sur son service (dénominateur = jeux de service de CE joueur).
        $rateLost1 = $this->looRate($tensionA, $tierStatA, 'count_lost_first_point_on_serve', 'sample_service_games_set1', $serviceGamesTeam1, $lostFirstPointTeam1);
        $rateLost2 = $this->looRate($tensionB, $tierStatB, 'count_lost_first_point_on_serve', 'sample_service_games_set1', $serviceGamesTeam2, $lostFirstPointTeam2);
        $result->prob_lost_serve1 = $rateLost1 === null ? null : round($rateLost1 * 100, 2);
        $result->prob_lost_serve2 = $rateLost2 === null ? null : round($rateLost2 * 100, 2);
        $result->edge_lost_serve1 = $result->prob_lost_serve1 === null ? null : round($result->prob_lost_serve1 - (100 / self::MARKET_ODDS['lost_serve']), 2);
        $result->edge_lost_serve2 = $result->prob_lost_serve2 === null ? null : round($result->prob_lost_serve2 - (100 / self::MARKET_ODDS['lost_serve']), 2);

        // Indicateur "au moins 1x dans les N premiers jeux" — même principe
        // LOO, mais dénominateur = sample_matches (ce match compte pour 1,
        // pas pour le nombre de jeux) et compteur = count_matches_reach_X.
        $matchInSetCols = [
            '15a' => 'count_matches_reach_15a', '30a' => 'count_matches_reach_30a',
            '40a' => 'count_matches_reach_40a', '30love' => 'count_matches_reach_30love',
            'g40_0' => 'count_matches_reach_g40_0', 'g40_15' => 'count_matches_reach_g40_15',
            'g40_30' => 'count_matches_reach_g40_30',
        ];
        foreach ($matchInSetCols as $k => $countCol) {
            $ownContribution = ($inSetReached[$k] ?? false) ? 1 : 0;
            $rateA = $this->looRate($tensionA, $tierStatA, $countCol, 'sample_matches', 1, $ownContribution);
            $rateB = $this->looRate($tensionB, $tierStatB, $countCol, 'sample_matches', 1, $ownContribution);
            $result->{$inSetResultKeys[$k]} = ($rateA === null || $rateB === null) ? null : round((($rateA + $rateB) / 2) * 100, 2);
        }

        $rateLeads1InSet = $this->looRate($tensionA, $tierStatA, 'count_matches_led_15_0', 'sample_matches', 1, $inSetLeadsTeam1 ? 1 : 0);
        $rateLeads2InSet = $this->looRate($tensionB, $tierStatB, 'count_matches_led_15_0', 'sample_matches', 1, $inSetLeadsTeam2 ? 1 : 0);
        $result->prob_team1_leads_15_0_in_set = $rateLeads1InSet === null ? null : round($rateLeads1InSet * 100, 2);
        $result->prob_team2_leads_15_0_in_set = $rateLeads2InSet === null ? null : round($rateLeads2InSet * 100, 2);

        $rateLost1InSet = $this->looRate($tensionA, $tierStatA, 'count_matches_lost_first_point_on_serve', 'sample_matches', 1, $inSetLostServeTeam1 ? 1 : 0);
        $rateLost2InSet = $this->looRate($tensionB, $tierStatB, 'count_matches_lost_first_point_on_serve', 'sample_matches', 1, $inSetLostServeTeam2 ? 1 : 0);
        $result->prob_lost_serve1_in_set = $rateLost1InSet === null ? null : round($rateLost1InSet * 100, 2);
        $result->prob_lost_serve2_in_set = $rateLost2InSet === null ? null : round($rateLost2InSet * 100, 2);

        return $result;
    }

    /**
     * Taux blendé (shrinkage) d'un joueur pour un marché donné, en excluant
     * la contribution de CE match précis des compteurs globaux ET par tier
     * avant de calculer le taux — voir computeLeaveOneOutScore().
     */
    private function looRate(
        ?TennisPlayerGameTensionStat $global,
        ?TennisPlayerGameTensionStatByTier $tier,
        string $countCol,
        string $sampleCol,
        int $subtractSample,
        int $subtractCount
    ): ?float {
        if (!$global) {
            return null;
        }
        $adjSample = max(0, $global->$sampleCol - $subtractSample);
        $adjCount = max(0, $global->$countCol - $subtractCount);
        if ($adjSample <= 0) {
            return null;
        }
        $globalRate = $adjCount / $adjSample;

        if (!$tier) {
            return $globalRate;
        }
        $tierAdjSample = max(0, $tier->$sampleCol - $subtractSample);
        $tierAdjCount = max(0, $tier->$countCol - $subtractCount);
        if ($tierAdjSample <= 0) {
            return $globalRate;
        }
        $tierRate = $tierAdjCount / $tierAdjSample;
        $k = self::TIER_BLEND_K;
        return (($globalRate * $k) + ($tierRate * $tierAdjSample)) / ($k + $tierAdjSample);
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
