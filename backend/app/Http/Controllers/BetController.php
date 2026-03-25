<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Sport;
use App\Models\UserBankroll;
use App\Models\User;
use App\Models\Team;
use App\Models\League;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BetController extends Controller
{
    /**
     * Récupérer tous les paris avec filtres (limités aux bankrolls de l'utilisateur)
     * Récupérer tous les paris avec filtres (limités aux bankrolls de l'utilisateur)
     */
    public function index(Request $request)
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        // Récupérer les IDs des bankrolls de l'utilisateur
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');


        // Charger les relations nécessaires pour afficher les informations complètes des événements
        $query = Bet::with(['sport', 'bankroll', 'events.team1', 'events.team2', 'events.league.country'])
            ->whereIn('bankroll_id', $userBankrollIds);
        $query = Bet::with(['sport', 'bankroll', 'events.team1', 'events.team2', 'events.league.country'])
            ->whereIn('bankroll_id', $userBankrollIds);

        // Debug: Log des filtres reçus
        $this->logFilters($request, 'index');

        // Appliquer les filtres
        if ($request->has('period')) {
            $query->byPeriod($request->get('period'));
        }
        if ($request->has('sports')) {
            $query->bySport($request->get('sports'));
        }
        if ($request->has('bet_types')) {
            $query->byBetType($request->get('bet_types'));
        }
        if ($request->has('bookmakers')) {
            $query->byBookmaker($request->get('bookmakers'));
        }
        if ($request->has('tipsters')) {
            $query->byTipster($request->get('tipsters'));
        }

        // Filtres de date supplémentaires
        if ($request->has('start_date')) {
            $query->where('bet_date', '>=', Carbon::parse($request->start_date));
        }
        if ($request->has('end_date')) {
            $query->where('bet_date', '<=', Carbon::parse($request->end_date));
        }

        $bets = $query->orderBy('bet_date', 'desc')->get();
        $bets = $query->orderBy('bet_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $bets,
            'total' => $bets->count(),
            'userbankrollids' => $userBankrollIds
        ]);
    }

    /**
     * Récupérer les statistiques des paris (limitées aux bankrolls de l'utilisateur)
     * Récupérer les statistiques des paris (limitées aux bankrolls de l'utilisateur)
     */
    public function stats(Request $request): JsonResponse
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        // Récupérer les IDs des bankrolls de l'utilisateur
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

        $query = Bet::whereIn('bankroll_id', $userBankrollIds);
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        // Récupérer les IDs des bankrolls de l'utilisateur
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

        $query = Bet::whereIn('bankroll_id', $userBankrollIds);

        // Debug: Log des filtres reçus
        $this->logFilters($request, 'stats');

        // Appliquer les filtres
        if ($request->has('period')) {
            $query->byPeriod($request->get('period'));
        }
        if ($request->has('sports')) {
            $query->bySport($request->get('sports'));
        }
        if ($request->has('bet_types')) {
            $query->byBetType($request->get('bet_types'));
        }
        if ($request->has('bookmakers')) {
            $query->byBookmaker($request->get('bookmakers'));
        }
        if ($request->has('tipsters')) {
            $query->byTipster($request->get('tipsters'));
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_bets,
            SUM(stake) as total_stake,
            SUM(CASE WHEN result = "win" THEN (stake * global_odds - stake) ELSE 0 END) as total_wins,
            SUM(CASE WHEN result = "lost" THEN -stake ELSE 0 END) as total_losses,
            SUM(CASE
                WHEN result = "win" THEN (stake * global_odds - stake)
                WHEN result = "lost" THEN -stake
                ELSE 0
            END) as total_profit_loss,
            AVG(global_odds) as average_odds,
            COUNT(CASE WHEN result = "win" THEN 1 END) as win_bets,
            COUNT(CASE WHEN result = "lost" THEN 1 END) as lost_bets,
            COUNT(CASE WHEN result = "pending" OR result IS NULL THEN 1 END) as pending_bets,

        ')->first();

        $winRate = $stats->total_bets > 0 ? ($stats->win_bets / $stats->total_bets) * 100 : 0;
        $roi = $stats->total_stake > 0 ? ($stats->total_profit_loss / $stats->total_stake) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_bets' => $stats->total_bets,
                'total_stake' => $stats->total_stake,
                'total_wins' => $stats->total_wins,
                'total_losses' => $stats->total_losses,
                'total_profit_loss' => $stats->total_profit_loss,
                'average_odds' => $stats->average_odds,
                'win_bets' => $stats->win_bets,
                'lost_bets' => $stats->lost_bets,
                'pending_bets' => $stats->pending_bets,
                'win_rate' => round($winRate, 2),
                'roi' => round($roi, 2)
            ]
        ]);
    }

    /**
     * Récupérer les statistiques détaillées des paris
     * Inclut les séries, mises en cours, plus gros gains/pertes, etc.
     */
    public function detailedStats(Request $request): JsonResponse
    {
        try {
            // Récupérer l'utilisateur connecté
            $user = auth()->user();

            // Récupérer les IDs des bankrolls de l'utilisateur
            $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

            $query = Bet::whereIn('bankroll_id', $userBankrollIds);
            // Récupérer l'utilisateur connecté
            $user = auth()->user();

            // Récupérer les IDs des bankrolls de l'utilisateur
            $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

            $query = Bet::whereIn('bankroll_id', $userBankrollIds);

            // Appliquer les mêmes filtres que les autres méthodes
            if ($request->has('period')) {
                $query->byPeriod($request->get('period'));
            }
            if ($request->has('sports')) {
                $query->bySport($request->get('sports'));
            }
            if ($request->has('bet_types')) {
                $query->byBetType($request->get('bet_types'));
            }
            if ($request->has('bookmakers')) {
                $query->byBookmaker($request->get('bookmakers'));
            }
            if ($request->has('tipsters')) {
                $query->byTipster($request->get('tipsters'));
            }

            // Récupérer tous les paris pour les calculs détaillés
            $bets = $query->orderBy('bet_date', 'asc')->get();

            // Calculs des statistiques détaillées
            $inPlayStake = $bets->where('result', 'pending')->sum('stake');
            $maxStake = $bets->max('stake');
            $minStake = $bets->min('stake');
            $biggestWinOdds = $bets->where('result', 'win')->max('global_odds');
            $smallestWinOdds = $bets->where('result', 'win')->min('global_odds');



            // Calcul du plus gros bénéfice et de la plus grosse perte
            $biggestProfit = 0;
            $biggestLoss = 0;



            foreach ($bets as $bet) {
                $profitLoss = $this->calculateBetProfitLoss($bet);
                if ($profitLoss > $biggestProfit) {
                    $biggestProfit = $profitLoss;
                }
                if ($profitLoss < $biggestLoss) {
                    $biggestLoss = $profitLoss;
                }
            }

            // Calcul des séries de victoires et défaites
            $streaks = $this->calculateStreaks($bets);

            return response()->json([
                'success' => true,
                'data' => [
                    'in_play_stake' => round($inPlayStake, 2),
                    'max_stake' => $maxStake ? round($maxStake, 2) : null,
                    'min_stake' => $minStake ? round($minStake, 2) : null,
                    'biggest_win_odds' => $biggestWinOdds ? round($biggestWinOdds, 3) : null,
                    'smallest_win_odds' => $smallestWinOdds ? round($smallestWinOdds, 3) : null,
                    'biggest_profit' => $biggestProfit > 0 ? round($biggestProfit, 2) : null,
                    'biggest_loss' => $biggestLoss < 0 ? round(abs($biggestLoss), 2) : null,
                    'max_win_streak' => $streaks['max_win_streak'],
                    'max_lose_streak' => $streaks['max_lose_streak'],
                    'current_win_streak' => $streaks['current_win_streak'],
                    'current_lose_streak' => $streaks['current_lose_streak']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur detailedStats: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            Log::error('Erreur detailedStats: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'error' => 'Une erreur est survenue lors du calcul des statistiques détaillées.',
            ], 500);
        }
    }

    /**
     * Calculer les séries de victoires et défaites
     */
    private function calculateStreaks($bets): array
    {
        $maxWinStreak = 0;
        $maxLoseStreak = 0;
        $currentWinStreak = 0;
        $currentLoseStreak = 0;
        $tempWinStreak = 0;
        $tempLoseStreak = 0;

        // Filtrer seulement les paris avec résultat défini (win/lost)
        $settledBets = $bets->whereIn('result', ['win', 'lost']);

        foreach ($settledBets as $bet) {
            if ($bet->result === 'win') {
                $tempWinStreak++;
                $tempLoseStreak = 0;
                if ($tempWinStreak > $maxWinStreak) {
                    $maxWinStreak = $tempWinStreak;
                }
            } elseif ($bet->result === 'lost') {
                $tempLoseStreak++;
                $tempWinStreak = 0;
                if ($tempLoseStreak > $maxLoseStreak) {
                    $maxLoseStreak = $tempLoseStreak;
                }
            }
        }

        // Les séries actuelles sont les dernières séries en cours
        $currentWinStreak = $tempWinStreak;
        $currentLoseStreak = $tempLoseStreak;

        return [
            'max_win_streak' => $maxWinStreak > 0 ? $maxWinStreak : null,
            'max_lose_streak' => $maxLoseStreak > 0 ? $maxLoseStreak : null,
            'current_win_streak' => $currentWinStreak > 0 ? $currentWinStreak : 0,
            'current_lose_streak' => $currentLoseStreak > 0 ? $currentLoseStreak : 0
        ];
    }




    /**
     * Récupérer l'évolution du capital
     */
    public function capitalEvolution(Request $request): JsonResponse
    {
        try {
            // Récupérer l'utilisateur connecté
            $user = auth()->user();

            if ($user) {
                Log::info('Calcul capitalEvolution pour user_id: ' . $user->id, ['filters' => $request->all()]);
            } else {
                Log::warning('Calcul capitalEvolution sans utilisateur authentifié', ['filters' => $request->all()]);
                return response()->json([
                    'success' => false,
                    'error' => 'Utilisateur non authentifié'
                ], 401);
            }

            if ($request->has('bankrolls')) {
                // Récupère les valeurs fournies (peut être un tableau, une chaîne CSV ou JSON)
                $requested = $request->get('bankrolls');

                // Normaliser en entiers
                $requestedIds = array_map('intval', $requested);

                // Ne garder que les bankrolls appartenant à l'utilisateur
                $userBankrolls = UserBankroll::where('user_id', $user->id)
                    ->whereIn('id', $requestedIds)
                    ->get();

                Log::info('Filtres de bankrolls reçus dans capitalEvolution', [
                    'requested_ids' => $requestedIds,
                    'matched_ids' => $userBankrolls->pluck('id')->toArray()
                ]);
            } else {
                Log::info('Aucun filtre de bankrolls reçu dans capitalEvolution');
                // Récupérer les bankrolls de l'utilisateur avec leur capital initial
                $userBankrolls = UserBankroll::where('user_id', $user->id)->get();
            }

            $userBankrollIds = $userBankrolls->pluck('id');

            // Capital initial = somme des capitals de toutes les bankrolls de l'utilisateur
            $initialCapital = null;
            try {
                $initialCapital = (float) $userBankrolls->sum('bankroll_start_amount');
            } catch (\Throwable $e) {
                Log::error('Erreur lors du calcul du capital initial', ['exception' => $e, 'user_id' => $user->id]);
                return response()->json(['error' => 'Erreur lors du calcul du capital initial'], 500);
            }

            if ($initialCapital === null) {
                Log::error('Capital initial introuvable ou invalide', ['user_id' => $user->id]);
                return response()->json(['error' => 'Capital initial introuvable'], 500);
            }
            // Récupérer l'utilisateur connecté
            $user = auth()->user();

            if ($user) {
                Log::info('Calcul capitalEvolution pour user_id: ' . $user->id, ['filters' => $request->all()]);
            } else {
                Log::warning('Calcul capitalEvolution sans utilisateur authentifié', ['filters' => $request->all()]);
                return response()->json([
                    'success' => false,
                    'error' => 'Utilisateur non authentifié'
                ], 401);
            }

            if ($request->has('bankrolls')) {
                // Récupère les valeurs fournies (peut être un tableau, une chaîne CSV ou JSON)
                $requested = $request->get('bankrolls');

                // Normaliser en entiers
                $requestedIds = array_map('intval', $requested);

                // Ne garder que les bankrolls appartenant à l'utilisateur
                $userBankrolls = UserBankroll::where('user_id', $user->id)
                    ->whereIn('id', $requestedIds)
                    ->get();

                Log::info('Filtres de bankrolls reçus dans capitalEvolution', [
                    'requested_ids' => $requestedIds,
                    'matched_ids' => $userBankrolls->pluck('id')->toArray()
                ]);
            } else {
                Log::info('Aucun filtre de bankrolls reçu dans capitalEvolution');
                // Récupérer les bankrolls de l'utilisateur avec leur capital initial
                $userBankrolls = UserBankroll::where('user_id', $user->id)->get();
            }

            $userBankrollIds = $userBankrolls->pluck('id');

            // Capital initial = somme des capitals de toutes les bankrolls de l'utilisateur
            $initialCapital = null;
            try {
                $initialCapital = (float) $userBankrolls->sum('bankroll_start_amount');
            } catch (\Throwable $e) {
                Log::error('Erreur lors du calcul du capital initial', ['exception' => $e, 'user_id' => $user->id]);
                return response()->json(['error' => 'Erreur lors du calcul du capital initial'], 500);
            }

            if ($initialCapital === null) {
                Log::error('Capital initial introuvable ou invalide', ['user_id' => $user->id]);
                return response()->json(['error' => 'Capital initial introuvable'], 500);
            }

            // Base query + filtres (aligné avec index/stats)
            $query = Bet::whereIn('bankroll_id', $userBankrollIds);
            $query = Bet::whereIn('bankroll_id', $userBankrollIds);

            if ($request->has('period')) {
                $query->byPeriod($request->get('period'));
            }
            if ($request->has('sports')) {
                $query->bySport($request->get('sports'));
            }
            if ($request->has('bet_types')) {
                $query->byBetType($request->get('bet_types'));
            }
            if ($request->has('bookmakers')) {
                $query->byBookmaker($request->get('bookmakers'));
            }
            if ($request->has('tipsters')) {
                $query->byTipster($request->get('tipsters'));
            }
            if ($request->has('start_date')) {
                $query->where('bet_date', '>=', Carbon::parse($request->start_date));
            }
            if ($request->has('end_date')) {
                $query->where('bet_date', '<=', Carbon::parse($request->end_date));
            }

            // On ne tient compte que des paris datés
            $bets = $query
                ->whereNotNull('bet_date')
                ->orderBy('bet_date', 'asc')
                ->get(['bet_date', 'stake', 'global_odds', 'result']);

            // Agrégation par jour: profit/perte quotidien
            $daily = [];
            foreach ($bets as $bet) {
                $dateKey = Carbon::parse($bet->bet_date)->toDateString(); // Y-m-d
                $pl = $this->calculateBetProfitLoss($bet);

                if (!array_key_exists($dateKey, $daily)) {
                    $daily[$dateKey] = 0.0;
                }
                $daily[$dateKey] += (float) $pl;
            }

            // Si aucun pari filtré, renvoyer une structure cohérente
            if (empty($daily)) {
                return response()->json([
                    'success' => true,
                    'data' => [$initialCapital],
                    'labels' => [now()->format('d/m')],
                    'capital_evolution' => [[
                        'date' => now()->toDateString(),
                        'capital' => $initialCapital,
                        'daily_profit_loss' => 0.0,
                        'cumulative_profit_loss' => 0.0,
                    ]],
                    'initial_capital' => $initialCapital,
                    'current_capital' => $initialCapital,
                    'total_profit_loss' => 0.0,
                    'total_profit_loss_percentage' => 0.0,
                ]);
            }

            // Ordonner par date et calculer cumul
            ksort($daily);

            $labels = [];
            $capitals = [];
            $details = [];

            $currentCapital = $initialCapital;
            $cumulative = 0.0;

            foreach ($daily as $ymd => $dailyPL) {
                $cumulative += (float) $dailyPL;
                $currentCapital = $initialCapital + $cumulative;

                $labels[] = Carbon::parse($ymd)->format('d/m/Y');
                $labels[] = Carbon::parse($ymd)->format('d/m/Y');
                $capitals[] = round($currentCapital, 2);
                $details[] = [
                    'date' => $ymd,
                    'capital' => round($currentCapital, 2),
                    'daily_profit_loss' => round((float) $dailyPL, 2),
                    'cumulative_profit_loss' => round($cumulative, 2),
                ];
            }

            $totalPL = $currentCapital - $initialCapital;
            $percent = $initialCapital != 0.0 ? ($totalPL / $initialCapital) * 100.0 : 0.0;

            return response()->json([
                'success' => true,
                'data' => $capitals,
                'labels' => $labels,
                'capital_evolution' => $details,
                'initial_capital' => round($initialCapital, 2),
                'current_capital' => round($currentCapital, 2),
                'total_profit_loss' => round($totalPL, 2),
                'total_profit_loss_percentage' => round($percent, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur capitalEvolution: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            Log::error('Erreur capitalEvolution: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'error' => 'Une erreur est survenue lors du calcul de l’évolution du capital.',
            ], 500);
        }
    }

    /**
     * Calculer le profit/perte d'un pari
     */
    private function calculateBetProfitLoss($bet): float
    {
        if ($bet->result === 'win') {
            return (float) (($bet->stake * $bet->global_odds) - $bet->stake);
        } elseif ($bet->result === 'lost') {
            return (float) (-$bet->stake);
        } elseif ($bet->result === 'void') {
            return 0.0;
        }
        return 0.0;
    }

    /**
     * Récupérer les options de filtres
     */
    public function filterOptions(): JsonResponse
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        // Récupérer les IDs des bankrolls de l'utilisateur
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        // Récupérer les IDs des bankrolls de l'utilisateur
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

        $sports = Sport::select('name')
            ->distinct()
            ->whereHas('bets', function ($query) use ($userBankrollIds) {
                $query->whereIn('bankroll_id', $userBankrollIds);
            })
            ->whereHas('bets', function ($query) use ($userBankrollIds) {
                $query->whereIn('bankroll_id', $userBankrollIds);
            })
            ->pluck('name')
            ->filter()
            ->map(function ($sport) {
                return ['label' => ucfirst($sport), 'value' => $sport];
            })
            ->values();

        $betTypes = Bet::select('bet_code')
            ->distinct()
            ->whereIn('bankroll_id', $userBankrollIds)
            ->whereIn('bankroll_id', $userBankrollIds)
            ->pluck('bet_code')
            ->filter()
            ->map(function ($type) {
                return ['label' => ucfirst($type), 'value' => $type];
            })
            ->values();

        $bookmakers = Bet::select('bet_code')
            ->distinct()
            ->whereIn('bankroll_id', $userBankrollIds)
            ->whereIn('bankroll_id', $userBankrollIds)
            ->pluck('bet_code')
            ->filter()
            ->map(function ($bookmaker) {
                return ['label' => ucfirst($bookmaker), 'value' => $bookmaker];
            })
            ->values();

        $tipsters = Bet::select('bet_code')
            ->distinct()
            ->whereIn('bankroll_id', $userBankrollIds)
            ->whereIn('bankroll_id', $userBankrollIds)
            ->pluck('bet_code')
            ->filter()
            ->map(function ($tipster) {
                return ['label' => ucfirst($tipster), 'value' => $tipster];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'sports' => $sports,
                'bet_types' => $betTypes,
                'bookmakers' => $bookmakers,
                'tipsters' => $tipsters
            ]
        ]);
    }

    /**
     * Créer un nouveau pari
     */
    public function store(Request $request): JsonResponse
    {
        // Récupérer l'utilisateur connecté pour valider les bankrolls
        $user = auth()->user();
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id')->toArray();

        // Récupérer l'utilisateur connecté pour valider les bankrolls
        $user = auth()->user();
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id')->toArray();

        // Validation des données principales du pari
        $validator = Validator::make($request->all(), [
            'bet_date' => 'required|date',
            'global_odds' => 'required|numeric|min:1',
            'bet_code' => 'required|string|max:256',
            'result' => 'nullable|in:win,lost,void,pending',
            'stake' => 'required|numeric|min:0',
            'stake_type' => 'required|in:currency,percentage',
            'bankroll_id' => 'nullable|in:' . implode(',', $userBankrollIds),
            'bankroll_id' => 'nullable|in:' . implode(',', $userBankrollIds),
            // Validation du tableau d'événements
            'events' => 'required|array|min:1',
            'events.*.sport_id' => 'nullable|exists:sports,id',
            'events.*.country_id' => 'nullable|exists:countries,id',
            'events.*.league_id' => 'nullable|exists:leagues,id',
            'events.*.team1_id' => 'nullable|exists:teams,id',
            'events.*.team2_id' => 'nullable|exists:teams,id',
            'events.*.description' => 'required|string|max:500',
            'events.*.result' => 'nullable|in:win,lost,void,pending',
            'events.*.odds' => 'nullable|numeric|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        // Déterminer la bankroll à utiliser
        $bankrollId = $validatedData['bankroll_id'] ?? null;

        // Si pas de bankroll spécifiée, prendre la première de l'utilisateur
        if (!$bankrollId) {
            $defaultBankroll = UserBankroll::where('user_id', $user->id)->first();

            if (!$defaultBankroll) {
                return response()->json([
                    'success' => false,
                    'error' => 'Aucune bankroll trouvée. Veuillez créer une bankroll avant d\'ajouter un pari.'
                ], 400);
            }

            $bankrollId = $defaultBankroll->id;
        }


        // Déterminer la bankroll à utiliser
        $bankrollId = $validatedData['bankroll_id'] ?? null;

        // Si pas de bankroll spécifiée, prendre la première de l'utilisateur
        if (!$bankrollId) {
            $defaultBankroll = UserBankroll::where('user_id', $user->id)->first();

            if (!$defaultBankroll) {
                return response()->json([
                    'success' => false,
                    'error' => 'Aucune bankroll trouvée. Veuillez créer une bankroll avant d\'ajouter un pari.'
                ], 400);
            }

            $bankrollId = $defaultBankroll->id;
        }

        // Extraire les données du pari (sans les événements)
        $betData = [
            'bet_date' => $validatedData['bet_date'],
            'global_odds' => $validatedData['global_odds'],
            'bet_code' => $validatedData['bet_code'],
            'result' => $validatedData['result'] ?? 'pending',
            'stake' => $validatedData['stake'],
            'bankroll_id' => $bankrollId,
            'stake' => $validatedData['stake'],
            'bankroll_id' => $bankrollId
        ];

        // Créer le pari
        $bet = Bet::create($betData);

        // Créer et associer les événements
        $eventIds = [];
        foreach ($validatedData['events'] as $eventData) {
            // Créer l'événement avec les données reçues
            $event = \App\Models\Event::create([
                'team1_id' => $eventData['team1_id'],
                'team2_id' => $eventData['team2_id'],
                'league_id' => $eventData['league_id'],
                'type' => $eventData['description'], // Utiliser la description comme type
                'market' => $eventData['description'], // Utiliser la description comme marché
                'odd' => $eventData['odds'],
                'event_date' => $validatedData['bet_date'] // Utiliser la date du pari
            ]);



            $eventIds[] = $event->id;
        }

        // Associer les événements au pari via la table pivot
        $bet->events()->attach($eventIds);

        // Si tous les events ont le même sport_id, attribuer ce sport au pari, sinon null
        $sportIds = collect($validatedData['events'])->pluck('sport_id')->filter()->unique()->values();
        if ($sportIds->count() === 1) {
            $bet->sport_id = $sportIds->first();
        } else {
            $bet->sport_id = null;
        }
        $bet->save();

        return response()->json([
            'success' => true,
            'message' => 'Pari créé avec succès',
            'data' => $bet->load(['sport', 'events.team1', 'events.team2', 'events.league.country'])
        ], 201);
    }

    /**
     * Créer un pari via le bot Auxobot (autorisé par middleware auxobot).
     * Utilise l'utilisateur configuré via `AUXOBOT_USER_ID` pour valider les bankrolls.
     */
    public function storeAuxobot(Request $request): JsonResponse
    {
        // Trouver l'utilisateur associé au bot
        $auxUserId = env('AUXOBOT_USER_ID');
        $user = $auxUserId ? User::find($auxUserId) : null;

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Auxobot user not configured'], 500);
        }

        // Préparer/normaliser les données : accepter soit un tableau `events`, soit `event_list`,
        // soit les champs top-level (`equipe_1`/`equipe_2`). Résoudre les noms/sofascore_id en IDs.
        $input = $request->all();

        // Assurer que 'events' existe
        if (!isset($input['events'])) {
            $input['events'] = [];
        }

        // 1) Si l'appelant fournit un tableau 'events', normaliser chaque item (accepter noms)
        if (!empty($input['events'])) {
            foreach ($input['events'] as $i => $ev) {
                $team1Val = $ev['team1_id'] ?? $ev['team1'] ?? $ev['team1_name'] ?? $ev['equipe_1'] ?? null;
                $team2Val = $ev['team2_id'] ?? $ev['team2'] ?? $ev['team2_name'] ?? $ev['equipe_2'] ?? null;
                $leagueVal = $ev['league_id'] ?? $ev['league'] ?? null;

                $input['events'][$i]['team1_id'] = $team1Val ? Team::findIdBySofascoreOrName($team1Val) : null;
                $input['events'][$i]['team2_id'] = $team2Val ? Team::findIdBySofascoreOrName($team2Val) : null;
                $input['events'][$i]['league_id'] = $leagueVal ? League::findIdBySofascoreOrName($leagueVal) : null;
            }

            // 2) Sinon, si 'event_list' est fourni, le parser
        } elseif (!empty($input['event_list'])) {
            foreach ($input['event_list'] as $event) {
                $team1Id = isset($event['equipe_1']) ? Team::findIdBySofascoreOrName($event['equipe_1']) : null;
                $team2Id = isset($event['equipe_2']) ? Team::findIdBySofascoreOrName($event['equipe_2']) : null;

                $leagueVal = $event['league_id'] ?? $event['league'] ?? null;
                $leagueId = $leagueVal ? League::findIdBySofascoreOrName($leagueVal) : null;

                $input['events'][] = [
                    'team1_id' => $team1Id,
                    'team2_id' => $team2Id,
                    'league_id' => $leagueId,
                    'description' => $event['selection'] ?? $input['selection'] ?? null,
                    'odds' => $event['odds'] ?? $input['odds'] ?? null,
                    'sport_id' => $event['sport_id'] ?? $input['sport_id'] ?? null,
                ];
            }

            // 3) Sinon, fallback: construire un seul event à partir des champs top-level
        } else {
            $team1Id = isset($input['equipe_1']) ? Team::findIdBySofascoreOrName($input['equipe_1']) : null;
            $team2Id = isset($input['equipe_2']) ? Team::findIdBySofascoreOrName($input['equipe_2']) : null;

            $leagueVal = $input['league_id'] ?? $input['league'] ?? null;
            $leagueId = $leagueVal ? League::findIdBySofascoreOrName($leagueVal) : null;

            $input['events'][] = [
                'team1_id' => $team1Id,
                'team2_id' => $team2Id,
                'league_id' => $leagueId,
                'description' => $input['selection'] ?? null,
                'odds' => $input['odds'] ?? null,
                'sport_id' => $input['sport_id'] ?? null,
            ];
        }

        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id')->toArray();

        // Validation similaire à store(), limitée aux bankrolls de l'utilisateur Auxobot
        $validator = Validator::make($input, [
            'bet_date' => 'required|date',
            'global_odds' => 'required|numeric|min:1',
            'bet_code' => 'required|string|max:256',
            'result' => 'nullable|in:win,lost,void,pending',
            'stake' => 'required|numeric|min:0',
            'stake_type' => 'required|in:currency,percentage',
            'bankroll_id' => 'nullable|in:' . implode(',', $userBankrollIds),
            'events' => 'required|array|min:1',
            'events.*.sport_id' => 'nullable|exists:sports,id',
            'events.*.country_id' => 'nullable|exists:countries,id',
            'events.*.league_id' => 'nullable|exists:leagues,id',
            'events.*.team1_id' => 'nullable|exists:teams,id',
            'events.*.team2_id' => 'nullable|exists:teams,id',
            'events.*.description' => 'required|string|max:500',
            'events.*.result' => 'nullable|in:win,lost,void,pending',
            'events.*.odds' => 'nullable|numeric|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        // Déterminer la bankroll
        $bankrollId = $validatedData['bankroll_id'] ?? null;
        if (!$bankrollId) {
            $defaultBankroll = UserBankroll::where('user_id', $user->id)->first();
            if (!$defaultBankroll) {
                return response()->json(['success' => false, 'error' => 'Aucune bankroll trouvée pour auxobot'], 400);
            }
            $bankrollId = $defaultBankroll->id;
        }

        // Créer le pari
        $betData = [
            'bet_date' => $validatedData['bet_date'],
            'global_odds' => $validatedData['global_odds'],
            'bet_code' => $validatedData['bet_code'],
            'result' => $validatedData['result'] ?? 'pending',
            'stake' => $validatedData['stake'],
            'bankroll_id' => $bankrollId
        ];

        $bet = Bet::create($betData);

        // Créer événements
        $eventIds = [];
        foreach ($validatedData['events'] as $eventData) {
            $event = \App\Models\Event::create([
                'team1_id' => $eventData['team1_id'] ?? null,
                'team2_id' => $eventData['team2_id'] ?? null,
                'league_id' => $eventData['league_id'] ?? null,
                'type' => $eventData['description'],
                'market' => $eventData['description'],
                'odd' => $eventData['odds'] ?? null,
                'event_date' => $validatedData['bet_date']
            ]);

            $eventIds[] = $event->id;
        }

        $bet->events()->attach($eventIds);

        // Si tous les events ont le même sport_id, attribuer ce sport au pari, sinon null
        $sportIds = collect($validatedData['events'])->pluck('sport_id')->filter()->unique()->values();
        if ($sportIds->count() === 1) {
            $bet->sport_id = $sportIds->first();
        } else {
            $bet->sport_id = null;
        }
        $bet->save();

        return response()->json(['success' => true, 'message' => 'Pari créé par Auxobot', 'data' => $bet->load(['sport', 'events.team1', 'events.team2', 'events.league.country'])], 201);
    }

    /**
     * Recherche l'ID d'une équipe par son nom (ou nickname) en utilisant un LIKE.
     */
    private function findTeamIdByName(?string $name): ?int
    {
        if (!$name) {
            return null;
        }

        $team = Team::where('name', 'like', '%' . $this->escapeLike($name) . '%')
            ->orWhere('nickname', 'like', '%' . $this->escapeLike($name) . '%')
            ->first();

        return $team ? $team->id : null;
    }

    /**
     * Escape special characters for SQL LIKE queries.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Récupérer un pari spécifique (seulement si il appartient à l'utilisateur)
     */
    public function show(Bet $bet): JsonResponse
    {
        // Vérifier que le pari appartient à une bankroll de l'utilisateur
        $user = auth()->user();
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

        if (!$userBankrollIds->contains($bet->bankroll_id)) {
            return response()->json([
                'success' => false,
                'error' => 'Pari non trouvé ou accès non autorisé.'
            ], 404);
        }

        // Vérifier que le pari appartient à une bankroll de l'utilisateur
        $user = auth()->user();
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

        if (!$userBankrollIds->contains($bet->bankroll_id)) {
            return response()->json([
                'success' => false,
                'error' => 'Pari non trouvé ou accès non autorisé.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $bet->load(['sport', 'bankroll', 'events.team1', 'events.team2', 'events.league.country'])
        ]);
    }

    /**
     * Mettre à jour un pari (seulement si il appartient à l'utilisateur)
     * Mettre à jour un pari (seulement si il appartient à l'utilisateur)
     */
    public function update(Request $request, Bet $bet): JsonResponse
    {
        // Vérifier que le pari appartient à une bankroll de l'utilisateur
        $user = auth()->user();
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

        if (!$userBankrollIds->contains($bet->bankroll_id)) {
            return response()->json([
                'success' => false,
                'error' => 'Pari non trouvé ou accès non autorisé.'
            ], 404);
        }

        // Vérifier que le pari appartient à une bankroll de l'utilisateur
        $user = auth()->user();
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

        if (!$userBankrollIds->contains($bet->bankroll_id)) {
            return response()->json([
                'success' => false,
                'error' => 'Pari non trouvé ou accès non autorisé.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'bet_date' => 'sometimes|date',
            'global_odds' => 'sometimes|numeric|min:1',
            'bet_code' => 'sometimes|string|max:256',
            'result' => 'nullable|in:win,lost,void,pending',
            'stake' => 'sometimes|numeric|min:0',
            'stake_type' => 'sometimes|in:currency,percentage',
            'bankroll_id' => 'sometimes|in:' . $userBankrollIds->implode(','),
            // Validation du tableau d'événements pour la mise à jour
            'events' => 'sometimes|array|min:1',
            'events.*.sport_id' => 'nullable|exists:sports,id',
            'events.*.country_id' => 'nullable|exists:countries,id',
            'events.*.league_id' => 'nullable|exists:leagues,id',
            'events.*.team1_id' => 'nullable|exists:teams,id',
            'events.*.team2_id' => 'nullable|exists:teams,id',
            'events.*.description' => 'sometimes|string|max:500',
            'events.*.result' => 'nullable|in:win,lost,void,pending',
            'events.*.odds' => 'nullable|numeric|min:1',
            'stake' => 'sometimes|numeric|min:0',
            'stake_type' => 'sometimes|in:currency,percentage',
            'bankroll_id' => 'sometimes|in:' . $userBankrollIds->implode(','),
            // Validation du tableau d'événements pour la mise à jour
            'events' => 'sometimes|array|min:1',
            'events.*.sport_id' => 'nullable|exists:sports,id',
            'events.*.country_id' => 'nullable|exists:countries,id',
            'events.*.league_id' => 'nullable|exists:leagues,id',
            'events.*.team1_id' => 'nullable|exists:teams,id',
            'events.*.team2_id' => 'nullable|exists:teams,id',
            'events.*.description' => 'sometimes|string|max:500',
            'events.*.result' => 'nullable|in:win,lost,void,pending',
            'events.*.odds' => 'nullable|numeric|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        // Extraire les données du pari (sans les événements)
        $betData = collect($validatedData)->except('events')->toArray();

        // Mettre à jour le pari principal
        $bet->update($betData);

        // Si des événements sont fournis, les mettre à jour
        if (isset($validatedData['events'])) {
            // Détacher tous les anciens événements
            $bet->events()->detach();

            // Supprimer les anciens événements (optionnel, selon la logique métier)
            // $bet->events()->delete();

            // Créer et associer les nouveaux événements
            $eventIds = [];
            foreach ($validatedData['events'] as $eventData) {
                // Créer l'événement avec les données reçues
                $event = \App\Models\Event::create([
                    'team1_id' => $eventData['team1_id'] ?? null,
                    'team2_id' => $eventData['team2_id'] ?? null,
                    'league_id' => $eventData['league_id'] ?? null,
                    'type' => $eventData['description'] ?? '', // Utiliser la description comme type
                    'market' => $eventData['description'] ?? '', // Utiliser la description comme marché
                    'odd' => $eventData['odds'] ?? null,
                    'event_date' => $validatedData['bet_date'] ?? $bet->bet_date // Utiliser la date du pari
                ]);

                $eventIds[] = $event->id;
            }

            // Associer les nouveaux événements au pari via la table pivot
            $bet->events()->attach($eventIds);

            // Si tous les events ont le même sport_id, attribuer ce sport au pari, sinon null
            $sportIds = collect($validatedData['events'])->pluck('sport_id')->filter()->unique()->values();
            if ($sportIds->count() === 1) {
                $bet->sport_id = $sportIds->first();
            } else {
                $bet->sport_id = null;
            }
            $bet->save();
        }
        return response()->json([
            'success' => true,
            'message' => 'Pari mis à jour avec succès',
            'data' => $bet->load(['sport', 'bankroll', 'events.team1', 'events.team2', 'events.league.country'])
        ]);
    }

    /**
     * Supprimer un pari (seulement si il appartient à l'utilisateur)
     * Supprimer un pari (seulement si il appartient à l'utilisateur)
     */
    public function destroy(Bet $bet): JsonResponse
    {
        // Vérifier que le pari appartient à une bankroll de l'utilisateur
        $user = auth()->user();
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

        if (!$userBankrollIds->contains($bet->bankroll_id)) {
            return response()->json([
                'success' => false,
                'error' => 'Pari non trouvé ou accès non autorisé.'
            ], 404);
        }

        // Vérifier que le pari appartient à une bankroll de l'utilisateur
        $user = auth()->user();
        $userBankrollIds = UserBankroll::where('user_id', $user->id)->pluck('id');

        if (!$userBankrollIds->contains($bet->bankroll_id)) {
            return response()->json([
                'success' => false,
                'error' => 'Pari non trouvé ou accès non autorisé.'
            ], 404);
        }

        $bet->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pari supprimé avec succès'
        ]);
    }

    /**
     * Méthode de débogage pour logger les filtres reçus
     */
    private function logFilters(Request $request, string $method): void
    {
        $filters = [
            'period' => $request->get('period'),
            'sports' => $request->get('sports'),
            'bet_types' => $request->get('bet_types'),
            'bookmakers' => $request->get('bookmakers'),
            'bankrolls' => $request->get('bankrolls'),
            'bankrolls' => $request->get('bankrolls'),
            'tipsters' => $request->get('tipsters'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date')
        ];

        Log::info("Filtres reçus dans {$method}:", $filters);

        // Vérifier quels filtres sont présents
        $presentFilters = [];
        foreach ($filters as $key => $value) {
            if ($request->has($key)) {
                $presentFilters[$key] = $value;
            }
        }

        Log::info("Filtres présents dans {$method}:", $presentFilters);
    }
}
