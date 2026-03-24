<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InvestmentController extends Controller
{
    /**
     * Récupérer tous les investissements de l'utilisateur connecté
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Investment::where('user_id', $user->id);

        // Appliquer les filtres
        if ($request->has('bankroll_id')) {
            $query->where('bankroll_id', $request->get('bankroll_id'));
        }

        if ($request->has('statut')) {
            $query->where('statut', $request->get('statut'));
        }

        $investments = $query->orderBy('date_investissement', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $investments,
            'total' => $investments->count(),
        ]);
    }

    /**
     * Récupérer les statistiques des investissements pour l'utilisateur connecté
     */
    public function stats(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Investment::where('user_id', $user->id);

        // Appliquer les filtres
        if ($request->has('bankroll_id')) {
            $query->where('bankroll_id', $request->get('bankroll_id'));
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_investissements,
            SUM(CASE WHEN statut = "actif" THEN montant_investi ELSE 0 END) as total_actif,
            SUM(CASE WHEN statut = "inactif" THEN montant_investi ELSE 0 END) as total_inactif,
            SUM(CASE WHEN statut = "retire" THEN montant_investi ELSE 0 END) as total_retire,
            SUM(montant_investi) as total_investi,
            AVG(montant_investi) as moyenne_investissement,
            MAX(montant_investi) as max_investissement,
            MIN(montant_investi) as min_investissement,
            MAX(date_investissement) as dernier_investissement,
            MIN(date_investissement) as premier_investissement
        ')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total_investissements' => $stats->total_investissements,
                'total_actif' => round($stats->total_actif, 2),
                'total_inactif' => round($stats->total_inactif, 2),
                'total_retire' => round($stats->total_retire, 2),
                'total_investi' => round($stats->total_investi, 2),
                'moyenne_investissement' => round($stats->moyenne_investissement, 2),
                'max_investissement' => round($stats->max_investissement, 2),
                'min_investissement' => round($stats->min_investissement, 2),
                'dernier_investissement' => $stats->dernier_investissement,
                'premier_investissement' => $stats->premier_investissement,
            ]
        ]);
    }

    /**
     * Créer un nouvel investissement
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Seuls les admins peuvent créer des investissements pour d'autres utilisateurs
        if ($request->has('user_id') && $request->user_id != $user->id) {
            if (!in_array($user->role, ['admin', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Accès non autorisé.'
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'sometimes|exists:users,id',
            'bankroll_id' => 'nullable|integer',
            'montant_investi' => 'required|numeric|min:0.01',
            'date_investissement' => 'required|date',
            'statut' => 'sometimes|in:actif,inactif,retire',
            'commentaire' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Si pas d'user_id spécifié, utiliser l'utilisateur connecté
        if (!isset($validated['user_id'])) {
            $validated['user_id'] = $user->id;
        }

        // Statut par défaut
        if (!isset($validated['statut'])) {
            $validated['statut'] = 'actif';
        }

        $investment = Investment::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Investissement créé avec succès',
            'data' => $investment->load('user')
        ], 201);
    }

    /**
     * Récupérer un investissement spécifique
     */
    public function show(Investment $investment): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que l'utilisateur peut voir cet investissement
        if ($user->role !== 'admin' && $user->role !== 'superadmin' && $investment->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $investment->load('user')
        ]);
    }

    /**
     * Mettre à jour un investissement
     */
    public function update(Request $request, Investment $investment): JsonResponse
    {
        $user = auth()->user();

        // Seuls les admins peuvent modifier des investissements
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'montant_investi' => 'sometimes|numeric|min:0.01',
            'date_investissement' => 'sometimes|date',
            'statut' => 'sometimes|in:actif,inactif,retire',
            'commentaire' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $investment->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Investissement mis à jour avec succès',
            'data' => $investment->load('user')
        ]);
    }

    /**
     * Supprimer un investissement
     */
    public function destroy(Investment $investment): JsonResponse
    {
        $user = auth()->user();

        // Seuls les admins peuvent supprimer des investissements
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé.'
            ], 403);
        }

        $investment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Investissement supprimé avec succès'
        ]);
    }
}
