<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportTeamsByLeague extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'teams:import-by-league {league_id?} {--force : Forcer l\'importation même si l\'équipe existe déjà} {--delay=0 : Délai en secondes entre chaque requête API} {--no-cache : Désactiver le cache}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Importer les équipes depuis l\'API Sofascore par ID de ligue';

    /**
     * Répertoire de cache
     */
    private $cacheDirectory;

    /**
     * Statistiques d'importation
     */
    private $stats = [
        'leagues_processed' => 0,
        'teams_processed' => 0,
        'teams_created' => 0,
        'teams_updated' => 0,
        'teams_skipped' => 0,
        'duplicates_detected' => 0,
        'errors' => 0,
        'api_errors' => 0,
        'season_not_found' => 0
    ];

    /**
     * Exécuter la commande console.
     */
    public function handle()
    {
        $leagueId = $this->argument('league_id');
        $force = $this->option('force');
        $delay = (int) $this->option('delay');
        $noCache = $this->option('no-cache');

        $this->line("🚀 Début de l'importation des équipes par ligue");
        $this->line("🔄 Mode force: " . ($force ? 'Activé' : 'Désactivé'));
        $this->line("💾 Cache: " . ($noCache ? 'Désactivé' : 'Activé'));
        $this->line("⏱️  Délai entre requêtes: {$delay} seconde(s)");
        $this->line("");

        if ($leagueId) {
            // Traiter une ligue spécifique
            $league = League::find($leagueId);
            if (!$league) {
                $this->error("❌ Ligue avec l'ID {$leagueId} non trouvée");
                return 1;
            }
            $this->processLeague($league, $force, $delay, $noCache);
        } else {
            // Traiter toutes les ligues (exclure le tennis - sport_id = 2)
            $leagues = League::whereNotNull('sofascore_id')
                           ->whereHas('sport', function($query) {
                               $query->where('id', '!=', 2);
                           })
                           ->get();
            $this->line("📊 Nombre de ligues à traiter: {$leagues->count()}");
            
            foreach ($leagues as $league) {
                $this->processLeague($league, $force, $delay, $noCache);
                $this->stats['leagues_processed']++;
                
                if ($delay > 0) {
                    sleep($delay);
                }
            }
        }

        $this->displayStats();
        return 0;
    }

    /**
     * Définir le répertoire de cache pour une ligue spécifique
     */
    private function setCacheDirectory($league)
    {
        $leagueName = preg_replace('/[^a-zA-Z0-9\-_]/', '-', strtolower($league->name));
        $this->cacheDirectory = storage_path('app/sofascore_cache/leagues_teams/' . $leagueName . '-' . $league->sofascore_id);
        
        if (!file_exists($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0755, true);
        }
    }

    /**
     * Traiter une ligue
     */
    private function processLeague($league, $force, $delay, $noCache)
    {
        try {
            // Vérifier si c'est une ligue de tennis (sport_id = 2) et l'ignorer
            if ($league->sport && $league->sport->id == 2) {
                $this->line("⏭️ Ignorer la ligue de tennis: {$league->name}");
                return;
            }
            
            $this->line("🏆 Traitement de la ligue: {$league->name} (ID: {$league->sofascore_id})");
            $this->line("🏃 Sport: {$league->sport->name} (ID: {$league->sport->id})");
            if ($league->country) {
                $this->line("🌍 Pays: {$league->country->name} ({$league->country->code})");
            }
            $this->line("📂 Répertoire de cache: leagues_teams/{$league->name}-{$league->sofascore_id}");
            
            // Définir le répertoire de cache spécifique à cette ligue
            $this->setCacheDirectory($league);
            
            // Étape 1: Récupérer les featured events pour obtenir l'ID de saison
            $seasonId = $this->getSeasonId($league->sofascore_id, $noCache);
            
            if (!$seasonId) {
                $this->error("❌ Impossible de récupérer l'ID de saison pour la ligue {$league->name}");
                $this->stats['season_not_found']++;
                return;
            }
            
            $this->line("📅 ID de saison trouvé: {$seasonId}");
            
            // Étape 2: Récupérer les standings avec les équipes
            $teams = $this->getTeamsFromStandings($league->sofascore_id, $seasonId, $noCache);
            
            if (empty($teams)) {
                $this->line("⚠️ Aucune équipe trouvée pour la ligue {$league->name}");
                return;
            }
            
            $this->line("👥 Nombre d'équipes trouvées: " . count($teams));
            
            // Étape 3: Traiter chaque équipe
            foreach ($teams as $teamData) {
                $this->processTeam($teamData, $league, $force);
                $this->stats['teams_processed']++;
                
                if ($delay > 0) {
                    usleep($delay * 100000); // Délai plus court entre les équipes
                }
            }
            
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('Erreur lors du traitement de la ligue', [
                'league_id' => $league->id,
                'league_name' => $league->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Récupérer l'ID de saison depuis les featured events
     */
    private function getSeasonId($leagueSofascoreId, $noCache)
    {
        try {
            $url = "https://www.sofascore.com/api/v1/unique-tournament/{$leagueSofascoreId}/featured-events";
            $cacheKey = md5($url);
            $cacheFile = $this->cacheDirectory . '/' . $cacheKey . '.json';
            
            // Vérifier le cache
            if (!$noCache && file_exists($cacheFile)) {
                $cacheAge = round((time() - filemtime($cacheFile)) / 3600, 1);
                $this->line("💾 Utilisation du cache pour featured events (âge: {$cacheAge}h)");
                $this->line("📁 Fichier cache: {$cacheFile}");
                $data = json_decode(file_get_contents($cacheFile), true);
            } else {
                $this->line("🌐 Requête API en direct pour featured events");
                $this->line("🔗 URL: {$url}");
                
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://www.sofascore.com/'
                ])->timeout(10)->get($url);
                
                if (!$response->successful()) {
                    if ($response->status() === 403) {
                        $this->handleForbiddenError($response, $url);
                        return null;
                    }
                    
                    $this->stats['api_errors']++;
                    Log::warning('Erreur API lors de la récupération des featured events', [
                        'league_sofascore_id' => $leagueSofascoreId,
                        'status' => $response->status(),
                        'url' => $url
                    ]);
                    return null;
                }
                
                $data = $response->json();
                
                // Sauvegarder en cache
                if (!$noCache) {
                    file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT));
                    $this->line("💾 Réponse sauvegardée en cache: {$cacheFile}");
                }
            }
            
            // Extraire l'ID de saison du premier événement
            if (isset($data['featuredEvents']) && !empty($data['featuredEvents'])) {
                $firstEvent = $data['featuredEvents'][0];
                return $firstEvent['season']['id'] ?? null;
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->stats['api_errors']++;
            Log::error('Exception lors de la récupération de l\'ID de saison', [
                'league_sofascore_id' => $leagueSofascoreId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Récupérer les équipes depuis les standings
     */
    private function getTeamsFromStandings($leagueSofascoreId, $seasonId, $noCache)
    {
        try {
            $url = "https://www.sofascore.com/api/v1/unique-tournament/{$leagueSofascoreId}/season/{$seasonId}/standings/total";
            $cacheKey = md5($url);
            $cacheFile = $this->cacheDirectory . '/' . $cacheKey . '.json';
            
            // Vérifier le cache
            if (!$noCache && file_exists($cacheFile)) {
                $cacheAge = round((time() - filemtime($cacheFile)) / 3600, 1);
                $this->line("💾 Utilisation du cache pour standings (âge: {$cacheAge}h)");
                $this->line("📁 Fichier cache: {$cacheFile}");
                $data = json_decode(file_get_contents($cacheFile), true);
            } else {
                $this->line("🌐 Requête API en direct pour standings");
                $this->line("🔗 URL: {$url}");
                
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://www.sofascore.com/'
                ])->timeout(10)->get($url);
                
                if (!$response->successful()) {
                    if ($response->status() === 403) {
                        $this->handleForbiddenError($response, $url);
                        return [];
                    }
                    
                    $this->stats['api_errors']++;
                    Log::warning('Erreur API lors de la récupération des standings', [
                        'league_sofascore_id' => $leagueSofascoreId,
                        'season_id' => $seasonId,
                        'status' => $response->status(),
                        'url' => $url
                    ]);
                    return [];
                }
                
                $data = $response->json();
                
                // Sauvegarder en cache
                if (!$noCache) {
                    file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT));
                    $this->line("💾 Réponse sauvegardée en cache: {$cacheFile}");
                }
            }
            
            // Extraire les équipes des standings
            $teams = [];
            if (isset($data['standings']) && !empty($data['standings'])) {
                foreach ($data['standings'] as $standing) {
                    if (isset($standing['rows'])) {
                        foreach ($standing['rows'] as $row) {
                            if (isset($row['team'])) {
                                $teams[] = $row['team'];
                            }
                        }
                    }
                }
            }
            
            return $teams;
            
        } catch (\Exception $e) {
            $this->stats['api_errors']++;
            Log::error('Exception lors de la récupération des standings', [
                'league_sofascore_id' => $leagueSofascoreId,
                'season_id' => $seasonId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Traiter une équipe individuelle
     */
    private function processTeam($teamData, $league, $force)
    {
        try {
            $sofascoreId = $teamData['id'] ?? null;
            $name = $teamData['name'] ?? null;
            $slug = $teamData['slug'] ?? null;
            $shortName = $teamData['shortName'] ?? null;
            
            if (!$sofascoreId || !$name || !$slug) {
                Log::warning("⚠️ Données d'équipe incomplètes", [
                    'team_data' => $teamData,
                    'league_id' => $league->id
                ]);
                $this->stats['teams_skipped']++;
                return;
            }
            
            // Vérifier si l'équipe existe déjà
            $existingTeam = Team::where('sofascore_id', $sofascoreId)->first();
            
            if ($existingTeam && !$force) {
                $this->line("⏭️ Équipe ignorée (existe déjà): {$name} (ID: {$sofascoreId})");
                $this->stats['teams_skipped']++;
                return;
            }
            
            // Vérification des doublons par nom et slug dans la même ligue
            $duplicateByName = Team::where('name', $name)
                                  ->where('league_id', $league->id)
                                  ->where('sofascore_id', '!=', $sofascoreId)
                                  ->first();
                                  
            $duplicateBySlug = Team::where('slug', $slug)
                                  ->where('league_id', $league->id)
                                  ->where('sofascore_id', '!=', $sofascoreId)
                                  ->first();
            
            if ($duplicateByName || $duplicateBySlug) {
                $this->stats['duplicates_detected']++;
                Log::warning("🔄 Doublon potentiel détecté", [
                    'sofascore_id' => $sofascoreId,
                    'team_name' => $name,
                    'league_id' => $league->id,
                    'duplicate_by_name' => $duplicateByName ? $duplicateByName->id : null,
                    'duplicate_by_slug' => $duplicateBySlug ? $duplicateBySlug->id : null
                ]);
            }
            
            // Créer ou mettre à jour l'équipe
            $teamAttributes = [
                'name' => $name,
                'slug' => $slug,
                'nickname' => $shortName,
                'sofascore_id' => $sofascoreId,
                'league_id' => $league->id
            ];
            
            if ($existingTeam) {
                $existingTeam->update($teamAttributes);
                $this->stats['teams_updated']++;
                $this->line("🔄 Équipe mise à jour: {$name} (ID: {$sofascoreId}, Slug: {$slug})");
                if ($shortName && $shortName !== $name) {
                    $this->line("   📝 Nom court: {$shortName}");
                }
            } else {
                Team::create($teamAttributes);
                $this->stats['teams_created']++;
                $this->line("✅ Équipe créée: {$name} (ID: {$sofascoreId}, Slug: {$slug})");
                if ($shortName && $shortName !== $name) {
                    $this->line("   📝 Nom court: {$shortName}");
                }
            }
            
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('❌ Erreur lors du traitement de l\'équipe', [
                'team_data' => $teamData,
                'league_id' => $league->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gérer les erreurs 403
     */
    private function handleForbiddenError($response, $url)
    {
        $responseBody = $response->json();
        $challengeType = $responseBody['error']['reason'] ?? 'unknown';
        
        $this->error("🚨 ERREUR 403 - Accès interdit");
        $this->error("🔍 Type de challenge détecté: {$challengeType}");
        $this->error("💡 Suggestions:");
        $this->error("   - Attendre quelques minutes avant de relancer");
        $this->error("   - Utiliser un VPN ou changer d'IP");
        $this->error("   - Réduire la fréquence des requêtes");
        $this->error("🛑 Arrêt du script en raison de l'erreur 403");
        
        Log::error('🚨 Erreur 403 - Challenge détecté', [
            'status' => $response->status(),
            'url' => $url,
            'challenge_type' => $challengeType,
            'response_body' => $responseBody
        ]);
        
        exit(1);
    }

    /**
     * Afficher les statistiques d'importation
     */
    private function displayStats()
    {
        $this->line("\n🏁 Importation terminée!\n");
        $this->line("📊 === Statistiques d'importation ===");
        $this->line("🏆 Ligues traitées: {$this->stats['leagues_processed']}");
        $this->line("🔢 Équipes traitées: {$this->stats['teams_processed']}");
        $this->line("✅ Équipes créées: {$this->stats['teams_created']}");
        $this->line("🔄 Équipes mises à jour: {$this->stats['teams_updated']}");
        $this->line("⏭️  Équipes ignorées: {$this->stats['teams_skipped']}");
        $this->line("🔄 Doublons détectés: {$this->stats['duplicates_detected']}");
        $this->line("📅 Saisons non trouvées: {$this->stats['season_not_found']}");
        $this->line("🌐 Erreurs API: {$this->stats['api_errors']}");
        $this->line("❌ Autres erreurs: {$this->stats['errors']}");
        
        $totalTeams = $this->stats['teams_created'] + $this->stats['teams_updated'];
        $this->line("📋 Total équipes ajoutées/modifiées: {$totalTeams}");
        
        if ($this->stats['teams_processed'] > 0) {
            $successRate = round((($totalTeams) / $this->stats['teams_processed']) * 100, 2);
            $this->line("📈 Taux de succès: {$successRate}%");
        }
        
        Log::info('Importation d\'équipes par ligue terminée', $this->stats);
    }
}