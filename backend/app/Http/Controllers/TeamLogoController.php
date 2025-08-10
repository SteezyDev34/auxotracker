<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TeamLogoService;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TeamLogoController extends Controller
{
    protected TeamLogoService $logoService;
    
    public function __construct(TeamLogoService $logoService)
    {
        $this->logoService = $logoService;
    }
    
    /**
     * Télécharge le logo d'une équipe spécifique
     * 
     * @param int $teamId
     * @return JsonResponse
     */
    public function downloadLogo(int $teamId): JsonResponse
    {
        try {
            $team = Team::findOrFail($teamId);
            
            if (!$team->sofascore_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun sofascore_id défini pour cette équipe'
                ], 400);
            }
            
            $logoPath = $this->logoService->ensureTeamLogo($team);
            
            if ($logoPath) {
                return response()->json([
                    'success' => true,
                    'message' => 'Logo téléchargé avec succès',
                    'logo_path' => $logoPath
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Échec du téléchargement du logo'
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors du téléchargement du logo', [
                'team_id' => $teamId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur lors du téléchargement'
            ], 500);
        }
    }
    
    /**
     * Télécharge tous les logos manquants
     * 
     * @return JsonResponse
     */
    public function downloadAllMissing(): JsonResponse
    {
        try {
            $stats = $this->logoService->processAllMissingLogos();
            
            return response()->json([
                'success' => true,
                'message' => 'Traitement terminé',
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors du téléchargement de tous les logos', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur lors du traitement'
            ], 500);
        }
    }
    
    /**
     * Vérifie le statut des logos d'équipes
     * 
     * @return JsonResponse
     */
    public function checkStatus(): JsonResponse
    {
        try {
            $teams = Team::whereNotNull('sofascore_id')->get();
            
            $stats = [
                'total_teams' => $teams->count(),
                'with_logo' => 0,
                'without_logo' => 0,
                'teams_without_logo' => []
            ];
            
            foreach ($teams as $team) {
                $logoPath = "team_logos/{$team->id}.png";
                
                if ($team->img && Storage::disk('public')->exists($team->img)) {
                    $stats['with_logo']++;
                } else {
                    $stats['without_logo']++;
                    $stats['teams_without_logo'][] = [
                        'id' => $team->id,
                        'name' => $team->name,
                        'sofascore_id' => $team->sofascore_id
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification du statut des logos', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur lors de la vérification'
            ], 500);
        }
    }
}
