<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TeamLogoService
{
    /**
     * Vérifie et télécharge le logo d'une équipe si nécessaire
     * 
     * @param Team $team
     * @return string|null Le chemin du logo ou null si échec
     */
    public function ensureTeamLogo(Team $team): ?string
    {
        // Vérifier si le logo existe déjà
        $logoPath = "team_logos/{$team->id}.png";
        
        if (Storage::disk('public')->exists($logoPath)) {
            // Mettre à jour le champ img si nécessaire
            if ($team->img !== $logoPath) {
                $team->update(['img' => $logoPath]);
            }
            return $logoPath;
        }
        
        // Télécharger le logo depuis Sofascore si sofascore_id existe
        if ($team->sofascore_id) {
            return $this->downloadTeamLogo($team);
        }
        
        return null;
    }
    
    /**
     * Télécharge le logo d'une équipe depuis l'API Sofascore
     * 
     * @param Team $team
     * @return string|null Le chemin du logo ou null si échec
     */
    private function downloadTeamLogo(Team $team): ?string
    {
        // Essayer plusieurs stratégies de téléchargement
        $strategies = [
            $this->getDefaultHeaders(),
            $this->getAlternativeHeaders(),
            $this->getMinimalHeaders()
        ];
        
        foreach ($strategies as $index => $headers) {
            try {
                $logoUrl = "https://api.sofascore.com/api/v1/team/{$team->sofascore_id}/image";
                
                Log::info("Tentative de téléchargement du logo pour l'équipe {$team->name}", [
                     'strategy' => ($index + 1),
                     'sofascore_id' => $team->sofascore_id,
                     'url' => $logoUrl
                 ]);
                
                $response = Http::timeout(30)
                    ->withHeaders($headers)
                    ->get($logoUrl);
                
                if ($response->successful()) {
                    $logoPath = "team_logos/{$team->id}.png";
                    
                    // Créer le dossier s'il n'existe pas
                    Storage::disk('public')->makeDirectory('team_logos');
                    
                    // Sauvegarder l'image
                    Storage::disk('public')->put($logoPath, $response->body());
                    
                    // Mettre à jour le champ img de l'équipe
                    $team->update(['img' => $logoPath]);
                    
                    Log::info("Logo téléchargé avec succès pour l'équipe {$team->name}", [
                         'path' => $logoPath,
                         'strategy' => ($index + 1),
                         'file_size' => strlen($response->body())
                     ]);
                    
                    return $logoPath;
                }
                
                // Gestion spécifique des erreurs 403 et 404
                if (in_array($response->status(), [403, 404])) {
                    Log::info("Logo non disponible pour l'équipe {$team->name} (HTTP {$response->status()}) - Stratégie " . ($index + 1), [
                         'sofascore_id' => $team->sofascore_id,
                         'status' => $response->status(),
                         'headers_used' => array_keys($headers)
                     ]);
                    
                    // Attendre avant la prochaine tentative pour éviter les blocages
                    if ($index < count($strategies) - 1) {
                        sleep(3); // Délai plus long pour éviter les erreurs 403
                    }
                    continue;
                }
                
                Log::warning("Échec du téléchargement du logo pour l'équipe {$team->name} - Stratégie " . ($index + 1), [
                     'sofascore_id' => $team->sofascore_id,
                     'status' => $response->status(),
                     'response_body' => substr($response->body(), 0, 200)
                 ]);
                
            } catch (\Exception $e) {
                Log::error("Erreur lors du téléchargement du logo pour l'équipe {$team->name} - Stratégie " . ($index + 1), [
                     'error' => $e->getMessage(),
                     'sofascore_id' => $team->sofascore_id,
                     'exception_type' => get_class($e)
                 ]);
            }
        }
        
        // Toutes les stratégies ont échoué
        Log::error("Toutes les stratégies de téléchargement ont échoué pour l'équipe {$team->name}", [
            'sofascore_id' => $team->sofascore_id,
            'strategies_tried' => count($strategies)
        ]);
        
        return null;
    }
    
    /**
     * Obtient les en-têtes par défaut pour les requêtes HTTP
     * Simule un navigateur Chrome sur macOS pour contourner la détection de bot
     */
    private function getDefaultHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language' => 'en-US,en;q=0.9,fr;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'DNT' => '1',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Cache-Control' => 'max-age=0',
            'Referer' => 'https://www.sofascore.com/'
        ];
    }
    
    /**
     * Retourne des en-têtes alternatifs pour contourner les blocages
     * 
     * @return array
     */
    private function getAlternativeHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => '*/*',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache'
        ];
    }
    
    /**
     * Retourne des en-têtes minimaux
     * 
     * @return array
     */
    private function getMinimalHeaders(): array
    {
        return [
            'User-Agent' => 'curl/7.68.0',
            'Accept' => '*/*'
        ];
    }
    
    /**
     * Process all missing logos for all teams
     * 
     * @return array Processing statistics
     * 
     * To run this method, use the following command in Artisan Tinker:
     * app(App\Services\TeamLogoService::class)->processAllMissingLogos();
     */
    public function processAllMissingLogos(): array
    {
        $teams = Team::whereNotNull('sofascore_id')
                    ->where(function($query) {
                        $query->whereNull('img')
                              ->orWhere('img', '');
                    })
                    ->get();
        
        $stats = [
            'total' => $teams->count(),
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        ];
        
        foreach ($teams as $team) {
            $result = $this->ensureTeamLogo($team);
            
            if ($result) {
                $stats['success']++;
            } else {
                $stats['failed']++;
            }
        }
        
        return $stats;
    }
}