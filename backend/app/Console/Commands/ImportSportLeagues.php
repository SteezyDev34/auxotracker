<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\League;
use App\Models\Country;
use App\Models\Sport;

class ImportSportLeagues extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'sport:import-leagues {sport_slug} {--force : Forcer l\'import même si la ligue existe} {--no-cache : Ne pas utiliser le cache}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Importe les pays et leurs ligues pour un sport donné depuis l\'API Sofascore';

    /**
     * Exécuter la commande console.
     */
    public function handle()
    {
        // Initialiser le répertoire de cache
        $this->setCacheDirectory();
        
        $sportSlug = $this->argument('sport_slug');
        
        $this->info("🚀 Début de l'importation des ligues pour le sport: {$sportSlug}...");
        
        try {
            // Récupérer tous les pays/catégories depuis l'API pour obtenir l'ID du sport
            $this->info('🌍 Récupération des pays et catégories...');
            $countries = $this->fetchCountries($sportSlug);
            
            if (empty($countries)) {
                $this->error('❌ Aucune catégorie récupérée depuis l\'API');
                return Command::FAILURE;
            }
            
            // Récupérer l'ID Sofascore du sport depuis la première catégorie
            $sportSofascoreId = $countries[0]['sport']['id'] ?? null;
            if (!$sportSofascoreId) {
                $this->error("❌ ID Sofascore du sport {$sportSlug} non trouvé dans l'API");
                return Command::FAILURE;
            }
            
            // Récupérer le sport par son sofascore_id
            $sport = Sport::where('sofascore_id', $sportSofascoreId)->first();
            if (!$sport) {
                $this->error("❌ Sport {$sportSlug} non trouvé en base (sofascore_id: {$sportSofascoreId})");
                return Command::FAILURE;
            }
            
            $this->info("🏆 Sport trouvé: {$sport->name} (ID: {$sport->id}, Sofascore ID: {$sport->sofascore_id})");
            
            $this->info("📋 " . count($countries) . " pays trouvés");
            
            $stats = [
                'countries_processed' => 0,
                'countries_ignored' => 0,
                'leagues_created' => 0,
                'leagues_updated' => 0,
                'leagues_skipped' => 0,
                'errors' => 0
            ];
            
            // Étape 2: Pour chaque pays, récupérer ses ligues
            $progressBar = $this->output->createProgressBar(count($countries));
            $progressBar->start();
            
            foreach ($countries as $countryData) {
                try {
                    $alpha2 = $countryData['alpha2'] ?? 'N/A';
            $this->line("\n🏴 Traitement du pays: {$countryData['name']} ({$alpha2})");
                    
                    // Vérifier si le pays existe en base
                    $country = $this->findOrCreateCountry($countryData);
                    
                    if (!$country) {
                        // Vérifier si c'est un pays ignoré (comme "In Progress") ou une vraie erreur
                        if (isset($countryData['name']) && $countryData['name'] === 'In Progress') {
                            $stats['countries_ignored']++;
                        } else {
                            $this->line("   ⚠️  Pays non trouvé en base: {$countryData['name']}");
                            $stats['errors']++;
                        }
                        continue;
                    }
                    
                    $this->line("   ✅ Pays trouvé: {$country->name} (ID: {$country->id})");
                    
                    // Récupérer les ligues pour ce pays
                    $leagues = $this->fetchLeaguesForCountry($countryData['id']);
                    
                    if (empty($leagues)) {
                        $this->line("   📭 Aucune ligue trouvée pour {$countryData['name']}");
                        $stats['countries_processed']++;
                        continue;
                    }
                    
                    $this->line("   🏆 " . count($leagues) . " ligues trouvées");
                    
                    // Traiter chaque ligue
                    foreach ($leagues as $leagueData) {
                        $result = $this->processLeague($leagueData, $country, $sport);
                        $stats[$result]++;
                    }
                    
                    $stats['countries_processed']++;
                    
                } catch (\Exception $e) {
                    $this->error("   ❌ Erreur lors du traitement du pays {$countryData['name']}: {$e->getMessage()}");
                    $stats['errors']++;
                    Log::error('Erreur lors du traitement du pays', [
                        'country' => $countryData,
                        'sport_slug' => $sportSlug,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                
                $progressBar->advance();
            }
            
            $progressBar->finish();
            $this->newLine(2);
            
            // Afficher les statistiques
            $this->info('🏁 Importation terminée!');
            $this->newLine();
            $this->info('📊 === Statistiques d\'importation ===');
            $this->info("🌍 Pays traités: {$stats['countries_processed']}");
            $this->info("⏭️  Pays ignorés: {$stats['countries_ignored']}");
            $this->info("✅ Ligues créées: {$stats['leagues_created']}");
            $this->info("🔄 Ligues mises à jour: {$stats['leagues_updated']}");
            $this->info("⏭️  Ligues ignorées: {$stats['leagues_skipped']}");
            $this->info("❌ Erreurs: {$stats['errors']}");
            
            $totalLeagues = $stats['leagues_created'] + $stats['leagues_updated'] + $stats['leagues_skipped'];
            $this->info("📋 Total ligues traitées: {$totalLeagues}");
            
            $successRate = $totalLeagues > 0 ? round((($stats['leagues_created'] + $stats['leagues_updated']) / $totalLeagues) * 100, 2) : 0;
            $this->info("📈 Taux de succès: {$successRate}%");
            
            // Log final
            Log::info('Importation des ligues terminée', [
                'sport_slug' => $sportSlug,
                'sport_id' => $sport->id,
                'countries_processed' => $stats['countries_processed'],
                'countries_ignored' => $stats['countries_ignored'],
                'leagues_created' => $stats['leagues_created'],
                'leagues_updated' => $stats['leagues_updated'],
                'leagues_skipped' => $stats['leagues_skipped'],
                'errors' => $stats['errors'],
                'success_rate' => $successRate,
                'force_mode' => $this->option('force')
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Erreur générale: ' . $e->getMessage());
            Log::error('Erreur lors de l\'importation des ligues', [
                'sport_slug' => $sportSlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
    
    /**
     * Récupérer tous les pays/catégories depuis l'API pour un sport donné
     */
    /**
     * Liste des User-Agents pour la rotation
     */
    private $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 Edg/91.0.864.59'
    ];
    
    /**
     * Obtenir un User-Agent aléatoire
     */
    private function getRandomUserAgent()
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }
    
    /**
     * Ajouter un délai aléatoire pour éviter la détection
     */
    private function addRandomDelay()
    {
        // Délai aléatoire entre 1 et 3 secondes
        $delay = rand(1000, 3000);
        usleep($delay * 1000); // usleep prend des microsecondes
        return $delay / 1000; // Retourne le délai en secondes
    }
    
    /**
     * Répertoire de cache pour les réponses API
     */
    private $cacheDir;
    
    /**
     * Définit le répertoire de cache pour les réponses API
     */
    private function setCacheDirectory()
    {
        $sportSlug = $this->argument('sport_slug');
        $this->cacheDir = storage_path('app/sofascore_cache/leagues_' . $sportSlug);
        
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
     * Génère une clé de cache à partir d'une URL
     */
    private function getCacheKey($url)
    {
        return md5($url);
    }
    
    /**
     * Vérifie si une réponse est en cache et valide
     */
    private function getCachedResponse($url)
    {
        if (!$this->shouldUseCache()) {
            return null;
        }
        
        $cacheKey = $this->getCacheKey($url);
        $cachePath = $this->cacheDir . '/' . $cacheKey . '.json';
        
        if (!file_exists($cachePath)) {
            return null;
        }
        
        // Utiliser le cache sans vérification d'expiration
        $this->line("   📂 Utilisation de la réponse en cache");
        $cachedData = json_decode(file_get_contents($cachePath), true);
        
        // Créer une réponse simulée
        $response = new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(
                $cachedData['status'],
                $cachedData['headers'],
                is_array($cachedData['body']) ? json_encode($cachedData['body']) : $cachedData['body']
            )
        );
        
        return $response;
    }
    
    /**
     * Sauvegarde une réponse en cache
     */
    private function cacheResponse($url, $response)
    {
        if (!$this->shouldUseCache() || !$response->successful()) {
            return;
        }
        
        $cacheKey = $this->getCacheKey($url);
        $cachePath = $this->cacheDir . '/' . $cacheKey . '.json';
        
        $dataToCache = [
            'url' => $url,
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->json(),
            'cached_at' => time()
        ];
        
        file_put_contents($cachePath, json_encode($dataToCache, JSON_PRETTY_PRINT));
        $this->line("   💾 Réponse mise en cache");
    }
    
    /**
     * Effectue une requête HTTP avec retry et backoff exponentiel
     */
    private function makeRequestWithRetry($url, $maxRetries = 3)
    {
        // Vérifier si la réponse est en cache
        $cachedResponse = $this->getCachedResponse($url);
        if ($cachedResponse) {
            return $cachedResponse;
        }
        
        $this->line("   🌐 Requête API en direct...");
        
        $attempt = 0;
        $response = null;
        $success = false;
        
        while (!$success && $attempt < $maxRetries) {
            $attempt++;
            
            // Ajouter un délai exponentiel à partir de la deuxième tentative
            if ($attempt > 1) {
                $backoffDelay = pow(2, $attempt - 1) + rand(1, 1000) / 1000;
                $this->line("   🔄 Tentative {$attempt}/{$maxRetries} après {$backoffDelay} secondes...");
                sleep($backoffDelay);
            } else {
                $this->line("   🔄 Tentative {$attempt}/{$maxRetries}...");
            }
            
            // Ajouter un délai aléatoire
            $delay = $this->addRandomDelay();
            
            // Obtenir un User-Agent aléatoire
            $userAgent = $this->getRandomUserAgent();
            $this->line("   🔄 Utilisation du User-Agent: " . substr($userAgent, 0, 30) . "...");
            
            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => $userAgent,
                        'Accept' => 'application/json, text/plain, */*',
                        'Accept-Language' => 'fr,fr-FR;q=0.9,en-US;q=0.8,en;q=0.7',
                        'Origin' => 'https://www.sofascore.com',
                        'Referer' => 'https://www.sofascore.com/',
                        'Sec-Fetch-Dest' => 'empty',
                        'Sec-Fetch-Mode' => 'cors',
                        'Sec-Fetch-Site' => 'same-origin',
                        'Cache-Control' => 'no-cache',
                        'Pragma' => 'no-cache'
                    ])
                    ->get($url);
                
                $this->line("   📡 Réponse API reçue avec le statut: " . $response->status());
                
                // Si la réponse est réussie, on sort de la boucle
                if ($response->successful()) {
                    $success = true;
                    // Mettre en cache la réponse réussie
                    $this->cacheResponse($url, $response);
                } else {
                    $this->line("   ⚠️ Échec de la requête (statut {$response->status()}), nouvelle tentative...");
                }
            } catch (\Exception $e) {
                $this->line("   ⚠️ Exception lors de la requête: {$e->getMessage()}");
            }
        }
        
        return $response;
    }
    
    private function fetchCountries($sportSlug)
    {
        try {
            $this->line('   🌐 Connexion à l\'API Sofascore...');
            
            $url = "https://www.sofascore.com/api/v1/sport/{$sportSlug}/categories";
            $response = $this->makeRequestWithRetry($url);
            
            if (!$response->successful()) {
                $this->error('   ❌ Erreur lors de la récupération des pays: ' . $response->status());
                Log::error('Échec de la récupération des pays', [
                    'sport_slug' => $sportSlug,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }
            
            $data = $response->json();
            
            if (!isset($data['categories']) || !is_array($data['categories'])) {
                $this->error('   ❌ Format de données invalide reçu de l\'API');
                Log::error('Format de données pays invalide', [
                    'sport_slug' => $sportSlug,
                    'data_keys' => is_array($data) ? array_keys($data) : 'not_an_array',
                    'data_type' => gettype($data)
                ]);
                return [];
            }
            
            return $data['categories'];
            
        } catch (\Exception $e) {
            $this->error('   ❌ Erreur lors de la récupération des pays: ' . $e->getMessage());
            Log::error('Erreur lors de la récupération des pays', [
                'sport_slug' => $sportSlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Récupérer les ligues pour un pays donné
     */
    private function fetchLeaguesForCountry($countryId)
    {
        try {
            $this->line("     🔍 Récupération des ligues pour le pays ID: {$countryId}");
            
            $url = "https://www.sofascore.com/api/v1/category/{$countryId}/unique-tournaments";
            $response = $this->makeRequestWithRetry($url);
            
            if (!$response->successful()) {
                $this->line("     ⚠️  Erreur lors de la récupération des ligues: {$response->status()}");
                Log::warning('Échec de la récupération des ligues', [
                    'country_id' => $countryId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }
            
            $data = $response->json();
            
            if (!isset($data['groups']) || !is_array($data['groups'])) {
                $this->line('     📭 Aucun groupe de ligues trouvé');
                return [];
            }
            
            $allLeagues = [];
            foreach ($data['groups'] as $group) {
                if (isset($group['uniqueTournaments']) && is_array($group['uniqueTournaments'])) {
                    $allLeagues = array_merge($allLeagues, $group['uniqueTournaments']);
                }
            }
            
            return $allLeagues;
            
        } catch (\Exception $e) {
            $this->line("     ❌ Erreur lors de la récupération des ligues: {$e->getMessage()}");
            Log::error('Erreur lors de la récupération des ligues', [
                'country_id' => $countryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Trouver ou créer un pays en base de données
     */
    private function findOrCreateCountry($countryData)
    {
        try {
            // Ignorer les pays "In Progress"
            if (isset($countryData['name']) && $countryData['name'] === 'In Progress') {
                $this->line("     ⏭️  Pays ignoré: {$countryData['name']} (statut temporaire)");
                Log::info('Pays "In Progress" ignoré', [
                    'country_name' => $countryData['name'],
                    'alpha2' => $countryData['alpha2'] ?? null,
                    'slug' => $countryData['slug'] ?? null,
                    'sofascore_id' => $countryData['id']
                ]);
                return null;
            }
            
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
     * Traiter une ligue individuelle
     */
    private function processLeague($leagueData, $country, $sport)
    {
        try {
            $name = $leagueData['name'] ?? 'Ligue inconnue';
            $slug = $leagueData['slug'] ?? null;
            $sofascoreId = $leagueData['id'] ?? null;
            
            if (!$sofascoreId) {
                $this->line("       ⚠️  ID Sofascore manquant pour la ligue: {$name}");
                return 'leagues_skipped';
            }
            
            $this->line("       🏆 Ligue: {$name} (ID: {$sofascoreId})");
            
            // Vérifier si la ligue existe déjà
            $existingLeague = League::where(function($query) use ($sofascoreId, $name, $country, $sport) {
                $query->where('sofascore_id', $sofascoreId)
                      ->orWhere(function($subQuery) use ($name, $country, $sport) {
                          $subQuery->where('name', $name)
                                   ->where('country_id', $country->id)
                                   ->where('sport_id', $sport->id);
                      });
            })->first();
            
            if ($existingLeague && !$this->option('force')) {
                $this->line("         ⏭️  Ligue déjà existante (ID: {$existingLeague->id})");
                Log::info('Ligue déjà existante', [
                    'existing_league_id' => $existingLeague->id,
                    'sofascore_id' => $sofascoreId,
                    'name' => $name,
                    'country_id' => $country->id,
                    'sport_id' => $sport->id
                ]);
                return 'leagues_skipped';
            }
            
            // Créer ou mettre à jour la ligue
            $this->line("         💾 " . ($existingLeague ? 'Mise à jour' : 'Création') . " de la ligue...");
            
            $league = League::updateOrCreate(
                [
                    'sofascore_id' => $sofascoreId
                ],
                [
                    'name' => $name,
                    'slug' => $slug ?: \Illuminate\Support\Str::slug($name),
                    'country_id' => $country->id,
                    'sport_id' => $sport->id
                ]
            );
            
            $action = $existingLeague ? 'updated' : 'created';
            $this->line("         ✅ Ligue {$action} avec succès (ID: {$league->id})");
            
            Log::info('Ligue traitée avec succès', [
                'league_id' => $league->id,
                'sofascore_id' => $sofascoreId,
                'name' => $name,
                'country_id' => $country->id,
                'sport_id' => $sport->id,
                'action' => $action
            ]);
            
            return $existingLeague ? 'leagues_updated' : 'leagues_created';
            
        } catch (\Exception $e) {
            $this->line("         ❌ Erreur lors du traitement de la ligue: {$e->getMessage()}");
            Log::error('Erreur lors du traitement de la ligue', [
                'league_data' => $leagueData,
                'country_id' => $country->id,
                'sport_id' => $sport->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 'errors';
        }
    }
}