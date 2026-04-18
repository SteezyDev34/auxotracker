<?php

namespace App\Http\Controllers;

use App\Models\Sport;
use App\Models\League;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
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
            // Utiliser la table pivot `league_team` via la relation `leagues`
            $teams = Team::whereHas('leagues', function ($q) use ($leagueId) {
                $q->where('leagues.id', $leagueId);
            })->orderBy('name')
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
            $priorityOnly = $request->get('priority_only', false); // Filtrer uniquement les équipes prioritaires

            // Détecter si le sport est le tennis (par slug)
            $sportModel = Sport::find($sportId);
            $isTennis = $sportModel && strtolower($sportModel->slug) === 'tennis';

            // Build base subquery to compute max priority per team within the sport
            $baseSub = DB::table('league_team')
                ->join('leagues', 'leagues.id', '=', 'league_team.league_id')
                ->where('leagues.sport_id', $sportId)
                ->select('league_team.team_id', DB::raw('MAX(leagues.priority) as max_priority'))
                ->groupBy('league_team.team_id');

            // Start building main query
            $query = Team::leftJoinSub($baseSub, 'lp', function ($join) {
                $join->on('teams.id', '=', 'lp.team_id');
            })
            // Filtrer uniquement les équipes qui ont au moins une ligue dans ce sport
            ->whereNotNull('lp.team_id')
            ->with(['leagues:id,name']);

            // Si priority_only = true, ne retourner que les équipes avec priority > 0
            if ($priorityOnly) {
                $query->where('lp.max_priority', '>', 0);
            }

            // Apply country filter: require a league in that country (works for tennis and other sports)
            if (!empty($countryId)) {
                $query->whereExists(function ($q) use ($countryId) {
                    $q->select(DB::raw(1))
                        ->from('league_team')
                        ->join('leagues', 'leagues.id', '=', 'league_team.league_id')
                        ->whereRaw('league_team.team_id = teams.id')
                        ->where('leagues.country_id', $countryId);
                });
            }

            // Apply search filter and advanced ordering
            if (!empty($search)) {
                // Recherche LIKE dans name, nickname et short_name
                $query->where(function ($q) use ($search) {
                    $q->where('teams.name', 'LIKE', '%' . $search . '%')
                        ->orWhere('teams.nickname', 'LIKE', '%' . $search . '%')
                        ->orWhere('teams.short_name', 'LIKE', '%' . $search . '%');
                });

                $searchLower = mb_strtolower($search);
                $searchStartsWith = $searchLower . '%';

                // Ordre de tri : 
                // 1) Priorité de ligue (DESC)
                // 2) Correspondance exacte dans name, nickname OU short_name (score 3)
                // 3) Commence par le terme recherché (LIKE 'terme%') (score 2)
                // 4) Contient le terme (LIKE '%terme%') (score 1 - par défaut)
                // 5) Ordre alphabétique
                $query->orderByDesc('lp.max_priority')
                      ->orderByRaw(
                          "CASE 
                            WHEN LOWER(teams.name) = ? OR LOWER(teams.nickname) = ? OR LOWER(teams.short_name) = ? THEN 3
                            WHEN LOWER(teams.name) LIKE ? OR LOWER(teams.nickname) LIKE ? OR LOWER(teams.short_name) LIKE ? THEN 2
                            ELSE 1
                          END DESC",
                          [$searchLower, $searchLower, $searchLower, $searchStartsWith, $searchStartsWith, $searchStartsWith]
                      )
                      ->orderBy('teams.name');
            } else {
                // No search term: league priority first, then alphabetical
                $query->orderByDesc('lp.max_priority')
                      ->orderBy('teams.name');
            }

            // Appliquer le filtre par ligue si fourni (garde compatibilité avec l'ancienne API)
            // Filtrer via la table pivot `league_team` pour tenir compte des équipes appartenant à plusieurs ligues
            if (!empty($leagueId)) {
                $query->whereExists(function ($q) use ($leagueId) {
                    $q->select(DB::raw(1))
                        ->from('league_team')
                        ->whereRaw('league_team.team_id = teams.id')
                        ->where('league_team.league_id', $leagueId);
                });
            }

            // Calculer l'offset
            $offset = ($page - 1) * $limit;

            // Récupérer le total pour la pagination
            $total = $query->count();

            // Récupérer les résultats avec pagination
            $teams = $query->skip($offset)
                ->take($limit)
                ->get(['id', 'name', 'nickname', 'short_name', 'img', 'league_id', 'sofascore_id']);

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
