<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\League;
use App\Models\Country;
use App\Models\Sport;

class ImportFootballLeagues extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'football:import-leagues {--force : Forcer l\'import même si la ligue existe} {--no-cache : Ne pas utiliser le cache} {--from-cache : Importer depuis le cache local} {--download-logos : Télécharger les logos des ligues} {--delay=0 : Délai en secondes entre chaque requête API}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Importe les pays et leurs ligues de football depuis l\'API Sofascore';

    /**
     * Exécuter la commande console.
     */
    public function handle()
    {
        $this->info('🚀 Début de l\'importation des ligues de football...');

        try {
            // Récupérer tous les pays/catégories depuis l'API ou depuis le cache si demandé
            $this->info('🌍 Récupération des pays et catégories...');
            if ($this->option('from-cache')) {
                $this->line('   💾 Option --from-cache activée : tentative de chargement des pays depuis le cache');
                $possibleFiles = [
                    storage_path('app/sofascore_cache/categories_football.json'),
                    storage_path('app/sofascore_cache/categories.json'),
                    storage_path('app/sofascore_cache/countries.json')
                ];

                $raw = null;
                foreach ($possibleFiles as $f) {
                    if (file_exists($f)) {
                        $this->line("   💾 Chargement du fichier de cache: {$f}");
                        $raw = json_decode(file_get_contents($f), true);
                        break;
                    }
                }

                if (empty($raw)) {
                    $this->error('❌ Aucun fichier de cache trouvé pour les pays. Utilisez --from-cache uniquement si les caches existent.');
                    return Command::FAILURE;
                }

                // Supporter plusieurs formats : soit ['categories' => [...]] soit tableau direct
                if (isset($raw['categories']) && is_array($raw['categories'])) {
                    $countries = $raw['categories'];
                } elseif (isset($raw['data']) && is_array($raw['data'])) {
                    $countries = $raw['data'];
                } elseif (is_array($raw)) {
                    $countries = $raw;
                } else {
                    $this->error('❌ Format de cache invalide pour les pays');
                    return Command::FAILURE;
                }
            } else {
                $countries = $this->fetchCountries();
            }

            if (empty($countries)) {
                $this->error('❌ Aucune catégorie récupérée depuis l\'API');
                return Command::FAILURE;
            }

            // Récupérer l'ID Sofascore du sport depuis la première catégorie
            $footballSofascoreId = $countries[0]['sport']['id'] ?? null;
            if (!$footballSofascoreId) {
                $this->error('❌ ID Sofascore du sport Football non trouvé dans l\'API');
                return Command::FAILURE;
            }

            // Récupérer le sport Football par son sofascore_id
            $footballSport = Sport::where('sofascore_id', $footballSofascoreId)->first();
            if (!$footballSport) {
                $this->error("❌ Sport Football non trouvé (sofascore_id: {$footballSofascoreId})");
                return Command::FAILURE;
            }

            $this->info("⚽ Sport trouvé: {$footballSport->name} (ID: {$footballSport->id}, Sofascore ID: {$footballSport->sofascore_id})");



            $this->info("📋 " . count($countries) . " pays trouvés");

            $stats = [
                'countries_processed' => 0,
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
                    $alpha2 = $countryData['alpha2'] ?? null;
                    $this->line("\n🏴 Traitement du pays: {$countryData['name']} (" . ($alpha2 === null ? 'null' : $alpha2) . ")");

                    // Vérifier si le pays existe en base
                    $country = $this->findOrCreateCountry($countryData);

                    if (!$country) {
                        $this->line("   ⚠️  Pays non trouvé en base: {$countryData['name']}");
                        $stats['errors']++;
                        continue;
                    }

                    $this->line("   ✅ Pays trouvé: {$country->name} (ID: {$country->id})");

                    // Préparer répertoire de cache pour ce pays
                    $countrySlug = isset($countryData['slug']) ? $countryData['slug'] : preg_replace('/[^a-zA-Z0-9\-_]/', '-', strtolower($countryData['name'] ?? 'country'));
                    $cacheDir = storage_path('app/sofascore_cache/leagues_country/' . $countrySlug . '-' . $countryData['id']);
                    if (!file_exists($cacheDir)) {
                        mkdir($cacheDir, 0755, true);
                    }

                    $cacheFile = $cacheDir . '/leagues.json';

                    // Récupérer les ligues pour ce pays (depuis cache si demandé)
                    if ($this->option('from-cache')) {
                        if (!file_exists($cacheFile)) {
                            $this->line("   ⚠️  Cache introuvable pour {$countryData['name']} ({$cacheFile})");
                            $stats['errors']++;
                            continue;
                        }
                        $this->line("   💾 Chargement depuis le cache: {$cacheFile}");
                        $raw = json_decode(file_get_contents($cacheFile), true);
                        if (!isset($raw['groups']) || !is_array($raw['groups'])) {
                            $this->line("   ⚠️  Format de cache invalide pour {$countryData['name']}");
                            $stats['errors']++;
                            continue;
                        }
                        $allLeagues = [];
                        foreach ($raw['groups'] as $group) {
                            if (isset($group['uniqueTournaments']) && is_array($group['uniqueTournaments'])) {
                                $allLeagues = array_merge($allLeagues, $group['uniqueTournaments']);
                            }
                        }
                        $leagues = $allLeagues;
                    } else {
                        $raw = $this->fetchLeaguesForCountry($countryData['id']);
                        if ($raw === null) {
                            $this->line("   📭 Aucune ligue trouvée pour {$countryData['name']}");
                            $stats['countries_processed']++;
                            continue;
                        }

                        // Sauvegarder la réponse brute en cache si autorisé
                        if (!$this->option('no-cache')) {
                            try {
                                file_put_contents($cacheFile, json_encode($raw, JSON_PRETTY_PRINT));
                                $this->line("   💾 Cache sauvegardé: {$cacheFile}");
                            } catch (\Exception $e) {
                                $this->warn("   ⚠️ Impossible d'écrire le cache: {$e->getMessage()}");
                            }
                        }

                        // Extraire toutes les ligues
                        $allLeagues = [];
                        if (isset($raw['groups']) && is_array($raw['groups'])) {
                            foreach ($raw['groups'] as $group) {
                                if (isset($group['uniqueTournaments']) && is_array($group['uniqueTournaments'])) {
                                    $allLeagues = array_merge($allLeagues, $group['uniqueTournaments']);
                                }
                            }
                        }

                        $leagues = $allLeagues;
                    }

                    if (empty($leagues)) {
                        $this->line("   📭 Aucune ligue trouvée pour {$countryData['name']}");
                        $stats['countries_processed']++;
                        continue;
                    }

                    $this->line("   🏆 " . count($leagues) . " ligues trouvées");

                    // Traiter chaque ligue
                    foreach ($leagues as $leagueData) {
                        $result = $this->processLeague($leagueData, $country, $footballSport, $this->option('download-logos'));
                        $stats[$result]++;
                    }

                    $stats['countries_processed']++;
                } catch (\Exception $e) {
                    $this->error("   ❌ Erreur lors du traitement du pays {$countryData['name']}: {$e->getMessage()}");
                    $stats['errors']++;
                    Log::error('Erreur lors du traitement du pays', [
                        'country' => $countryData,
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
            $this->info("✅ Ligues créées: {$stats['leagues_created']}");
            $this->info("🔄 Ligues mises à jour: {$stats['leagues_updated']}");
            $this->info("⏭️  Ligues ignorées: {$stats['leagues_skipped']}");
            $this->info("❌ Erreurs: {$stats['errors']}");

            $totalLeagues = $stats['leagues_created'] + $stats['leagues_updated'] + $stats['leagues_skipped'];
            $this->info("📋 Total ligues traitées: {$totalLeagues}");

            $successRate = $totalLeagues > 0 ? round((($stats['leagues_created'] + $stats['leagues_updated']) / $totalLeagues) * 100, 2) : 0;
            $this->info("📈 Taux de succès: {$successRate}%");

            // Log final
            Log::info('Importation des ligues de football terminée', [
                'countries_processed' => $stats['countries_processed'],
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
            Log::error('Erreur lors de l\'importation des ligues de football', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Récupérer tous les pays/catégories depuis l'API
     */
    private function fetchCountries()
    {
        try {
            $this->line('   🌐 Connexion à l\'API Sofascore...');

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://www.sofascore.com/'
                ])
                ->get('https://www.sofascore.com/api/v1/sport/football/categories');
            $this->line('   📡 Réponse API reçue avec le statut: ' . $response->status());

            if (!$response->successful()) {
                $this->error('   ❌ Erreur lors de la récupération des pays: ' . $response->status());
                Log::error('Échec de la récupération des pays', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();

            if (!isset($data['categories']) || !is_array($data['categories'])) {
                $this->error('   ❌ Format de données invalide reçu de l\'API');
                Log::error('Format de données pays invalide', ['data_keys' => array_keys($data)]);
                return [];
            }

            // Sauvegarder la liste des catégories en cache pour réutilisation ultérieure
            if (!$this->option('no-cache')) {
                try {
                    $cacheDir = storage_path('app/sofascore_cache');
                    if (!file_exists($cacheDir)) {
                        mkdir($cacheDir, 0755, true);
                    }
                    $cacheFile = $cacheDir . '/categories_football.json';
                    file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT));
                    $this->line('   💾 Cache catégories sauvegardé: ' . $cacheFile);
                } catch (\Exception $e) {
                    Log::warning('Impossible d\'écrire le cache des catégories', ['error' => $e->getMessage()]);
                }
            }

            return $data['categories'];
        } catch (\Exception $e) {
            $this->error('   ❌ Erreur lors de la récupération des pays: ' . $e->getMessage());
            Log::error('Erreur lors de la récupération des pays', [
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

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://www.sofascore.com/'
                ])
                ->get("https://www.sofascore.com/api/v1/category/{$countryId}/unique-tournaments");

            $this->line('     📡 Réponse ligues reçue avec le statut: ' . $response->status());

            if (!$response->successful()) {
                $this->line("     ⚠️  Erreur lors de la récupération des ligues: {$response->status()}");
                Log::warning('Échec de la récupération des ligues', [
                    'country_id' => $countryId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            return $data;
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
                // Créer le pays si non trouvé en base
                try {
                    $this->line("");
                    $this->info("�️ Création du pays en base: {$countryData['name']}");

                    $slug = $countryData['slug'] ?? \Illuminate\Support\Str::slug($countryData['name']);
                    $code = $countryData['alpha2'] ?? null;

                    $country = Country::create([
                        'name' => $countryData['name'] ?? 'Unknown',
                        'code' => $code,
                        'slug' => $slug,
                        'img' => null
                    ]);

                    $this->line("   ✅ Pays créé: {$country->name} (ID: {$country->id})");
                    Log::info('Pays créé automatiquement depuis l\'API', [
                        'country_name' => $country->name,
                        'alpha2' => $code,
                        'slug' => $slug
                    ]);
                } catch (\Exception $e) {
                    $this->line("");
                    $this->error("❌ Impossible de créer le pays: {$countryData['name']}");
                    Log::error('Erreur création pays', [
                        'country_data' => $countryData,
                        'error' => $e->getMessage()
                    ]);
                    return null;
                }
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
    private function processLeague($leagueData, $country, $sport, $downloadLogos = false)
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
            $existingLeague = League::where(function ($query) use ($sofascoreId, $name, $country, $sport) {
                $query->where('sofascore_id', $sofascoreId)
                    ->orWhere(function ($subQuery) use ($name, $country, $sport) {
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

            // Télécharger / assurer les logos si demandé
            if ($downloadLogos) {
                try {
                    $logoService = app(\App\Services\LeagueLogoService::class);
                    $logoRes = $logoService->ensureLeagueLogos($league, (bool)$this->option('force'));
                    if ($logoRes && !empty($logoRes['img_updated'])) {
                        $this->line("         📸 Logos téléchargés/mis à jour pour la ligue (ID: {$league->id})");
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur téléchargement logo pour la ligue', [
                        'league_id' => $league->id ?? null,
                        'error' => $e->getMessage()
                    ]);
                }
            }

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
