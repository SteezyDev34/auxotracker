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
    /**
     * GET /api/matches/today?date=YYYY-MM-DD&sport_id=2
     * Retourne les matchs du jour avec équipes, ligue, pays, sport.
     */
    public function today(Request $request): JsonResponse
    {
        $date    = $request->get('date', now()->format('Y-m-d'));
        $sportId = $request->get('sport_id');
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = min(200, max(1, (int) $request->get('per_page', 50)));
        // Tri optionnel : datetime_asc|datetime_desc|score_desc|prob_15a_desc|
        // prob_30a_desc|prob_40a_desc (défaut : tri par ligue/heure habituel)
        $sort = $request->get('sort');

        $query = DB::table('matches as m')
            ->leftJoin('teams as t1', 't1.sofascore_id', '=', 'm.team_1_sofascore_id')
            ->leftJoin('teams as t2', 't2.sofascore_id', '=', 'm.team_2_sofascore_id')
            ->leftJoin('leagues as l', 'l.sofascore_id', '=', 'm.tournament_sofascore_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->leftJoin('sports as s', 's.id', '=', 'm.sport_id')
            ->leftJoin('tennis_match_tightness_scores as tts', 'tts.event_id', '=', 'm.event_id')
            ->where('m.match_start_date', $date);

        if ($sportId) {
            $query->where('m.sport_id', $sportId);
        }

        // value_bets=1 : ne garder que les matchs avec un edge suffisant sur au
        // moins un des marchés (15A/30A/40A + patterns de jeu) par rapport aux
        // cotes de référence, ET un échantillon fiable derrière. Un simple
        // edge > 0 capte trop de faux positifs statistiques quand la proba
        // moyenne d'un marché est juste collée au seuil de rentabilité de sa
        // cote de référence (ex : 30-0, où ~46% des matchs dépassent la cote
        // par pur bruit de variance plutôt qu'un vrai signal) — voir
        // MIN_VALUE_BET_EDGE côté frontend (TodayMatches.vue), même seuil ici
        // pour rester cohérent entre le badge 💰 et ce filtre.
        // Appliqué à $query (avant clonage) pour que $total/has_more restent
        // cohérents avec le filtre.
        if ($request->boolean('value_bets')) {
            $query->where('tts.sample_size', '>=', 15)
                ->where(function ($q) {
                    $q->where('tts.edge_15a', '>', 5)
                      ->orWhere('tts.edge_30a', '>', 5)
                      ->orWhere('tts.edge_40a', '>', 5)
                      ->orWhere('tts.edge_30love', '>', 5)
                      ->orWhere('tts.edge_g40_0', '>', 5)
                      ->orWhere('tts.edge_g40_15', '>', 5)
                      ->orWhere('tts.edge_g40_30', '>', 5)
                      ->orWhere('tts.edge_leads1', '>', 5)
                      ->orWhere('tts.edge_leads2', '>', 5)
                      ->orWhere('tts.edge_lost_serve1', '>', 5)
                      ->orWhere('tts.edge_lost_serve2', '>', 5);
                });
        }

        // Regrouper par ligue côté client : trier par ligue puis heure pour que
        // chaque page contienne des groupes complets autant que possible.
        $total = (clone $query)->count();

        $matchesQuery = (clone $query)
            ->select([
                'm.id', 'm.event_id', 'm.match_start_date', 'm.match_start_time',
                'm.sofascore_link', 'm.sport_id',
                't1.name as team1_name', 't1.img as team1_img', 't1.sofascore_id as team1_sofascore_id',
                't1.country_code as team1_country',
                't2.name as team2_name', 't2.img as team2_img', 't2.sofascore_id as team2_sofascore_id',
                't2.country_code as team2_country',
                'l.name as league_name', 'l.img as league_img', 'l.sofascore_id as league_sofascore_id',
                'c.id as country_id', 'c.name as country_name',
                's.name as sport_name', 's.img as sport_img',
                'tts.score as tightness_score', 'tts.breakdown as tightness_breakdown',
                'tts.prob_15a_set1', 'tts.prob_30a_set1', 'tts.prob_40a_set1',
                'tts.edge_15a', 'tts.edge_30a', 'tts.edge_40a',
                'tts.sample_size',
                'tts.prob_30love_set1', 'tts.prob_game_40_0', 'tts.prob_game_40_15', 'tts.prob_game_40_30',
                'tts.prob_server_loss_to_love',
                'tts.prob_team1_leads_15_0', 'tts.prob_team2_leads_15_0',
                'tts.edge_30love', 'tts.edge_g40_0', 'tts.edge_g40_15', 'tts.edge_g40_30',
                'tts.edge_leads1', 'tts.edge_leads2',
                'tts.prob_lost_serve1', 'tts.prob_lost_serve2',
                'tts.edge_lost_serve1', 'tts.edge_lost_serve2',
            ]);

        $sortMap = [
            'datetime_asc'  => [['m.match_start_date', 'asc'], ['m.match_start_time', 'asc']],
            'datetime_desc' => [['m.match_start_date', 'desc'], ['m.match_start_time', 'desc']],
            'score_desc'    => [['tts.score', 'desc']],
            'score_asc'     => [['tts.score', 'asc']],
            'prob_15a_desc' => [['tts.prob_15a_set1', 'desc']],
            'prob_30a_desc' => [['tts.prob_30a_set1', 'desc']],
            'prob_40a_desc' => [['tts.prob_40a_set1', 'desc']],
            'edge_15a_desc' => [['tts.edge_15a', 'desc']],
            'edge_30a_desc' => [['tts.edge_30a', 'desc']],
            'edge_40a_desc' => [['tts.edge_40a', 'desc']],
            'prob_30love_desc' => [['tts.prob_30love_set1', 'desc']],
            'prob_g40_0_desc' => [['tts.prob_game_40_0', 'desc']],
            'prob_g40_15_desc' => [['tts.prob_game_40_15', 'desc']],
            'prob_g40_30_desc' => [['tts.prob_game_40_30', 'desc']],
            'server_loss_desc' => [['tts.prob_server_loss_to_love', 'desc']],
        ];

        if ($sort === 'leads_15_0_desc') {
            // Asymétrique (un joueur par colonne) : on trie sur le meilleur
            // des deux, peu importe lequel des deux joueurs il concerne.
            $matchesQuery
                ->orderByRaw('GREATEST(COALESCE(tts.prob_team1_leads_15_0, 0), COALESCE(tts.prob_team2_leads_15_0, 0)) = 0')
                ->orderByRaw('GREATEST(COALESCE(tts.prob_team1_leads_15_0, 0), COALESCE(tts.prob_team2_leads_15_0, 0)) DESC')
                ->orderBy('m.match_start_time');
        } elseif ($sort === 'lost_serve_desc') {
            // Idem, asymétrique par joueur (dénominateur = jeux servis).
            $matchesQuery
                ->orderByRaw('GREATEST(COALESCE(tts.prob_lost_serve1, 0), COALESCE(tts.prob_lost_serve2, 0)) = 0')
                ->orderByRaw('GREATEST(COALESCE(tts.prob_lost_serve1, 0), COALESCE(tts.prob_lost_serve2, 0)) DESC')
                ->orderBy('m.match_start_time');
        } elseif ($sort && isset($sortMap[$sort])) {
            foreach ($sortMap[$sort] as [$column, $direction]) {
                $matchesQuery->orderByRaw("{$column} IS NULL")->orderBy($column, $direction);
            }
            $matchesQuery->orderBy('m.match_start_time');
        } else {
            // Tri par défaut, pour le tennis uniquement : Masters/Grand Chelem
            // d'abord, puis ATP, WTA, Challenger (WTA 125 assimilé, tier
            // équivalent côté WTA), ITF, UTR et le reste. `s.name != 'Tennis'`
            // renvoie 0 pour tous les autres sports, donc leur tri (par nom de
            // ligue) reste inchangé.
            $matchesQuery->orderByRaw("
                CASE
                    WHEN s.name != 'Tennis' THEN 0
                    WHEN l.tennis_points >= 1000 THEN 0
                    WHEN c.name = 'ATP' THEN 1
                    WHEN c.name = 'WTA' THEN 2
                    WHEN c.name IN ('Challenger', 'WTA 125') THEN 3
                    WHEN c.name IN ('ITF Men', 'ITF Women') THEN 4
                    WHEN c.name IN ('UTR Men', 'UTR Women') THEN 5
                    ELSE 6
                END
            ")
                // Grandes ligues d'abord pour les autres sports (football
                // notamment) : l.priority reflète l'importance du championnat
                // côté Sofascore (ex. Premier League ~93, championnat mineur
                // 0). Sans effet sur le tennis (l.priority toujours 0, déjà
                // départagé par le CASE ci-dessus).
                ->orderBy('l.priority', 'desc')
                ->orderBy('l.name')
                ->orderBy('m.match_start_time');
        }

        $matches = $matchesQuery
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'success'  => true,
            'date'     => $date,
            'count'    => $matches->count(),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'has_more' => ($page * $perPage) < $total,
            'matches'  => $matches,
        ]);
    }

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
        $sportId     = 2; // Tennis
        $searchLower = mb_strtolower($search);
        $searchStart = $searchLower . '%';

        $sub = DB::table('league_team')
            ->join('leagues', 'leagues.id', '=', 'league_team.league_id')
            ->where('leagues.sport_id', $sportId)
            ->select('league_team.team_id', DB::raw('MAX(leagues.priority) as max_priority'))
            ->groupBy('league_team.team_id');

        return Team::leftJoinSub($sub, 'lp', fn($j) => $j->on('teams.id', '=', 'lp.team_id'))
            ->whereNotNull('lp.team_id')
            ->where('teams.name', 'NOT LIKE', '%/%')
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
