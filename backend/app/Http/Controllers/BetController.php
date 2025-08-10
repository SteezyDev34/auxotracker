<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Sport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BetController extends Controller
{
    /**
     * Récupérer tous les paris avec filtres
     */
    public function index(Request $request)
    {
        // Charger les relations nécessaires pour afficher les informations complètes des événements
        $query = Bet::with(['sport', 'events.team1', 'events.team2', 'events.league.country']);

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

        $bets = $query->orderBy('bet_date', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $bets->items(),
            'pagination' => [
                'current_page' => $bets->currentPage(),
                'last_page' => $bets->lastPage(),
                'per_page' => $bets->perPage(),
                'total' => $bets->total()
            ]
        ]);
    }

    /**
     * Récupérer les statistiques des paris
     */
    public function stats(Request $request): JsonResponse
    {
        $query = Bet::query();

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
            SUM(CASE WHEN result = "won" THEN (stake * global_odds - stake) ELSE 0 END) as total_wins,
            SUM(CASE WHEN result = "lost" THEN -stake ELSE 0 END) as total_losses,
            SUM(CASE
                WHEN result = "won" THEN (stake * global_odds - stake)
                WHEN result = "lost" THEN -stake
                ELSE 0
            END) as total_profit_loss,
            AVG(global_odds) as average_odds,
            COUNT(CASE WHEN result = "won" THEN 1 END) as won_bets,
            COUNT(CASE WHEN result = "lost" THEN 1 END) as lost_bets,
            COUNT(CASE WHEN result = "pending" OR result IS NULL THEN 1 END) as pending_bets
        ')->first();

        $winRate = $stats->total_bets > 0 ? ($stats->won_bets / $stats->total_bets) * 100 : 0;
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
                'won_bets' => $stats->won_bets,
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
            $query = Bet::query();

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
            $biggestWonOdds = $bets->where('result', 'won')->max('global_odds');
            $smallestWonOdds = $bets->where('result', 'won')->min('global_odds');
            
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
                    'biggest_won_odds' => $biggestWonOdds ? round($biggestWonOdds, 3) : null,
                    'smallest_won_odds' => $smallestWonOdds ? round($smallestWonOdds, 3) : null,
                    'biggest_profit' => $biggestProfit > 0 ? round($biggestProfit, 2) : null,
                    'biggest_loss' => $biggestLoss < 0 ? round(abs($biggestLoss), 2) : null,
                    'max_win_streak' => $streaks['max_win_streak'],
                    'max_lose_streak' => $streaks['max_lose_streak'],
                    'current_win_streak' => $streaks['current_win_streak'],
                    'current_lose_streak' => $streaks['current_lose_streak']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur detailedStats: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

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

        // Filtrer seulement les paris avec résultat défini (won/lost)
        $settledBets = $bets->whereIn('result', ['won', 'lost']);

        foreach ($settledBets as $bet) {
            if ($bet->result === 'won') {
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



    // ... autres méthodes

    /**
     * Récupérer l'évolution du capital
     */
    public function capitalEvolution(Request $request): JsonResponse
    {
        try {
            // Capital initial paramétrable (par défaut 1000)
            $initialCapital = (float) ($request->get('initial_capital', 1000));

            // Base query + filtres (aligné avec index/stats)
            $query = Bet::query();

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

                $labels[] = Carbon::parse($ymd)->format('d/m');
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
            Log::error('Erreur capitalEvolution: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

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
        if ($bet->result === 'won') {
            return (float) (($bet->stake * $bet->global_odds) - $bet->stake);
        } elseif ($bet->result === 'lost') {
            return (float) (-$bet->stake);
        } elseif ($bet->result === 'void') {
            return 0.0;
        }
        return 0.0; // pending ou null
    }

    /**
     * Récupérer les options de filtres
     */
    public function filterOptions(): JsonResponse
    {
        $sports = Sport::select('name')
            ->distinct()
            ->pluck('name')
            ->filter()
            ->map(function($sport) {
                return ['label' => ucfirst($sport), 'value' => $sport];
            })
            ->values();

        $betTypes = Bet::select('bet_code')
            ->distinct()
            ->pluck('bet_code')
            ->filter()
            ->map(function($type) {
                return ['label' => ucfirst($type), 'value' => $type];
            })
            ->values();

        $bookmakers = Bet::select('bet_code')
            ->distinct()
            ->pluck('bet_code')
            ->filter()
            ->map(function($bookmaker) {
                return ['label' => ucfirst($bookmaker), 'value' => $bookmaker];
            })
            ->values();

        $tipsters = Bet::select('bet_code')
            ->distinct()
            ->pluck('bet_code')
            ->filter()
            ->map(function($tipster) {
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
        // Validation des données principales du pari
        $validator = Validator::make($request->all(), [
            'bet_date' => 'required|date',
            'global_odds' => 'required|numeric|min:1',
            'bet_code' => 'required|string|max:256',
            'result' => 'nullable|in:won,lost,void,pending',
            'stake' => 'required|numeric|min:0',
            'stake_type' => 'required|in:currency,percentage',
            // Validation du tableau d'événements
            'events' => 'required|array|min:1',
            'events.*.sport_id' => 'nullable|exists:sports,id',
            'events.*.country_id' => 'nullable|exists:countries,id',
            'events.*.league_id' => 'nullable|exists:leagues,id',
            'events.*.team1_id' => 'nullable|exists:teams,id',
            'events.*.team2_id' => 'nullable|exists:teams,id',
            'events.*.description' => 'required|string|max:500',
            'events.*.result' => 'nullable|in:won,lost,void,pending',
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
        $betData = [
            'bet_date' => $validatedData['bet_date'],
            'global_odds' => $validatedData['global_odds'],
            'bet_code' => $validatedData['bet_code'],
            'result' => $validatedData['result'] ?? 'pending',
            'stake' => $validatedData['stake']
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

        return response()->json([
            'success' => true,
            'message' => 'Pari créé avec succès',
            'data' => $bet->load(['sport', 'events.team1', 'events.team2', 'events.league.country'])
        ], 201);
    }

    /**
     * Récupérer un pari spécifique
     */
    public function show(Bet $bet): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $bet->load(['sport', 'events.team1', 'events.team2', 'events.league.country'])
        ]);
    }

    /**
     * Mettre à jour un pari
     */
    public function update(Request $request, Bet $bet): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_date' => 'sometimes|date',
            'global_odds' => 'sometimes|numeric|min:1',
            'bet_code' => 'sometimes|string|max:256',
            'result' => 'nullable|in:won,lost,void,pending',
            'sport_id' => 'sometimes|exists:sports,id',
            'stake' => 'sometimes|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $bet->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Pari mis à jour avec succès',
            'data' => $bet->load('sport')
        ]);
    }

    /**
     * Supprimer un pari
     */
    public function destroy(Bet $bet): JsonResponse
    {
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
