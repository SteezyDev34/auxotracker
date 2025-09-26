<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Team;

class SofaScoreController extends Controller
{
    /**
     * Récupère les statistiques d'un joueur par son ID SofaScore
     * 
     * @param string $sofascoreId L'ID SofaScore du joueur
     * @return JsonResponse
     */
    public function getPlayerStatistics(string $sofascoreId): JsonResponse
    {
        try {
            // Construction du chemin vers le fichier de statistiques
            $filePath = "sofascore_cache/tennis_players/players/statistics/player_statistics_{$sofascoreId}.json";
            
            // Vérification de l'existence du fichier
            if (!Storage::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => "Aucune statistique trouvée pour l'ID SofaScore: {$sofascoreId}",
                    'error' => 'FILE_NOT_FOUND'
                ], 404);
            }
            
            // Lecture du contenu du fichier
            $fileContent = Storage::get($filePath);
            
            // Décodage du JSON
            $statistics = json_decode($fileContent, true);
            
            // Vérification de la validité du JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Erreur de décodage JSON pour le fichier: {$filePath}", [
                    'json_error' => json_last_error_msg(),
                    'sofascore_id' => $sofascoreId
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors du décodage des données de statistiques',
                    'error' => 'INVALID_JSON'
                ], 500);
            }
            
            // Retour des statistiques avec métadonnées
            return response()->json([
                'success' => true,
                'sofascore_id' => $sofascoreId,
                'data' => $statistics,
                'file_path' => $filePath,
                'retrieved_at' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des statistiques SofaScore", [
                'sofascore_id' => $sofascoreId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur interne du serveur lors de la récupération des statistiques',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Récupère les statistiques d'une équipe par son ID en base de données
     * 
     * @param int $teamId L'ID de l'équipe en base de données
     * @return JsonResponse
     */
    public function getTeamStatistics(int $teamId): JsonResponse
    {
        try {
            // Récupération de l'équipe avec son sofascore_id
            $team = Team::find($teamId);
            
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => "Aucune équipe trouvée avec l'ID: {$teamId}",
                    'error' => 'TEAM_NOT_FOUND'
                ], 404);
            }
            
            if (!$team->sofascore_id) {
                return response()->json([
                    'success' => false,
                    'message' => "L'équipe {$team->name} n'a pas de sofascore_id associé",
                    'error' => 'NO_SOFASCORE_ID'
                ], 404);
            }
            
            // Construction du chemin vers le fichier de statistiques d'équipe
            $filePath = "sofascore_cache/tennis_players/players/statistics/player_statistics_{$team->sofascore_id}.json";
            
            // Vérification de l'existence du fichier
            if (!Storage::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => "Aucune statistique trouvée pour l'équipe {$team->name} (SofaScore ID: {$team->sofascore_id})",
                    'error' => 'STATISTICS_FILE_NOT_FOUND'
                ], 404);
            }
            
            // Lecture du contenu du fichier
            $fileContent = Storage::get($filePath);
            
            // Décodage du JSON
            $statistics = json_decode($fileContent, true);
            
            // Vérification de la validité du JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Erreur de décodage JSON pour le fichier d'équipe: {$filePath}", [
                    'json_error' => json_last_error_msg(),
                    'team_id' => $teamId,
                    'sofascore_id' => $team->sofascore_id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors du décodage des données de statistiques d\'équipe',
                    'error' => 'INVALID_JSON'
                ], 500);
            }
            
            // Retour des statistiques avec métadonnées
            return response()->json([
                'success' => true,
                'team_id' => $teamId,
                'team_name' => $team->name,
                'sofascore_id' => $team->sofascore_id,
                'data' => $statistics,
                'file_path' => $filePath,
                'retrieved_at' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des statistiques d'équipe SofaScore", [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur interne du serveur lors de la récupération des statistiques d\'équipe',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }
}