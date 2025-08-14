<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Country;
use App\Models\League;
use App\Models\Sport;

class ImportBasketballLeagues extends Command
{
    /**
     * Nom et signature de la commande
     */
    protected $signature = 'basketball:import-leagues {--force : Forcer l\'importation même si des ligues existent déjà} {--no-cache : Ne pas utiliser le cache}';

    /**
     * Description de la commande
     */
    protected $description = 'Importer les pays et leurs ligues de basketball depuis l\'API Sofascore';

    /**
     * URL de base de l'API Sofascore
     */
    private const SOFASCORE_BASE_URL = 'https://www.sofascore.com/api/v1';
    
    /**
     * Liste des User-Agents pour la rotation
     */
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 Edg/91.0.864.59',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36 OPR/78.0.4093.112',
    ];
    
    /**
     * Répertoire de cache pour les réponses API
     */
    private $cacheDir;

    /**
     * Exécuter la commande
     */
    public function handle()
    {
        // Initialiser le répertoire de cache
        $this->setCacheDirectory();
        
        $this->info('🏀 Début de l\'importation des ligues de basketball...');
        
        try {
            // Récupérer les catégories (pays) de basketball
            $this->line('📡 Récupération des catégories de basketball...');
            $categoriesResponse = $this->makeRequestWithRetry(self::SOFASCORE_BASE_URL . '/sport/basketball/categories');
            
            if (!$categoriesResponse->successful()) {
                $this->error('❌ Erreur lors de la récupération des catégories: ' . $categoriesResponse->status());
                return 1;
            }
            
            $categoriesData = $categoriesResponse->json();
            $categories = $categoriesData['categories'] ?? [];
            
            if (empty($categories)) {
                $this->error('❌ Aucune catégorie récupérée depuis l\'API');
                return 1;
            }
            
            // Récupérer l'ID Sofascore du sport depuis la première catégorie
            $basketballSofascoreId = $categories[0]['sport']['id'] ?? null;
            if (!$basketballSofascoreId) {
                $this->error('❌ ID Sofascore du sport Basketball non trouvé dans l\'API');
                return 1;
            }
            
            // Récupérer le sport Basketball par son sofascore_id
            $sport = Sport::where('sofascore_id', $basketballSofascoreId)->first();
            if (!$sport) {
                $this->error("❌ Sport Basketball non trouvé (sofascore_id: {$basketballSofascoreId})");
                return 1;
            }
            
            $this->info("✅ Sport trouvé: {$sport->name} (ID: {$sport->id})");
            
            $this->info('📊 ' . count($categories) . ' catégories trouvées');
            
            $totalProcessed = 0;
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalSkipped = 0;
            
            // Barre de progression
            $progressBar = $this->output->createProgressBar(count($categories));
            $progressBar->start();
            
            foreach ($categories as $index => $categoryData) {
                $progressBar->advance();
                
                $this->line("");
                $alpha2 = $categoryData['alpha2'] ?? 'N/A';
                $this->line("🏴 Traitement du pays: {$categoryData['name']} ({$alpha2})");
                
                // Trouver le pays correspondant en base
                $country = $this->findOrCreateCountry($categoryData);
                if (!$country) {
                    continue;
                }
                
                $this->line("   ✅ Pays trouvé: {$country->name} (ID: {$country->id})");
                
                // Récupérer les ligues pour ce pays
                $this->line("     🔍 Récupération des ligues pour le pays ID: {$categoryData['id']}");
                $leaguesResponse = $this->makeRequestWithRetry(self::SOFASCORE_BASE_URL . "/category/{$categoryData['id']}/unique-tournaments");
                
                if (!$leaguesResponse->successful()) {
                    $this->line("     ❌ Erreur lors de la récupération des ligues: {$leaguesResponse->status()}");
                    continue;
                }
                
                $this->line("     📡 Réponse ligues reçue avec le statut: {$leaguesResponse->status()}");
                
                $leaguesData = $leaguesResponse->json();
                
                // Extraire les ligues depuis la structure groups[].uniqueTournaments[]
                $uniqueTournaments = [];
                if (isset($leaguesData['groups']) && is_array($leaguesData['groups'])) {
                    foreach ($leaguesData['groups'] as $group) {
                        if (isset($group['uniqueTournaments']) && is_array($group['uniqueTournaments'])) {
                            $uniqueTournaments = array_merge($uniqueTournaments, $group['uniqueTournaments']);
                        }
                    }
                }
                
                $this->line('   🏆 ' . count($uniqueTournaments) . ' ligues trouvées');
                
                foreach ($uniqueTournaments as $tournamentData) {
                    $result = $this->processLeague($tournamentData, $country, $sport);
                    $totalProcessed++;
                    
                    switch ($result) {
                        case 'created':
                            $totalCreated++;
                            break;
                        case 'updated':
                            $totalUpdated++;
                            break;
                        case 'skipped':
                            $totalSkipped++;
                            break;
                    }
                }
            }
            
            $progressBar->finish();
            $this->line("");
            $this->line("");
            
            // Résumé final
            $this->info('🎉 Importation terminée!');
            $this->table(
                ['Statistique', 'Nombre'],
                [
                    ['Ligues traitées', $totalProcessed],
                    ['Ligues créées', $totalCreated],
                    ['Ligues mises à jour', $totalUpdated],
                    ['Ligues ignorées', $totalSkipped],
                ]
            );
            
            Log::info('Importation des ligues de basketball terminée', [
                'total_processed' => $totalProcessed,
                'total_created' => $totalCreated,
                'total_updated' => $totalUpdated,
                'total_skipped' => $totalSkipped
            ]);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de l\'importation: ' . $e->getMessage());
            Log::error('Erreur lors de l\'importation des ligues de basketball', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Traiter une ligue individuelle
     */
    private function processLeague($tournamentData, $country, $sport)
    {
        try {
            $name = $tournamentData['name'];
            $slug = $tournamentData['slug'];
            $sofascoreId = (string) $tournamentData['id'];
            
            $this->line("       🏆 Ligue: {$name} (ID: {$sofascoreId})");
            
            // Vérifier si la ligue existe déjà
            $existingLeague = League::where('sofascore_id', $sofascoreId)
                ->where('sport_id', $sport->id)
                ->first();
            
            if ($existingLeague && !$this->option('force')) {
                $this->line("         ⏭️  Ligue déjà existante, ignorée");
                Log::info('Ligue de basketball ignorée (déjà existante)', [
                    'existing_league_id' => $existingLeague->id,
                    'sofascore_id' => $sofascoreId,
                    'name' => $name,
                    'country_id' => $country->id,
                    'sport_id' => $sport->id
                ]);
                return 'skipped';
            }
            
            // Créer ou mettre à jour la ligue
            $this->line("         💾 " . ($existingLeague ? 'Mise à jour' : 'Création') . " de la ligue...");
            $league = League::updateOrCreate(
                [
                    'sofascore_id' => $sofascoreId,
                    'sport_id' => $sport->id
                ],
                [
                    'name' => $name,
                    'slug' => $slug,
                    'country_id' => $country->id
                ]
            );
            
            $action = $existingLeague ? 'updated' : 'created';
            $this->line("         ✅ Ligue {$action} avec succès (ID: {$league->id})");
            
            Log::info('Ligue de basketball traitée avec succès', [
                'league_id' => $league->id,
                'sofascore_id' => $sofascoreId,
                'name' => $name,
                'country_id' => $country->id,
                'sport_id' => $sport->id,
                'action' => $action
            ]);
            
            return $action;
            
        } catch (\Exception $e) {
            $this->line("         ❌ Erreur lors du traitement de la ligue: {$e->getMessage()}");
            Log::error('Erreur lors du traitement d\'une ligue de basketball', [
                'tournament_data' => $tournamentData,
                'country_id' => $country->id,
                'sport_id' => $sport->id,
                'error' => $e->getMessage()
            ]);
            return 'error';
        }
    }

    /**
     * Trouver ou créer un pays en base de données
     */
    private function findOrCreateCountry($countryData)
    {
        try {
            $this->line("     🔍 Recherche du pays: {$countryData['name']}");
            
            $country = null;
            
            // Chercher d'abord par code (alpha2) si disponible
            if (isset($countryData['alpha2']) && !empty($countryData['alpha2'])) {
                $country = Country::where('code', $countryData['alpha2'])->first();
            }
            
            // Si pas trouvé et pas d'alpha2, chercher par nom
            if (!$country && isset($countryData['name'])) {
                $country = Country::where('name', $countryData['name'])->first();
            }
            
            // Si toujours pas trouvé, chercher par slug
            if (!$country && isset($countryData['slug'])) {
                $country = Country::where('slug', $countryData['slug'])->first();
            }
            
            if (!$country) {
                $this->line("");
                $this->error("❌ Pays non trouvé en base de données:");
                $this->line("   - Nom: {$countryData['name']}");
                $this->line("   - Alpha2: " . ($countryData['alpha2'] ?? 'N/A'));
                $this->line("   - Slug: " . ($countryData['slug'] ?? 'N/A'));
                $this->line("   - ID Sofascore: {$countryData['id']}");
                $this->line("");
                $this->error("🛑 Arrêt du script. Veuillez ajouter ce pays en base de données avant de continuer.");
                
                Log::error('Script arrêté - Pays non trouvé en base de données', [
                    'country_name' => $countryData['name'],
                    'alpha2' => $countryData['alpha2'] ?? null,
                    'slug' => $countryData['slug'] ?? null,
                    'sofascore_id' => $countryData['id']
                ]);
                
                exit(1);
            }
            
            return $country;
            
        } catch (\Exception $e) {
            $this->line("     ❌ Erreur lors de la recherche du pays: {$e->getMessage()}");
            Log::error('Erreur lors de la recherche du pays', [
                'country_data' => $countryData,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Définit le répertoire de cache pour les réponses API
     */
    private function setCacheDirectory()
    {
        $this->cacheDir = storage_path('app/cache/sofascore/basketball');
        
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Vérifie si le cache doit être utilisé
     */
    private function shouldUseCache()
    {
        return !$this->option('no-cache');
    }
    
    /**
     * Génère une clé de cache pour une URL
     */
    private function generateCacheKey($url)
    {
        return md5($url);
    }
    
    /**
     * Récupère une réponse mise en cache
     */
    private function getCachedResponse($url)
    {
        if (!$this->shouldUseCache()) {
            return null;
        }
        
        $cacheKey = $this->generateCacheKey($url);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';
        
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            
            // Vérifier si le cache est encore valide (24 heures)
            if (isset($cacheData['timestamp']) && (time() - $cacheData['timestamp']) < 86400) {
                $this->line("     📦 Utilisation de la réponse en cache pour: " . basename($url));
                
                // Recréer une réponse HTTP à partir des données en cache
                $response = Http::response(
                    $cacheData['body'],
                    $cacheData['status'],
                    $cacheData['headers']
                );
                
                return $response;
            }
        }
        
        return null;
    }
    
    /**
     * Sauvegarde une réponse en cache
     */
    private function cacheResponse($url, $response)
    {
        if (!$this->shouldUseCache() || !$response->successful()) {
            return;
        }
        
        $cacheKey = $this->generateCacheKey($url);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';
        
        $cacheData = [
            'timestamp' => time(),
            'url' => $url,
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->body(),
        ];
        
        file_put_contents($cacheFile, json_encode($cacheData));
        $this->line("     💾 Réponse mise en cache pour: " . basename($url));
    }
    
    /**
     * Effectue une requête HTTP avec rotation de User-Agent, délais aléatoires et retry
     */
    private function makeRequestWithRetry($url, $maxRetries = 3)
    {
        // Vérifier d'abord si nous avons une réponse en cache
        $cachedResponse = $this->getCachedResponse($url);
        if ($cachedResponse) {
            return $cachedResponse;
        }
        
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $maxRetries) {
            try {
                // Ajouter un délai aléatoire pour éviter la détection de bot
                $delay = rand(1000, 3000);
                usleep($delay * 1000); // Convertir en microsecondes
                
                // Sélectionner un User-Agent aléatoire
                $userAgent = self::USER_AGENTS[array_rand(self::USER_AGENTS)];
                
                $this->line("     🔄 Tentative de requête #" . ($attempt + 1) . " pour: " . basename($url));
                
                // Effectuer la requête avec des en-têtes améliorés
                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => $userAgent,
                        'Accept' => 'application/json, text/plain, */*',
                        'Accept-Language' => 'fr,fr-FR;q=0.9,en-US;q=0.8,en;q=0.7',
                        'Origin' => 'https://www.sofascore.com',
                        'Referer' => 'https://www.sofascore.com/basketball',
                        'Sec-Fetch-Dest' => 'empty',
                        'Sec-Fetch-Mode' => 'cors',
                        'Sec-Fetch-Site' => 'same-origin',
                        'Cache-Control' => 'no-cache',
                        'Pragma' => 'no-cache',
                    ])
                    ->get($url);
                
                // Si la requête a réussi, mettre en cache et retourner la réponse
                if ($response->successful()) {
                    $this->cacheResponse($url, $response);
                    return $response;
                }
                
                // Si nous avons une erreur 403, augmenter le délai et réessayer
                if ($response->status() === 403) {
                    $this->line("     ⚠️ Erreur 403 reçue, nouvelle tentative avec délai plus long...");
                    $attempt++;
                    // Backoff exponentiel
                    $backoffDelay = pow(2, $attempt) * 1000;
                    usleep($backoffDelay * 1000);
                    continue;
                }
                
                // Pour les autres erreurs, retourner la réponse telle quelle
                return $response;
                
            } catch (\Exception $e) {
                $lastException = $e;
                $this->line("     ⚠️ Erreur lors de la requête: {$e->getMessage()}");
                $attempt++;
                
                // Backoff exponentiel
                $backoffDelay = pow(2, $attempt) * 1000;
                usleep($backoffDelay * 1000);
            }
        }
        
        // Si toutes les tentatives ont échoué, créer une réponse d'erreur
        $this->error("     ❌ Toutes les tentatives ont échoué pour: " . basename($url));
        
        if ($lastException) {
            return Http::response(
                json_encode(['error' => $lastException->getMessage()]),
                500,
                ['Content-Type' => 'application/json']
            );
        }
        
        return Http::response(
            json_encode(['error' => 'Maximum retry attempts reached']),
            500,
            ['Content-Type' => 'application/json']
        );
    }
}