<?php

namespace App\Http\Controllers;

use App\Models\TeamSearchNotFound;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class TeamSearchNotFoundController extends Controller
{
    public function __construct()
    {
        // Middleware désactivé temporairement pour tests
        // $this->middleware(['auth:sanctum', 'role:superadmin']);
    }

    /**
     * Enregistrer un terme de recherche non trouvé
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search_term' => 'required|string|max:191',
            'sport_id' => 'nullable|integer|exists:sports,id',
        ]);

        $search = TeamSearchNotFound::create([
            'search_term' => $validated['search_term'],
            'sport_id' => $validated['sport_id'] ?? null,
            'user_id' => auth()->id(),
            'resolved' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $search
        ], 201);
    }

    /**
     * Récupérer la liste des recherches non trouvées
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 50);
        $perPage = min($perPage, 200);
        $resolved = $request->get('resolved', 'false');

        $query = TeamSearchNotFound::with(['sport:id,name', 'user:id,name', 'team:id,name'])
            ->orderByDesc('created_at');

        if ($resolved === 'false' || $resolved === false) {
            $query->where('resolved', false);
        } elseif ($resolved === 'true' || $resolved === true) {
            $query->where('resolved', true);
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Associer une recherche à une équipe existante et ajouter le terme au nickname
     */
    public function resolve(Request $request, $id): JsonResponse
    {
        $search = TeamSearchNotFound::findOrFail($id);

        $validated = $request->validate([
            'team_id' => 'required|integer|exists:teams,id',
        ]);

        $team = Team::findOrFail($validated['team_id']);

        DB::beginTransaction();
        try {
            // Ajouter le search_term au nickname de l'équipe (séparé par des virgules)
            $currentNickname = $team->nickname ?? '';
            $searchTerm = trim($search->search_term);

            // Éviter les doublons dans le nickname
            if (stripos($currentNickname, $searchTerm) === false) {
                if (!empty($currentNickname)) {
                    // Si nickname existe déjà, ajouter avec une virgule
                    $team->nickname = $currentNickname . ', ' . $searchTerm;
                } else {
                    // Si nickname vide, ajouter directement
                    $team->nickname = $searchTerm;
                }
                $team->save();
            }

            // Marquer la recherche comme résolue
            $search->team_id = $team->id;
            $search->resolved = true;
            $search->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Recherche associée avec succès',
                'data' => $search->load(['team', 'sport', 'user'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'association',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une recherche
     */
    public function destroy($id): JsonResponse
    {
        $search = TeamSearchNotFound::findOrFail($id);
        $search->delete();

        return response()->json([
            'success' => true,
            'message' => 'Recherche supprimée'
        ]);
    }
}
