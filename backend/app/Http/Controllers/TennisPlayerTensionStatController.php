<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TennisPlayerGameTensionStat;
use Illuminate\Http\JsonResponse;

class TennisPlayerTensionStatController extends Controller
{
    /**
     * Expose les stats "match serré" / martingale d'un joueur de tennis
     * (celles utilisées par le calcul du score et les edges dans /api/matches/today).
     */
    public function show(int $teamId): JsonResponse
    {
        $team = Team::find($teamId);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => "Aucune équipe trouvée avec l'ID: {$teamId}",
                'error' => 'TEAM_NOT_FOUND',
            ], 404);
        }

        $stat = TennisPlayerGameTensionStat::where('team_id', $teamId)->first();

        if (!$stat) {
            return response()->json([
                'success' => false,
                'message' => "Aucune stat de tension calculée pour {$team->name}",
                'error' => 'TENSION_STAT_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'team_id' => $teamId,
            'team_name' => $team->name,
            'sofascore_id' => $team->sofascore_id,
            'reliable' => $stat->isReliable(),
            'sample' => [
                'matches' => $stat->sample_matches,
                'games_set1' => $stat->sample_games_set1,
                'service_games_set1' => $stat->sample_service_games_set1,
            ],
            'stats' => [
                'reach_15a' => round(($stat->rate15a() ?? 0) * 100, 2),
                'reach_30a' => round(($stat->rate30a() ?? 0) * 100, 2),
                'reach_40a' => round(($stat->rate40a() ?? 0) * 100, 2),
                'reach_30love' => round(($stat->rate30Love() ?? 0) * 100, 2),
                'game_40_0' => round(($stat->rateGame40_0() ?? 0) * 100, 2),
                'game_40_15' => round(($stat->rateGame40_15() ?? 0) * 100, 2),
                'game_40_30' => round(($stat->rateGame40_30() ?? 0) * 100, 2),
                'server_loss_to_love' => round(($stat->rateServerLossToLove() ?? 0) * 100, 2),
                'leads_15_0' => round(($stat->rateLedFirstPoint() ?? 0) * 100, 2),
                'lost_first_point_on_serve' => round(($stat->rateLostFirstPointOnServe() ?? 0) * 100, 2),
            ],
            'updated_from_cache_at' => $stat->updated_from_cache_at,
        ]);
    }
}
