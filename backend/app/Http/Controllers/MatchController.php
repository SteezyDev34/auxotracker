<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MatchController extends Controller
{
    /**
     * Store a newly created match in storage or update existing by event_id.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id'               => 'required|integer',
            'team_1_sofascore_id'    => 'nullable|integer',
            'team_2_sofascore_id'    => 'nullable|integer',
            'match_start_date'       => 'nullable|date',
            'match_start_time'       => 'nullable',
            'tournament_sofascore_id' => 'nullable|integer',
            'sofascore_link'         => 'nullable|string',
        ]);

        $match = MatchModel::updateOrCreate([
            'event_id' => $data['event_id']
        ], $data);

        return response()->json($match, 201);
    }

    /**
     * Rechercher le lien Sofascore d'un match de tennis par date + noms des deux joueurs.
     *
     * GET /api/matches/tennis/link?date=2026-05-07&team1=Alcaraz&team2=Djokovic
     *
     * - date  : (optionnel) format Y-m-d, défaut = aujourd'hui
     * - team1 : nom (partiel) du joueur 1
     * - team2 : nom (partiel) du joueur 2
     *
     * Utilise le même pattern que SportController::searchTeamsBySport :
     * leftJoinSub priority + LIKE sur name/nickname/short_name, sport tennis = 5.
     */
    public function findTennisLink(Request $request): JsonResponse
    {
        $request->validate([
            'team1' => 'required|string|min:2',
            'team2' => 'required|string|min:2',
            'date'  => 'nullable|date_format:Y-m-d',
        ]);

        $date  = $request->get('date', now()->format('Y-m-d'));
        $name1 = trim($request->get('team1'));
        $name2 = trim($request->get('team2'));

        $team1 = $this->searchTeamByName($name1);
        $team2 = $this->searchTeamByName($name2);

        if (!$team1 || !$team2) {
            return response()->json([
                'success'     => false,
                'message'     => 'Un ou plusieurs joueurs introuvables.',
                'team1_found' => $team1 ? ['name' => $team1->name, 'sofascore_id' => $team1->sofascore_id] : null,
                'team2_found' => $team2 ? ['name' => $team2->name, 'sofascore_id' => $team2->sofascore_id] : null,
            ], 404);
        }

        $id1 = $team1->sofascore_id;
        $id2 = $team2->sofascore_id;

        // Chercher le match dans les deux sens (équipe 1 ↔ équipe 2)
        $match = MatchModel::where('match_start_date', $date)
            ->where(function ($q) use ($id1, $id2) {
                $q->where(function ($q) use ($id1, $id2) {
                    $q->where('team_1_sofascore_id', $id1)
                        ->where('team_2_sofascore_id', $id2);
                })->orWhere(function ($q) use ($id1, $id2) {
                    $q->where('team_1_sofascore_id', $id2)
                        ->where('team_2_sofascore_id', $id1);
                });
            })
            ->first();

        if (!$match) {
            return response()->json([
                'success'       => false,
                'message'       => "Aucun match trouvé le {$date} entre {$team1->name} et {$team2->name}.",
                'team1'         => ['name' => $team1->name, 'sofascore_id' => $id1],
                'team2'         => ['name' => $team2->name, 'sofascore_id' => $id2],
                'date_searched' => $date,
            ], 404);
        }

        return response()->json([
            'success'        => true,
            'sofascore_link' => $match->sofascore_link,
            'match_start'    => $match->match_start_date . ' ' . $match->match_start_time,
            'event_id'       => $match->event_id,
            'team1'          => ['name' => $team1->name, 'sofascore_id' => $id1],
            'team2'          => ['name' => $team2->name, 'sofascore_id' => $id2],
        ]);
    }

    /**
     * Recherche interne d'un joueur/équipe tennis par nom partiel.
     * Reproduit le pattern de SportController::searchTeamsBySport :
     *   - leftJoinSub sur la priorité max de ligue (sport_id = 5)
     *   - LIKE sur name, nickname, short_name
     *   - tri : priorité DESC → exact (3) > startsWith (2) > contains (1) → alphabétique
     */
    private function searchTeamByName(string $search): ?Team
    {
        $sportId     = 5; // Tennis
        $searchLower = mb_strtolower($search);
        $searchStart = $searchLower . '%';

        $sub = DB::table('league_team')
            ->join('leagues', 'leagues.id', '=', 'league_team.league_id')
            ->where('leagues.sport_id', $sportId)
            ->select('league_team.team_id', DB::raw('MAX(leagues.priority) as max_priority'))
            ->groupBy('league_team.team_id');

        return Team::leftJoinSub($sub, 'lp', fn($j) => $j->on('teams.id', '=', 'lp.team_id'))
            ->whereNotNull('lp.team_id')
            ->where(function ($q) use ($search) {
                $q->where('teams.name',       'LIKE', '%' . $search . '%')
                    ->orWhere('teams.nickname',   'LIKE', '%' . $search . '%')
                    ->orWhere('teams.short_name', 'LIKE', '%' . $search . '%');
            })
            ->orderByDesc('lp.max_priority')
            ->orderByRaw(
                "CASE
                    WHEN LOWER(teams.name) = ? OR LOWER(teams.nickname) = ? OR LOWER(teams.short_name) = ? THEN 3
                    WHEN LOWER(teams.name) LIKE ? OR LOWER(teams.nickname) LIKE ? OR LOWER(teams.short_name) LIKE ? THEN 2
                    ELSE 1
                END DESC",
                [$searchLower, $searchLower, $searchLower, $searchStart, $searchStart, $searchStart]
            )
            ->orderBy('teams.name')
            ->select('teams.id', 'teams.name', 'teams.nickname', 'teams.sofascore_id')
            ->first();
    }
}
