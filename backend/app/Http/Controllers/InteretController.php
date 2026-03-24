<?php

namespace App\Http\Controllers;

use App\Models\Interet;
use App\Models\Investment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InteretController extends Controller
{
    /**
     * Récupérer tous les intérêts de l'utilisateur connecté (investor uniquement)
     */
    public function index(Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur est un investisseur
        $user = auth()->user();

        if ($user->role !== 'investor') {
            return response()->json([
                'success' => false,
                'error' => 'Accès réservé aux investisseurs.'
            ], 403);
        }

        $query = Interet::where('user_id', $user->id);

        // Appliquer les filtres
        if ($request->has('period')) {
            $query->byPeriod($request->get('period'));
        }

        if ($request->has('moyen_paiement')) {
            $query->byMoyenPaiement($request->get('moyen_paiement'));
        }

        // Filtres de date supplémentaires
        if ($request->has('start_date')) {
            $query->where('date_versement', '>=', Carbon::parse($request->start_date));
        }
        if ($request->has('end_date')) {
            $query->where('date_versement', '<=', Carbon::parse($request->end_date));
        }

        $interets = $query->orderBy('date_versement', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $interets,
            'total' => $interets->count(),
        ]);
    }

    /**
     * Récupérer les statistiques des intérêts pour l'utilisateur connecté
     */
    public function stats(Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur est un investisseur
        $user = auth()->user();

        if ($user->role !== 'investor') {
            return response()->json([
                'success' => false,
                'error' => 'Accès réservé aux investisseurs.'
            ], 403);
        }

        $query = Interet::where('user_id', $user->id);

        // Appliquer les filtres
        if ($request->has('period')) {
            $query->byPeriod($request->get('period'));
        }

        if ($request->has('moyen_paiement')) {
            $query->byMoyenPaiement($request->get('moyen_paiement'));
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_versements,
            SUM(montant_interet) as total_interets,
            AVG(montant_interet) as moyenne_interet,
            MAX(montant_interet) as max_interet,
            MIN(montant_interet) as min_interet,
            MAX(date_versement) as dernier_versement,
            MIN(date_versement) as premier_versement
        ')->first();

        // Calculer le montant total investi actuel de l'utilisateur
        $totalInvesti = Investment::getTotalInvestedAmount($user->id);

        // Calculer le taux de rendement global
        $tauxRendement = $totalInvesti > 0 ?
            ($stats->total_interets / $totalInvesti) * 100 : 0;

        // Statistiques par moyen de paiement
        $statsByMoyenPaiement = Interet::where('user_id', $user->id)
            ->selectRaw('
                moyen_paiement,
                COUNT(*) as nombre_versements,
                SUM(montant_interet) as total_interet
            ')
            ->groupBy('moyen_paiement')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_versements' => $stats->total_versements,
                'total_investi' => round($totalInvesti, 2),
                'total_interets' => round($stats->total_interets, 2),
                'moyenne_interet' => round($stats->moyenne_interet, 2),
                'max_interet' => round($stats->max_interet, 2),
                'min_interet' => round($stats->min_interet, 2),
                'taux_rendement' => round($tauxRendement, 2),
                'dernier_versement' => $stats->dernier_versement,
                'premier_versement' => $stats->premier_versement,
                'stats_par_moyen' => $statsByMoyenPaiement->map(function ($stat) {
                    return [
                        'moyen_paiement' => $stat->moyen_paiement,
                        'libelle' => match ($stat->moyen_paiement) {
                            'paypal' => 'PayPal',
                            'virement_bancaire' => 'Virement bancaire',
                            'autre' => 'Autre',
                            default => 'Non spécifié'
                        },
                        'nombre_versements' => $stat->nombre_versements,
                        'total_interet' => round($stat->total_interet, 2)
                    ];
                })
            ]
        ]);
    }

    /**
     * Récupérer l'évolution des intérêts dans le temps
     */
    public function evolution(Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur est un investisseur
        $user = auth()->user();

        if ($user->role !== 'investor') {
            return response()->json([
                'success' => false,
                'error' => 'Accès réservé aux investisseurs.'
            ], 403);
        }

        try {
            $query = Interet::where('user_id', $user->id);

            // Appliquer les filtres
            if ($request->has('period')) {
                $query->byPeriod($request->get('period'));
            }

            if ($request->has('start_date')) {
                $query->where('date_versement', '>=', Carbon::parse($request->start_date));
            }
            if ($request->has('end_date')) {
                $query->where('date_versement', '<=', Carbon::parse($request->end_date));
            }

            // Récupérer les intérêts ordonnés par date
            $interets = $query
                ->orderBy('date_versement', 'asc')
                ->get(['date_versement', 'montant_interet', 'montant_total_investi_date_versement']);

            // Si aucun intérêt trouvé
            if ($interets->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'labels' => [],
                    'cumulative_data' => [],
                    'total_cumule' => 0
                ]);
            }

            $labels = [];
            $monthlyData = [];
            $cumulativeData = [];
            $totalCumule = 0;

            foreach ($interets as $interet) {
                $date = Carbon::parse($interet->date_versement);
                $label = $date->format('M Y'); // Nov 2024

                $labels[] = $label;
                $monthlyData[] = round($interet->montant_interet, 2);

                $totalCumule += $interet->montant_interet;
                $cumulativeData[] = round($totalCumule, 2);
            }

            return response()->json([
                'success' => true,
                'data' => $monthlyData,
                'labels' => $labels,
                'cumulative_data' => $cumulativeData,
                'total_cumule' => round($totalCumule, 2)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur evolution intérêts: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Une erreur est survenue lors du calcul de l\'évolution des intérêts.',
            ], 500);
        }
    }

    /**
     * Créer un nouvel intérêt (admin uniquement)
     */
    public function store(Request $request): JsonResponse
    {
        // Seuls les admins peuvent créer des intérêts
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'bankroll_id' => 'nullable|integer',
            'montant_interet' => 'required|numeric|min:0',
            'taux_interet' => 'nullable|numeric|min:0|max:100',
            'moyen_paiement' => 'required|in:paypal,virement_bancaire,autre',
            'detail_paiement' => 'nullable|string|max:255',
            'date_versement' => 'required|date',
            'commentaire' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Calculer le montant total investi à la date de versement
        $montantTotalInvesti = Investment::getTotalInvestedAmountAtDate(
            $validated['user_id'],
            $validated['date_versement'],
            $validated['bankroll_id'] ?? null
        );

        $validated['montant_total_investi_date_versement'] = $montantTotalInvesti;

        // Si le taux n'est pas spécifié, utiliser le taux par défaut de 10%
        if (!isset($validated['taux_interet'])) {
            $validated['taux_interet'] = 10.00;
        }

        $interet = Interet::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Intérêt créé avec succès',
            'data' => $interet->load('user')
        ], 201);
    }

    /**
     * Récupérer un intérêt spécifique
     */
    public function show(Interet $interet): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que l'utilisateur peut voir cet intérêt
        if ($user->role === 'investor' && $interet->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $interet->load('user')
        ]);
    }

    /**
     * Mettre à jour un intérêt (admin uniquement)
     */
    public function update(Request $request, Interet $interet): JsonResponse
    {
        // Seuls les admins peuvent modifier des intérêts
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'montant_interet' => 'sometimes|numeric|min:0',
            'taux_interet' => 'sometimes|numeric|min:0|max:100',
            'moyen_paiement' => 'sometimes|in:paypal,virement_bancaire,autre',
            'detail_paiement' => 'nullable|string|max:255',
            'date_versement' => 'sometimes|date',
            'commentaire' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Si la date de versement change, recalculer le montant total investi
        if (isset($validated['date_versement'])) {
            $montantTotalInvesti = Investment::getTotalInvestedAmountAtDate(
                $interet->user_id,
                $validated['date_versement'],
                $interet->bankroll_id
            );
            $validated['montant_total_investi_date_versement'] = $montantTotalInvesti;
        }

        $interet->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Intérêt mis à jour avec succès',
            'data' => $interet->load('user')
        ]);
    }

    /**
     * Supprimer un intérêt (admin uniquement)
     */
    public function destroy(Interet $interet): JsonResponse
    {
        // Seuls les admins peuvent supprimer des intérêts
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé.'
            ], 403);
        }

        $interet->delete();

        return response()->json([
            'success' => true,
            'message' => 'Intérêt supprimé avec succès'
        ]);
    }

    /**
     * Récupérer les options de filtres pour les intérêts
     */
    public function filterOptions(): JsonResponse
    {
        $user = auth()->user();

        if ($user->role !== 'investor') {
            return response()->json([
                'success' => false,
                'error' => 'Accès réservé aux investisseurs.'
            ], 403);
        }

        $moyensPaiement = Interet::where('user_id', $user->id)
            ->select('moyen_paiement')
            ->distinct()
            ->pluck('moyen_paiement')
            ->map(function ($moyen) {
                return [
                    'label' => match ($moyen) {
                        'paypal' => 'PayPal',
                        'virement_bancaire' => 'Virement bancaire',
                        'autre' => 'Autre',
                        default => 'Non spécifié'
                    },
                    'value' => $moyen
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'moyens_paiement' => $moyensPaiement,
                'periodes' => [
                    ['label' => '3m', 'value' => '3m'],
                    ['label' => '6m', 'value' => '6m'],
                    ['label' => '1an', 'value' => '1an'],
                    ['label' => 'Tout', 'value' => 'all']
                ]
            ]
        ]);
    }

    /**
     * Test d'authentification pour diagnostic
     */
    public function authTest(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilisateur non authentifié',
                    'guard' => config('auth.defaults.guard'),
                    'token_present' => request()->bearerToken() ? true : false,
                    'token_preview' => request()->bearerToken() ? substr(request()->bearerToken(), 0, 20) . '...' : null
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'Authentification réussie',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'guard' => config('auth.defaults.guard'),
                'token_present' => request()->bearerToken() ? true : false,
                'token_preview' => request()->bearerToken() ? substr(request()->bearerToken(), 0, 20) . '...' : null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du test d\'authentification',
                'message' => $e->getMessage(),
                'guard' => config('auth.defaults.guard'),
                'token_present' => request()->bearerToken() ? true : false
            ], 500);
        }
    }
}
