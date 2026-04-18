<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class AdminLeagueController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:admin,superadmin']);
    }

    /**
     * Retourne la liste paginée des ligues (admin) — 200 par page
     */
    public function index(Request $request): JsonResponse
    {
        $sportId = $request->get('sport_id');
        $countryId = $request->get('country_id');
        $search = $request->get('search');
        $perPage = (int) $request->get('per_page', 200);
        $perPage = min($perPage, 500); // cap max pour éviter les abus

        $query = League::with('country:id,name');

        if (!empty($sportId)) {
            $query->where('sport_id', $sportId);
        }

        if (!empty($countryId)) {
            $query->where('country_id', $countryId);
        }

        if (!empty($search)) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where('name', 'LIKE', '%' . $escaped . '%');
        }

        $paginated = $query->orderByDesc('priority')
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'img', 'country_id', 'sport_id', 'priority', 'nickname']);

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
     * Met à jour les priorités à partir d'un tableau {id, priority}
     */
    public function updatePriorities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'priorities' => 'required|array',
            'priorities.*.id' => 'required|integer|exists:leagues,id',
            'priorities.*.priority' => 'required|integer'
        ]);

        DB::beginTransaction();
        try {
            foreach ($data['priorities'] as $item) {
                League::where('id', $item['id'])->update(['priority' => (int)$item['priority']]);
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Priorités mises à jour']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur lors de la mise à jour', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Met à jour une ligue (nickname par exemple)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $league = League::findOrFail($id);

        $validated = $request->validate([
            'nickname' => 'nullable|string|max:191',
            'priority' => 'nullable|integer'
        ]);

        if (array_key_exists('nickname', $validated)) {
            $league->nickname = $validated['nickname'];
        }

        if (array_key_exists('priority', $validated)) {
            $league->priority = (int)$validated['priority'];
        }

        $league->save();

        return response()->json(['success' => true, 'league' => $league]);
    }

    /**
     * Supprime une ligue (admin)
     * Empêche la suppression si des équipes sont liées via la table pivot `league_team`.
     * Supprime les assets de logo associés (disk `public` / `league_logos/`).
     */
    public function destroy($id): JsonResponse
    {
        $league = League::find($id);
        if (!$league) {
            return response()->json(['success' => false, 'message' => 'Ligue introuvable'], 404);
        }

        // Si des équipes sont liées via le pivot, empêcher la suppression
        $hasTeams = $league->teams()->exists();
        if ($hasTeams) {
            return response()->json(['success' => false, 'message' => 'Impossible de supprimer : des équipes sont liées à cette ligue'], 409);
        }

        // Si des événements sont liés, empêcher la suppression
        $hasEvents = $league->events()->exists();
        if ($hasEvents) {
            return response()->json(['success' => false, 'message' => 'Impossible de supprimer : des événements sont liés à cette ligue'], 409);
        }

        // Supprimer les fichiers de logo (si présent)
        try {
            if (!empty($league->img)) {
                $img = $league->img;
                $base = pathinfo($img, PATHINFO_FILENAME);
                $ext = pathinfo($img, PATHINFO_EXTENSION);

                $candidates = [];
                if ($ext) {
                    $candidates[] = "league_logos/{$img}";
                    $candidates[] = "league_logos/{$base}-dark.{$ext}";
                    $candidates[] = "league_logos/{$base}.{$ext}";
                } else {
                    $candidates[] = "league_logos/{$img}.png";
                    $candidates[] = "league_logos/{$img}-dark.png";
                    $candidates[] = "league_logos/{$img}.webp";
                }

                // variantes courantes
                $candidates[] = "league_logos/{$base}-dark.png";
                $candidates[] = "league_logos/{$base}.png";
                $candidates[] = "league_logos/{$base}-dark.webp";
                $candidates[] = "league_logos/{$base}.webp";

                // Add candidates based on league id as well (common usage from frontend)
                $candidates[] = "league_logos/{$league->id}.png";
                $candidates[] = "league_logos/{$league->id}-dark.png";
                $candidates[] = "league_logos/{$league->id}.webp";
                $candidates[] = "league_logos/{$league->id}-dark.webp";
                $candidates[] = "league_logos/{$league->id}.svg";

                foreach (array_unique($candidates) as $path) {
                    try {
                        if (Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                        }
                    } catch (\Exception $e) {
                        // ne pas bloquer la suppression si un fichier ne peut être supprimé
                        logger()->warning('Impossible de supprimer le fichier d\'asset', ['path' => $path, 'error' => $e->getMessage()]);
                    }
                }
            }

            $league->delete();
            return response()->json(['success' => true, 'message' => 'Ligue supprimée']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lors de la suppression', 'error' => $e->getMessage()], 500);
        }
    }
}
