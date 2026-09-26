<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\TennisPlayerSeasonStat;
use App\Models\TennisH2hMatch;
use App\Models\TennisMatchOdds;
use App\Models\TennisMatchTightnessScore;
use App\Models\TennisPlayerGameTensionStat;
use App\Models\Sport;
use Illuminate\Support\Facades\Log;

/**
 * Calcule un score de "match serré" (0-100) pour les matchs de tennis à venir,
 * basé sur : proximité de niveau (ranking/UTR/cotes), qualité de service
 * similaire, conversion des balles de break, activité de breaks/débreaks,
 * historique H2H, fréquence de tie-breaks récents.
 *
 * Ne fait aucun appel réseau : lecture seule des données déjà importées par
 * tennis:import-from-cache (rankings, tennis_player_season_stats, H2H, cotes).
 */
class ComputeTennisTightnessScores extends Command
{
    protected $signature = 'tennis:compute-tightness-scores
                            {--days=1 : Nombre de jours à venir à traiter (à partir d\'aujourd\'hui)}
                            {--force : Recalculer même si un score existe déjà}';

    protected $description = "Calcule un score 'match serré' (0-100) pour les matchs de tennis à venir";

    public function handle()
    {
        $days = (int) $this->option('days');
        $force = (bool) $this->option('force');

        $startDate = now()->toDateString();
        $endDate = now()->addDays(max(0, $days - 1))->toDateString();

        $tennisSportId = Sport::where('slug', 'tennis')->orWhere('name', 'Tennis')->value('id');
        if (!$tennisSportId) {
            $this->error("❌ Sport 'Tennis' introuvable en base");
            return 1;
        }

        $matches = MatchModel::where('sport_id', $tennisSportId)
            ->whereBetween('match_start_date', [$startDate, $endDate])
            ->get();

        $this->info("🎾 {$matches->count()} match(s) tennis entre {$startDate} et {$endDate}");

        $computed = 0;
        $skipped = 0;

        foreach ($matches as $match) {
            if (!$force && TennisMatchTightnessScore::where('event_id', $match->event_id)->exists()) {
                $skipped++;
                continue;
            }

            $result = $this->computeForMatch($match);
            if ($result === null) {
                continue;
            }

            TennisMatchTightnessScore::updateOrCreate(
                ['event_id' => $match->event_id],
                [
                    'score' => $result['score'],
                    'breakdown' => $result['breakdown'],
                    'prob_15a_set1' => $result['prob_15a_set1'],
                    'prob_30a_set1' => $result['prob_30a_set1'],
                    'prob_40a_set1' => $result['prob_40a_set1'],
                    'edge_15a' => $result['edge_15a'],
                    'edge_30a' => $result['edge_30a'],
                    'edge_40a' => $result['edge_40a'],
                    'sample_size' => $result['sample_size'],
                    'prob_30love_set1' => $result['prob_30love_set1'],
                    'prob_game_40_0' => $result['prob_game_40_0'],
                    'prob_game_40_15' => $result['prob_game_40_15'],
                    'prob_game_40_30' => $result['prob_game_40_30'],
                    'prob_server_loss_to_love' => $result['prob_server_loss_to_love'],
                    'prob_team1_leads_15_0' => $result['prob_team1_leads_15_0'],
                    'prob_team2_leads_15_0' => $result['prob_team2_leads_15_0'],
                    'edge_30love' => $result['edge_30love'],
                    'edge_g40_0' => $result['edge_g40_0'],
                    'edge_g40_15' => $result['edge_g40_15'],
                    'edge_g40_30' => $result['edge_g40_30'],
                    'edge_leads1' => $result['edge_leads1'],
                    'edge_leads2' => $result['edge_leads2'],
                    'prob_lost_serve1' => $result['prob_lost_serve1'],
                    'prob_lost_serve2' => $result['prob_lost_serve2'],
                    'edge_lost_serve1' => $result['edge_lost_serve1'],
                    'edge_lost_serve2' => $result['edge_lost_serve2'],
                    'computed_at' => now(),
                ]
            );
            $computed++;
        }

        $this->line("✅ Scores calculés: {$computed} | déjà présents (skip): {$skipped}");
        return 0;
    }

    private function computeForMatch(MatchModel $match): ?array
    {
        $teamA = Team::where('sofascore_id', $match->team_1_sofascore_id)->first();
        $teamB = Team::where('sofascore_id', $match->team_2_sofascore_id)->first();

        if (!$teamA || !$teamB) {
            return null;
        }

        $statA = $this->bestSeasonStat($teamA->id);
        $statB = $this->bestSeasonStat($teamB->id);

        $breakdown = [];
        $total = 0;

        // 1. Cotes proches (20 pts) — fallback sur proximité de ranking si pas de cotes
        [$pts, $detail] = $this->scoreOddsOrRanking($match->event_id, $teamA, $teamB);
        $breakdown['cotes_ou_ranking'] = $detail;
        $total += $pts;

        // 2. Rang UTR proche (15 pts) — classement alternatif, PAS la note UTR
        [$pts, $detail] = $this->scoreUtrProximity($teamA, $teamB);
        $breakdown['utr_proche'] = $detail;
        $total += $pts;

        // 3. % service similaire (15 pts)
        [$pts, $detail] = $this->scoreServiceSimilarity($statA, $statB);
        $breakdown['service_similaire'] = $detail;
        $total += $pts;

        // 4. Beaucoup de breaks/débreaks (15 pts)
        [$pts, $detail] = $this->scoreBreakActivity($statA, $statB);
        $breakdown['breaks_debreaks'] = $detail;
        $total += $pts;

        // 5. Mauvaise conversion des balles de break (10 pts)
        [$pts, $detail] = $this->scorePoorBreakConversion($statA, $statB);
        $breakdown['conversion_bp_faible'] = $detail;
        $total += $pts;

        // 6. Historique H2H serré (10 pts)
        [$pts, $detail] = $this->scoreH2hHistory($match->event_id);
        $breakdown['h2h_serre'] = $detail;
        $total += $pts;

        // 7. Tie-breaks récents fréquents (10 pts)
        [$pts, $detail] = $this->scoreTiebreakFrequency($statA, $statB);
        $breakdown['tiebreaks_frequents'] = $detail;
        $total += $pts;

        // 8. Forme récente similaire — % jeux gagnés proche (5 pts)
        [$pts, $detail] = $this->scoreWinRateSimilarity($statA, $statB);
        $breakdown['forme_similaire'] = $detail;
        $total += $pts;

        $set1Probs = $this->computeSet1ScoreProbabilities($statA, $statB, $teamA, $teamB);
        if ($set1Probs['source'] ?? null) {
            $breakdown['proba_set1_source'] = $set1Probs['source'];
        }

        return [
            'score' => (int) round(min(100, max(0, $total))),
            'breakdown' => $breakdown,
            'prob_15a_set1' => $set1Probs['prob_15a'],
            'prob_30a_set1' => $set1Probs['prob_30a'],
            'prob_40a_set1' => $set1Probs['prob_40a'],
            'edge_15a' => $this->computeEdge($set1Probs['prob_15a'], self::MARKET_ODDS['15a']),
            'edge_30a' => $this->computeEdge($set1Probs['prob_30a'], self::MARKET_ODDS['30a']),
            'edge_40a' => $this->computeEdge($set1Probs['prob_40a'], self::MARKET_ODDS['40a']),
            'sample_size' => $set1Probs['sample_size'] ?? null,
            'prob_30love_set1' => $set1Probs['prob_30love'] ?? null,
            'prob_game_40_0' => $set1Probs['prob_g40_0'] ?? null,
            'prob_game_40_15' => $set1Probs['prob_g40_15'] ?? null,
            'prob_game_40_30' => $set1Probs['prob_g40_30'] ?? null,
            'prob_server_loss_to_love' => $set1Probs['prob_server_loss_to_love'] ?? null,
            'prob_team1_leads_15_0' => $set1Probs['prob_team1_leads_15_0'] ?? null,
            'prob_team2_leads_15_0' => $set1Probs['prob_team2_leads_15_0'] ?? null,
            'edge_30love' => $this->computeEdge($set1Probs['prob_30love'] ?? null, self::MARKET_ODDS['30love']),
            'edge_g40_0' => $this->computeEdge($set1Probs['prob_g40_0'] ?? null, self::MARKET_ODDS['g40_0']),
            'edge_g40_15' => $this->computeEdge($set1Probs['prob_g40_15'] ?? null, self::MARKET_ODDS['g40_15']),
            'edge_g40_30' => $this->computeEdge($set1Probs['prob_g40_30'] ?? null, self::MARKET_ODDS['g40_30']),
            'edge_leads1' => $this->computeEdge($set1Probs['prob_team1_leads_15_0'] ?? null, self::MARKET_ODDS['leads']),
            'edge_leads2' => $this->computeEdge($set1Probs['prob_team2_leads_15_0'] ?? null, self::MARKET_ODDS['leads']),
            'prob_lost_serve1' => $set1Probs['prob_team1_lost_serve'] ?? null,
            'prob_lost_serve2' => $set1Probs['prob_team2_lost_serve'] ?? null,
            'edge_lost_serve1' => $this->computeEdge($set1Probs['prob_team1_lost_serve'] ?? null, self::MARKET_ODDS['lost_serve']),
            'edge_lost_serve2' => $this->computeEdge($set1Probs['prob_team2_lost_serve'] ?? null, self::MARKET_ODDS['lost_serve']),
        ];
    }

    /**
     * Cotes de référence pour les paris "au jeu" 15A/30A/40A (source : cotes
     * habituellement observées par l'utilisateur, pas de fetch dynamique).
     */
    private const MARKET_ODDS = [
        '15a' => 1.85, '30a' => 2.40, '40a' => 3.00,
        '30love' => 2.00, 'g40_0' => 3.00, 'g40_15' => 3.00, 'g40_30' => 3.00,
        'leads' => 1.40, 'lost_serve' => 2.00,
    ];

    /**
     * Edge (points de %) entre notre proba calculée et le seuil de rentabilité
     * (1/cote) du marché. Positif = pari +EV théorique, négatif = -EV.
     */
    private function computeEdge(?float $probPercent, float $odds): ?float
    {
        if ($probPercent === null) {
            return null;
        }
        $breakeven = 100 / $odds;
        return round($probPercent - $breakeven, 2);
    }

    /**
     * Probabilité (%) qu'un jeu DONNÉ du 1er set atteigne 15-15 / 30-30 / 40-40
     * — moyenne des taux des deux joueurs. Priorité à la fréquence EMPIRIQUE
     * (tennis_player_game_tension_stats, calculée depuis le vrai historique
     * point-by-point — voir ImportTennisPlayersFromCache::processPointByPointTension)
     * si l'échantillon est suffisant pour les deux joueurs ; sinon repli sur un
     * modèle théorique (binomial) basé sur leur % de points gagnés au service.
     *
     * Note : on rapporte ici le taux PAR JEU, pas "au moins une fois dans tout
     * le set" — cette dernière formulation sature quasi toujours vers 100% dès
     * qu'on la combine sur les ~10-20 jeux d'un set (même un taux par jeu de
     * 20-30% donne >99% de chances que ça arrive au moins une fois sur autant
     * d'essais), ce qui ne discrimine plus aucun match.
     */
    private function computeSet1ScoreProbabilities(
        ?TennisPlayerSeasonStat $statA,
        ?TennisPlayerSeasonStat $statB,
        Team $teamA,
        Team $teamB
    ): array {
        $tensionA = TennisPlayerGameTensionStat::where('team_id', $teamA->id)->first();
        $tensionB = TennisPlayerGameTensionStat::where('team_id', $teamB->id)->first();

        if ($tensionA && $tensionA->isReliable() && $tensionB && $tensionB->isReliable()) {
            $result = $this->set1ProbabilitiesFromEmpirical($tensionA, $tensionB);
            // Maillon faible : la fiabilité du couple est celle du joueur le
            // moins échantillonné, pas la moyenne.
            $result['sample_size'] = min($tensionA->sample_games_set1, $tensionB->sample_games_set1);
            // Asymétrique par nature (qui mène 15-0, pas une moyenne fusionnée)
            // — pas de repli théorique propre, seulement empirique.
            $rateA = $tensionA->rateLedFirstPoint();
            $rateB = $tensionB->rateLedFirstPoint();
            $result['prob_team1_leads_15_0'] = $rateA !== null ? round($rateA * 100, 2) : null;
            $result['prob_team2_leads_15_0'] = $rateB !== null ? round($rateB * 100, 2) : null;
            // Idem, spécifique au service de CHAQUE joueur (pas de repli théorique).
            $rateLostA = $tensionA->rateLostFirstPointOnServe();
            $rateLostB = $tensionB->rateLostFirstPointOnServe();
            $result['prob_team1_lost_serve'] = $rateLostA !== null ? round($rateLostA * 100, 2) : null;
            $result['prob_team2_lost_serve'] = $rateLostB !== null ? round($rateLostB * 100, 2) : null;
            return $result;
        }

        $result = $this->set1ProbabilitiesFromTheoreticalModel($statA, $statB);
        $result['sample_size'] = ($statA && $statB)
            ? min($statA->matches_count ?? 0, $statB->matches_count ?? 0)
            : null;
        return $result;
    }

    /**
     * Fréquence réelle observée dans l'historique point-by-point des 2 joueurs :
     * moyenne simple de leurs taux par jeu respectifs (pas de compoundage).
     */
    private function set1ProbabilitiesFromEmpirical(TennisPlayerGameTensionStat $a, TennisPlayerGameTensionStat $b): array
    {
        $avg = fn (?float $rateA, ?float $rateB) => ($rateA === null || $rateB === null)
            ? null
            : round((($rateA + $rateB) / 2) * 100, 2);

        return [
            'prob_15a' => $avg($a->rate15a(), $b->rate15a()),
            'prob_30a' => $avg($a->rate30a(), $b->rate30a()),
            'prob_40a' => $avg($a->rate40a(), $b->rate40a()),
            'prob_30love' => $avg($a->rate30Love(), $b->rate30Love()),
            'prob_g40_0' => $avg($a->rateGame40_0(), $b->rateGame40_0()),
            'prob_g40_15' => $avg($a->rateGame40_15(), $b->rateGame40_15()),
            'prob_g40_30' => $avg($a->rateGame40_30(), $b->rateGame40_30()),
            'prob_server_loss_to_love' => $avg($a->rateServerLossToLove(), $b->rateServerLossToLove()),
            'source' => 'empirique',
        ];
    }

    /**
     * Modèle théorique (binomial) — utilisé quand l'historique point-by-point
     * est insuffisant. Pour un jeu de service avec p = probabilité que le
     * serveur gagne un point (sans interruption possible avant 4 points), la
     * probabilité que CE jeu atteigne :
     *   - 15-15 (1-1 après 2 points) : 2p(1-p)
     *   - 30-30 (2-2 après 4 points) : C(4,2) p²(1-p)² = 6 p²(1-p)²
     *   - 40-40 (3-3 après 6 points) : C(6,3) p³(1-p)³ = 20 p³(1-p)³
     * On rapporte la moyenne de ce taux pour les jeux servis par chacun des
     * deux joueurs (pas de compoundage sur plusieurs jeux).
     */
    private function set1ProbabilitiesFromTheoreticalModel(?TennisPlayerSeasonStat $statA, ?TennisPlayerSeasonStat $statB): array
    {
        $pA = $statA ? $statA->servicePointsWonPct() : null;
        $pB = $statB ? $statB->servicePointsWonPct() : null;

        if ($pA === null || $pB === null) {
            return [
                'prob_15a' => null, 'prob_30a' => null, 'prob_40a' => null,
                'prob_30love' => null, 'prob_g40_0' => null, 'prob_g40_15' => null, 'prob_g40_30' => null,
                'prob_server_loss_to_love' => null, 'source' => null,
            ];
        }

        $pA /= 100;
        $pB /= 100;

        $perGame15 = fn ($p) => 2 * $p * (1 - $p);
        $perGame30 = fn ($p) => 6 * ($p ** 2) * ((1 - $p) ** 2);
        $perGame40 = fn ($p) => 20 * ($p ** 3) * ((1 - $p) ** 3);
        // 30-0 ou 0-30 (score NON partagé après 2 points) : complémentaire de 15-15.
        $perGame30Love = fn ($p) => ($p ** 2) + ((1 - $p) ** 2);
        // Jeu gagné 4-0 / 4-1 / 4-2 (l'un ou l'autre camp), sans deuce — nombre
        // de séquences à k pertes parmi les 3+k premiers points, point gagnant
        // en dernier : C(3+k,k) * p^4 * (1-p)^k, sommé sur les deux sens.
        $perGame40_0 = fn ($p) => ($p ** 4) + ((1 - $p) ** 4);
        $perGame40_15 = fn ($p) => 4 * ($p ** 4) * (1 - $p) + 4 * ((1 - $p) ** 4) * $p;
        $perGame40_30 = fn ($p) => 10 * ($p ** 4) * ((1 - $p) ** 2) + 10 * ((1 - $p) ** 4) * ($p ** 2);

        $avg = fn (callable $perGameProb) => round((($perGameProb($pA) + $perGameProb($pB)) / 2) * 100, 2);
        // Spécifique au serveur : proba qu'IL perde son propre jeu 0-40 (le
        // relanceur gagne les 4 points), pas de compoundage des deux sens.
        $avgServerLoss = round(((((1 - $pA) ** 4)) + (((1 - $pB) ** 4))) / 2 * 100, 2);

        return [
            'prob_15a' => $avg($perGame15),
            'prob_30a' => $avg($perGame30),
            'prob_40a' => $avg($perGame40),
            'prob_30love' => $avg($perGame30Love),
            'prob_g40_0' => $avg($perGame40_0),
            'prob_g40_15' => $avg($perGame40_15),
            'prob_g40_30' => $avg($perGame40_30),
            'prob_server_loss_to_love' => $avgServerLoss,
            'source' => 'théorique',
        ];
    }

    /**
     * Stats de la surface la plus fournie (le plus de matchs) pour l'année en cours.
     * À défaut de correspondance exacte de surface avec le tournoi du jour, c'est
     * une approximation raisonnable de la forme générale du joueur.
     */
    /**
     * Ligne (surface + année) avec le plus de matchs échantillonnés, toutes
     * années confondues (année en cours + année précédente désormais fetchées)
     * — pas seulement l'année en cours, pour maximiser la fiabilité du taux.
     */
    private function bestSeasonStat(int $teamId): ?TennisPlayerSeasonStat
    {
        return TennisPlayerSeasonStat::where('team_id', $teamId)
            ->orderByDesc('matches_count')
            ->first();
    }

    private function scoreOddsOrRanking(?int $eventId, Team $a, Team $b): array
    {
        $odds = TennisMatchOdds::where('event_id', $eventId)
            ->where('market_name', 'Full time')
            ->orderBy('choice_name')
            ->get();

        if ($odds->count() >= 2) {
            $values = $odds->pluck('decimal_value')->filter()->values();
            if ($values->count() >= 2) {
                $diff = abs($values[0] - $values[1]);
                // diff 0 -> 20 pts, diff >= 3 -> 0 pt
                $pts = max(0, 20 - ($diff / 3) * 20);
                return [round($pts, 1), "cotes {$values[0]} / {$values[1]} (écart {$diff})"];
            }
        }

        if ($a->ranking && $b->ranking) {
            $diff = abs($a->ranking - $b->ranking);
            // diff 0 -> 20 pts, diff >= 100 -> 0 pt
            $pts = max(0, 20 - ($diff / 100) * 20);
            return [round($pts, 1), "ranking {$a->ranking} vs {$b->ranking} (écart {$diff}, pas de cotes disponibles)"];
        }

        return [0, 'aucune donnée (cotes et ranking absents)'];
    }

    /**
     * NB : "utr_rating" stocke le RANG du joueur dans le classement UTR
     * Sofascore (rankingClass "utr", ex: 10e, 19e...), pas la note UTR
     * elle-même (qui serait une valeur ~1-16). C'est donc un proxy de
     * proximité de niveau parmi d'autres classements, pas un vrai indice Elo.
     */
    private function scoreUtrProximity(Team $a, Team $b): array
    {
        if (!$a->utr_rating || !$b->utr_rating) {
            return [0, 'Rang UTR indisponible pour au moins un joueur'];
        }
        $diff = abs($a->utr_rating - $b->utr_rating);
        // diff 0 -> 15 pts, diff >= 200 -> 0 pt
        $pts = max(0, 15 - ($diff / 200) * 15);
        return [round($pts, 1), "Rang UTR {$a->utr_rating} vs {$b->utr_rating} (écart {$diff})"];
    }

    private function scoreServiceSimilarity(?TennisPlayerSeasonStat $a, ?TennisPlayerSeasonStat $b): array
    {
        if (!$a || !$b) {
            return [0, 'stats de saison indisponibles'];
        }
        $pctA = $a->servicePointsWonPct();
        $pctB = $b->servicePointsWonPct();
        if ($pctA === null || $pctB === null) {
            return [0, 'points de service insuffisants'];
        }
        $diff = abs($pctA - $pctB);
        // diff 0 -> 15 pts, diff >= 15 -> 0 pt
        $pts = max(0, 15 - ($diff / 15) * 15);
        return [round($pts, 1), "% points service gagnés {$pctA}% vs {$pctB}% (écart {$diff}pt)"];
    }

    private function scoreBreakActivity(?TennisPlayerSeasonStat $a, ?TennisPlayerSeasonStat $b): array
    {
        if (!$a || !$b || !$a->matches_count || !$b->matches_count) {
            return [0, 'stats de saison indisponibles'];
        }
        // Activité de break = (BP créées + BP subies) par match, moyenne des 2 joueurs
        $activityA = (($a->break_points_total ?? 0) + ($a->opponent_break_points_total ?? 0)) / max(1, $a->matches_count);
        $activityB = (($b->break_points_total ?? 0) + ($b->opponent_break_points_total ?? 0)) / max(1, $b->matches_count);
        $avgActivity = ($activityA + $activityB) / 2;
        // 0 BP/match -> 0 pt, >= 12 BP/match -> 15 pts (échelle empirique)
        $pts = min(15, ($avgActivity / 12) * 15);
        return [round($pts, 1), "activité BP moyenne: " . round($avgActivity, 1) . "/match"];
    }

    private function scorePoorBreakConversion(?TennisPlayerSeasonStat $a, ?TennisPlayerSeasonStat $b): array
    {
        if (!$a || !$b) {
            return [0, 'stats de saison indisponibles'];
        }
        $convA = $a->breakPointConversionPct();
        $convB = $b->breakPointConversionPct();
        if ($convA === null || $convB === null) {
            return [0, 'balles de break insuffisantes'];
        }
        // Conversion basse (beaucoup d'occasions ratées) = jeux qui traînent.
        // 50%+ de conversion -> 0 pt, 25% ou moins -> 10 pts
        $avgConv = ($convA + $convB) / 2;
        $pts = max(0, min(10, (50 - $avgConv) / 25 * 10));
        return [round($pts, 1), "conversion BP moyenne: " . round($avgConv, 1) . "%"];
    }

    private function scoreH2hHistory(?int $eventId): array
    {
        $h2h = TennisH2hMatch::where('current_event_id', $eventId)->get();
        if ($h2h->isEmpty()) {
            return [0, 'aucune confrontation passée connue'];
        }

        $tightCount = 0;
        foreach ($h2h as $m) {
            $home = $m->home_score['point'] ?? null; // non utilisé, juste garde-fou
            $periods = collect(range(1, 5))->map(fn ($i) => [
                $m->home_score["period{$i}"] ?? null,
                $m->away_score["period{$i}"] ?? null,
            ])->filter(fn ($p) => $p[0] !== null && $p[1] !== null);

            $hasTightSet = $periods->contains(function ($p) {
                [$h, $a] = $p;
                return abs($h - $a) <= 2 && max($h, $a) >= 6;
            });
            if ($hasTightSet) {
                $tightCount++;
            }
        }

        $ratio = $tightCount / max(1, $h2h->count());
        $pts = round($ratio * 10, 1);
        return [$pts, "{$tightCount}/{$h2h->count()} confrontation(s) passée(s) serrée(s)"];
    }

    private function scoreTiebreakFrequency(?TennisPlayerSeasonStat $a, ?TennisPlayerSeasonStat $b): array
    {
        if (!$a || !$b) {
            return [0, 'stats de saison indisponibles'];
        }
        $rateA = $a->tiebreakRatePerMatch();
        $rateB = $b->tiebreakRatePerMatch();
        if ($rateA === null || $rateB === null) {
            return [0, 'aucun match recensé'];
        }
        $avgRate = ($rateA + $rateB) / 2;
        // 0 tie-break/match -> 0 pt, >= 0.5 tie-break/match -> 10 pts
        $pts = min(10, ($avgRate / 0.5) * 10);
        return [round($pts, 1), "taux tie-break moyen: " . round($avgRate, 2) . "/match"];
    }

    private function scoreWinRateSimilarity(?TennisPlayerSeasonStat $a, ?TennisPlayerSeasonStat $b): array
    {
        if (!$a || !$b || !$a->matches_count || !$b->matches_count) {
            return [0, 'stats de saison indisponibles'];
        }
        $winRateA = 100 * $a->wins / $a->matches_count;
        $winRateB = 100 * $b->wins / $b->matches_count;
        $diff = abs($winRateA - $winRateB);
        // diff 0 -> 5 pts, diff >= 40 -> 0 pt
        $pts = max(0, 5 - ($diff / 40) * 5);
        return [round($pts, 1), "% victoires " . round($winRateA) . "% vs " . round($winRateB) . "% (écart " . round($diff) . "pt)"];
    }
}
