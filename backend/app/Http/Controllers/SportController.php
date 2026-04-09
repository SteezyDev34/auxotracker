<?php

namespace App\Http\Controllers;

use App\Models\Sport;
use App\Models\League;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SportController extends Controller
{
    /**
     * Récupérer tous les sports
     */
    public function index(): JsonResponse
    {
        try {
            $sports = Sport::all();

            return response()->json([
                'success' => true,
                'data' => $sports
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des sports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les ligues d'un sport spécifique
     */
    public function getLeagues(Request $request, $sportId): JsonResponse
    {
        try {
            $leagues = League::where('sport_id', $sportId)
                ->with('country:id,name') // Charger la relation country
                ->orderByDesc('priority')
                ->orderBy('name')
                ->get(['id', 'name', 'img', 'country_id', 'priority']); // Inclure priority

            return response()->json([
                'success' => true,
                'data' => $leagues
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des ligues',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les équipes d'une ligue spécifique
     */
    public function getTeams(Request $request, $leagueId): JsonResponse
    {
        try {
            $teams = Team::where('league_id', $leagueId)
                ->orderBy('name')
                ->get(['id', 'name', 'nickname', 'img']);

            return response()->json([
                'success' => true,
                'data' => $teams
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des équipes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les équipes d'un sport spécifique (toutes ligues confondues)
     */
    public function getTeamsBySport(Request $request, $sportId): JsonResponse
    {
        try {
            // Build subquery to compute max priority per team for the given sport
            $sub = DB::table('league_team')
                ->join('leagues', 'leagues.id', '=', 'league_team.league_id')
                ->where('leagues.sport_id', $sportId)
                ->select('league_team.team_id', DB::raw('MAX(leagues.priority) as max_priority'))
                ->groupBy('league_team.team_id');

            $teams = Team::leftJoinSub($sub, 'lp', function ($join) {
                $join->on('teams.id', '=', 'lp.team_id');
            })
                ->with(['leagues:id,name'])
                ->orderByDesc('lp.max_priority')
                ->orderBy('teams.name')
                ->get(['teams.id', 'teams.name', 'teams.nickname', 'teams.img']);

            return response()->json([
                'success' => true,
                'data' => $teams
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des équipes par sport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechercher les ligues d'un sport avec pagination
     */
    public function searchLeaguesBySport(Request $request, $sportId): JsonResponse
    {
        try {
            $search = $request->get('search', '');
            $page = (int) $request->get('page', 1);
            $limit = min((int) $request->get('limit', 30), 50); // Limiter à 50 max, défaut 30
            $countryId = $request->get('country_id'); // Filtre optionnel par pays

            $query = League::where('sport_id', $sportId)
                ->with('country:id,name') // Charger la relation country
                ->orderBy('name');

            // Appliquer le filtre de recherche si fourni
            if (!empty($search)) {
                $query->where('name', 'LIKE', '%' . $search . '%');
            }

            // Appliquer le filtre par pays si fourni
            if (!empty($countryId)) {
                $query->where('country_id', $countryId);
            }

            // Calculer l'offset
            $offset = ($page - 1) * $limit;

            // Récupérer le total pour la pagination
            $total = $query->count();

            // Récupérer les résultats avec pagination
            $leagues = $query->skip($offset)
                ->take($limit)
                ->get(['id', 'name', 'img', 'country_id']); // Inclure country_id

            // Déterminer s'il y a plus de résultats
            $hasMore = ($offset + $limit) < $total;

            return response()->json([
                'success' => true,
                'data' => $leagues,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'has_more' => $hasMore
                ],
                'hasMore' => $hasMore // Pour compatibilité avec le frontend
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche des ligues',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechercher les équipes d'un sport avec pagination et filtrage par ligue et pays
     */
    public function searchTeamsBySport(Request $request, $sportId): JsonResponse
    {
        try {
            $search = $request->get('search', '');
            $page = (int) $request->get('page', 1);
            $limit = min((int) $request->get('limit', 200), 200); // Limiter à 50 max, défaut 30
            $leagueId = $request->get('league_id'); // Filtre optionnel par ligue
            $countryId = $request->get('country_id'); // Filtre optionnel par pays

            // Build base query using pivot to compute max priority per team within the sport
            $baseSub = DB::table('league_team')
                ->join('leagues', 'leagues.id', '=', 'league_team.league_id')
                ->where('leagues.sport_id', $sportId)
                ->select('league_team.team_id', DB::raw('MAX(leagues.priority) as max_priority'))
                ->groupBy('league_team.team_id');

            $query = Team::leftJoinSub($baseSub, 'lp', function ($join) {
                $join->on('teams.id', '=', 'lp.team_id');
            })
                ->with(['leagues:id,name'])
                ->orderByDesc('lp.max_priority')
                ->orderBy('teams.name');

            // Apply country filter by ensuring team has at least one league in that country
            if (!empty($countryId)) {
                $query->whereExists(function ($q) use ($countryId) {
                    $q->select(DB::raw(1))
                        ->from('league_team')
                        ->join('leagues', 'leagues.id', '=', 'league_team.league_id')
                        ->whereRaw('league_team.team_id = teams.id')
                        ->where('leagues.country_id', $countryId);
                });
            }

            // Appliquer le filtre de recherche si fourni
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('nickname', 'LIKE', '%' . $search . '%');
                });
            }

            // Appliquer le filtre par ligue si fourni
            if (!empty($leagueId)) {
                $query->where('league_id', $leagueId);
            }

            // Calculer l'offset
            $offset = ($page - 1) * $limit;

            // Récupérer le total pour la pagination
            $total = $query->count();

            // Récupérer les résultats avec pagination
            $teams = $query->skip($offset)
                ->take($limit)
                ->get(['id', 'name', 'nickname', 'img', 'league_id', 'sofascore_id']);

            // Déterminer s'il y a plus de résultats
            $hasMore = ($offset + $limit) < $total;

            return response()->json([
                'success' => true,
                'data' => $teams,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'has_more' => $hasMore
                ],
                'hasMore' => $hasMore // Pour compatibilité avec le frontend
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche des équipes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les pays qui ont des ligues pour un sport spécifique
     */
    public function getCountriesBySport(Request $request, $sportId): JsonResponse
    {
        try {
            $search = $request->get('search', '');

            $query = \App\Models\Country::whereHas('leagues', function ($q) use ($sportId) {
                $q->where('sport_id', $sportId);
            })
                ->select('id', 'name', 'code', 'slug')
                ->orderBy('name');

            // Appliquer le filtre de recherche si fourni
            if (!empty($search)) {
                $query->where('name', 'LIKE', '%' . $search . '%');
            }

            $countries = $query->get();

            return response()->json([
                'success' => true,
                'data' => $countries
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des pays pour ce sport',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
