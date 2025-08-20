<?php

namespace App\Services;

use App\Models\League;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class LeagueLogoService
{
    /**
     * Vérifie et télécharge les logos d'une ligue si nécessaire
     * 
     * @param League $league
     * @return array|null Les chemins des logos avec info de mise à jour ou null si échec
     */
    public function ensureLeagueLogos(League $league): ?array
    {
        // Vérifier si les logos existent déjà
        $lightLogoPath = "league_logos/{$league->id}.png";
        $darkLogoPath = "league_logos/{$league->id}-dark.png";
        
        $existingLogos = [];
        
        if (Storage::disk('public')->exists($lightLogoPath)) {
            $existingLogos['light'] = $lightLogoPath;
        }
        
        if (Storage::disk('public')->exists($darkLogoPath)) {
            $existingLogos['dark'] = $darkLogoPath;
        }
        
        // Si au moins un logo existe, mettre à jour le champ img et retourner les logos existants
        if (!empty($existingLogos)) {
            // Mettre à jour le champ img avec le logo principal (light en priorité)
            $mainLogoPath = isset($existingLogos['light']) ? $existingLogos['light'] : $existingLogos['dark'];
            $imgUpdated = false;
            if ($league->img !== $mainLogoPath) {
                $league->update(['img' => $mainLogoPath]);
                $imgUpdated = true;
                Log::info("Champ img mis à jour pour la ligue {$league->name}", [
                    'league_id' => $league->id,
                    'img_path' => $mainLogoPath
                ]);
            }
            $existingLogos['img_updated'] = $imgUpdated;
            return $existingLogos;
        }
        
        // Télécharger les logos depuis Sofascore si sofascore_id existe
        if ($league->sofascore_id) {
            return $this->downloadLeagueLogos($league);
        }
        
        return null;
    }
    
    /**
     * Télécharge les logos d'une ligue depuis l'API Sofascore
     * 
     * @param League $league
     * @return array|null Les chemins des logos ou null si échec
     */
    private function downloadLeagueLogos(League $league): ?array
    {
        $downloadedLogos = [];
        
        // Essayer plusieurs stratégies de téléchargement
        $strategies = [
            $this->getDefaultHeaders(),
            $this->getAlternativeHeaders(),
            $this->getMinimalHeaders()
        ];
        
        // Tentative de téléchargement du logo light
        $lightDownloaded = false;
        foreach ($strategies as $index => $headers) {
            $lightResult = $this->downloadSingleLogo($league, 'light', $headers, $index);
            if ($lightResult) {
                $downloadedLogos['light'] = $lightResult;
                $lightDownloaded = true;
                break;
            }
        }
        
        // Tentative de téléchargement du logo dark
        $darkDownloaded = false;
        foreach ($strategies as $index => $headers) {
            $darkResult = $this->downloadSingleLogo($league, 'dark', $headers, $index);
            if ($darkResult) {
                $downloadedLogos['dark'] = $darkResult;
                $darkDownloaded = true;
                break;
            }
        }
        
        // Logique de fallback : si light n'existe pas, utiliser dark comme défaut
        if (!$lightDownloaded && $darkDownloaded) {
            // Copier le logo dark comme logo principal
            $mainLogoPath = "league_logos/{$league->id}.png";
            $darkLogoPath = $downloadedLogos['dark'];
            
            $darkContent = Storage::disk('public')->get($darkLogoPath);
            Storage::disk('public')->put($mainLogoPath, $darkContent);
            
            $downloadedLogos['light'] = $mainLogoPath;
            
            Log::info("Logo dark utilisé comme logo principal pour la ligue {$league->name}", [
                'league_id' => $league->id,
                'sofascore_id' => $league->sofascore_id
            ]);
        }
        
        // Mettre à jour le champ img de la ligue avec le logo principal
        $imgUpdated = false;
        if (isset($downloadedLogos['light'])) {
            $league->update(['img' => $downloadedLogos['light']]);
            $imgUpdated = true;
        }
        
        if (!empty($downloadedLogos)) {
            $downloadedLogos['img_updated'] = $imgUpdated;
            return $downloadedLogos;
        }
        
        return null;
    }
    
    /**
     * Télécharge un logo spécifique (light ou dark)
     * 
     * @param League $league
     * @param string $type 'light' ou 'dark'
     * @param array $headers
     * @param int $strategyIndex
     * @return string|null Le chemin du logo ou null si échec
     */
    private function downloadSingleLogo(League $league, string $type, array $headers, int $strategyIndex): ?string
    {
        try {
            $logoUrl = "https://img.sofascore.com/api/v1/unique-tournament/{$league->sofascore_id}/image/{$type}";
            
            Log::info("Tentative de téléchargement du logo {$type} pour la ligue {$league->name}", [
                'strategy' => ($strategyIndex + 1),
                'sofascore_id' => $league->sofascore_id,
                'url' => $logoUrl
            ]);
            
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->get($logoUrl);
            
            if ($response->successful()) {
                $logoPath = $type === 'light' 
                    ? "league_logos/{$league->id}.png" 
                    : "league_logos/{$league->id}-dark.png";
                
                // Créer le dossier s'il n'existe pas
                Storage::disk('public')->makeDirectory('league_logos');
                
                // Sauvegarder l'image
                Storage::disk('public')->put($logoPath, $response->body());
                
                Log::info("Logo {$type} téléchargé avec succès pour la ligue {$league->name}", [
                    'path' => $logoPath,
                    'strategy' => ($strategyIndex + 1),
                    'file_size' => strlen($response->body())
                ]);
                
                return $logoPath;
            }
            
            // Gestion spécifique des erreurs 403 et 404
            if (in_array($response->status(), [403, 404])) {
                Log::info("Logo {$type} non disponible pour la ligue {$league->name} (HTTP {$response->status()}) - Stratégie " . ($strategyIndex + 1), [
                    'sofascore_id' => $league->sofascore_id,
                    'status' => $response->status(),
                    'headers_used' => array_keys($headers)
                ]);
                
                // Attendre avant la prochaine tentative pour éviter les blocages
                if ($strategyIndex < 2) { // Il y a 3 stratégies (index 0, 1, 2)
                    sleep(3); // Délai plus long pour éviter les erreurs 403
                }
                return null;
            }
            
            Log::warning("Échec du téléchargement du logo {$type} pour la ligue {$league->name} - Stratégie " . ($strategyIndex + 1), [
                'sofascore_id' => $league->sofascore_id,
                'status' => $response->status(),
                'response_body' => substr($response->body(), 0, 200)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors du téléchargement du logo {$type} pour la ligue {$league->name} - Stratégie " . ($strategyIndex + 1), [
                'error' => $e->getMessage(),
                'sofascore_id' => $league->sofascore_id,
                'exception_type' => get_class($e)
            ]);
        }
        
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
     * Traite tous les logos manquants pour toutes les ligues
     * 
     * @return array Statistiques de traitement
     * 
     * Pour exécuter cette méthode, utilisez la commande suivante dans Artisan Tinker :
     * app(App\Services\LeagueLogoService::class)->processAllMissingLogos();
     */
    public function processAllMissingLogos(): array
    {
        $leagues = League::whereNotNull('sofascore_id')
                    ->where(function($query) {
                        $query->whereNull('img')
                              ->orWhere('img', '');
                    })
                    ->get();
        
        $stats = [
            'total' => $leagues->count(),
            'success' => 0,
            'failed' => 0,
            'light_only' => 0,
            'dark_only' => 0,
            'both' => 0
        ];
        
        foreach ($leagues as $league) {
            $result = $this->ensureLeagueLogos($league);
            
            if ($result) {
                $stats['success']++;
                
                if (isset($result['light']) && isset($result['dark'])) {
                    $stats['both']++;
                } elseif (isset($result['light'])) {
                    $stats['light_only']++;
                } elseif (isset($result['dark'])) {
                    $stats['dark_only']++;
                }
            } else {
                $stats['failed']++;
            }
        }
        
        return $stats;
    }
}