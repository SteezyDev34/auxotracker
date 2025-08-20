<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Player;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportPlayersByTeam extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'players:import-by-team {team_id?} {--league-id= : ID de la ligue pour importer toutes les équipes de cette ligue} {--force : Forcer l\'importation même si le joueur existe déjà} {--delay=0 : Délai en secondes entre chaque requête API} {--no-cache : Désactiver le cache}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Importer les joueurs depuis l\'API Sofascore par ID d\'équipe';

    /**
     * Répertoire de cache
     */
    private $cacheDirectory;

    /**
     * Statistiques d'importation
     */
    private $stats = [
        'teams_processed' => 0,
        'players_processed' => 0,
        'players_created' => 0,
        'players_updated' => 0,
        'players_skipped' => 0,
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
        $teamId = $this->argument('team_id');
        $leagueId = $this->option('league-id');
        $force = $this->option('force');
        $delay = (int) $this->option('delay');
        $noCache = $this->option('no-cache');

        $this->line("🚀 Début de l'importation des joueurs par équipe");
        $this->line("🔄 Mode force: " . ($force ? 'Activé' : 'Désactivé'));
        $this->line("💾 Cache: " . ($noCache ? 'Désactivé' : 'Activé'));
        $this->line("⏱️  Délai entre requêtes: {$delay} seconde(s)");
        $this->line("");

        if ($teamId) {
            // Traiter une équipe spécifique
            $team = Team::find($teamId);
            if (!$team) {
                $this->error("❌ Équipe avec l'ID {$teamId} non trouvée");
                return 1;
            }
            $this->processTeam($team, $force, $delay, $noCache);
        } elseif ($leagueId) {
            // Traiter toutes les équipes d'une ligue spécifique
            $league = \App\Models\League::find($leagueId);
            if (!$league) {
                $this->error("❌ Ligue avec l'ID {$leagueId} non trouvée");
                return 1;
            }
            
            $this->line("🏆 Importation pour la ligue: {$league->name}");
            $this->line("");
            
            $teams = Team::where('league_id', $leagueId)
                         ->whereNotNull('sofascore_id')
                         ->get();
            
            if ($teams->isEmpty()) {
                $this->warn("⚠️ Aucune équipe trouvée pour la ligue {$league->name}");
                return 0;
            }
            
            $this->line("📊 {$teams->count()} équipe(s) trouvée(s) dans la ligue {$league->name}");
            $this->line("");
            
            foreach ($teams as $team) {
                $this->processTeam($team, $force, $delay, $noCache);
                $this->stats['teams_processed']++;
                
                if ($delay > 0) {
                    sleep($delay);
                }
            }
        } else {
            // Traiter toutes les équipes
            $teams = Team::whereNotNull('sofascore_id')->get();
            $this->line("📊 Nombre d'équipes à traiter: {$teams->count()}");
            
            foreach ($teams as $team) {
                $this->processTeam($team, $force, $delay, $noCache);
                $this->stats['teams_processed']++;
                
                if ($delay > 0) {
                    sleep($delay);
                }
            }
        }

        $this->displayStats();
        return 0;
    }

    /**
     * Définir le répertoire de cache pour une équipe spécifique
     */
    private function setCacheDirectory($team)
    {
        $teamName = preg_replace('/[^a-zA-Z0-9\-_]/', '-', strtolower($team->name));
        $this->cacheDirectory = storage_path('app/sofascore_cache/teams_players/' . $teamName . '-' . $team->sofascore_id);
        
        if (!file_exists($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0755, true);
        }
    }

    /**
     * Traiter une équipe
     */
    private function processTeam($team, $force, $delay, $noCache)
    {
        try {
            $this->line("⚽ Traitement de l'équipe: {$team->name} (ID: {$team->sofascore_id})");
            $this->line("🏆 Ligue: {$team->league->name}");
            $this->line("🏃 Sport: {$team->league->sport->name} (ID: {$team->league->sport->id})");
            if ($team->league->country) {
                $this->line("🌍 Pays: {$team->league->country->name} ({$team->league->country->code})");
            }
            $this->line("📂 Répertoire de cache: teams_players/{$team->name}-{$team->sofascore_id}");
            
            // Définir le répertoire de cache spécifique à cette équipe
            $this->setCacheDirectory($team);
            
            // Étape 1: Récupérer l'ID de saison depuis la ligue
            $seasonId = $this->getSeasonId($team->league->sofascore_id, $noCache);
            
            if (!$seasonId) {
                $this->error("❌ Impossible de récupérer l'ID de saison pour l'équipe {$team->name}");
                $this->stats['season_not_found']++;
                return;
            }
            
            $this->line("📅 ID de saison trouvé: {$seasonId}");
            
            // Étape 2: Récupérer les joueurs de l'équipe
            $players = $this->getPlayersFromTeam($team->sofascore_id, $team->league->sofascore_id, $seasonId, $noCache);
            
            if (empty($players)) {
                $this->line("⚠️ Aucun joueur trouvé pour l'équipe {$team->name}");
                return;
            }
            
            $this->line("👥 Nombre de joueurs trouvés: " . count($players));
            
            // Étape 3: Traiter chaque joueur
            foreach ($players as $playerData) {
                $this->processPlayer($playerData, $team, $force);
                $this->stats['players_processed']++;
                
                if ($delay > 0) {
                    usleep($delay * 100000); // Délai plus court entre les joueurs
                }
            }
            
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('Erreur lors du traitement de l\'équipe', [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Récupérer l'ID de saison depuis les featured events de la ligue
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
     * Récupérer les joueurs depuis l'API top-players
     */
    private function getPlayersFromTeam($teamSofascoreId, $leagueSofascoreId, $seasonId, $noCache)
    {
        try {
            $url = "https://www.sofascore.com/api/v1/team/{$teamSofascoreId}/unique-tournament/{$leagueSofascoreId}/season/{$seasonId}/top-players/overall";
            $cacheKey = md5($url);
            $cacheFile = $this->cacheDirectory . '/' . $cacheKey . '.json';
            
            // Vérifier le cache
            if (!$noCache && file_exists($cacheFile)) {
                $cacheAge = round((time() - filemtime($cacheFile)) / 3600, 1);
                $this->line("💾 Utilisation du cache pour top-players (âge: {$cacheAge}h)");
                $this->line("📁 Fichier cache: {$cacheFile}");
                $data = json_decode(file_get_contents($cacheFile), true);
            } else {
                $this->line("🌐 Requête API en direct pour top-players");
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
                    Log::warning('Erreur API lors de la récupération des top-players', [
                        'team_sofascore_id' => $teamSofascoreId,
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
            
            // Extraire les joueurs des top-players selon le sport
            $players = [];
            if (isset($data['topPlayers'])) {
                // Pour le football et autres sports : utiliser 'rating'
                if (isset($data['topPlayers']['rating'])) {
                    $this->line("🏃 Structure détectée: Football (rating)");
                    foreach ($data['topPlayers']['rating'] as $playerRating) {
                        if (isset($playerRating['player'])) {
                            $players[] = $playerRating['player'];
                        }
                    }
                }
                // Pour le basketball : utiliser 'points'
                elseif (isset($data['topPlayers']['points'])) {
                    $this->line("🏀 Structure détectée: Basketball (points)");
                    foreach ($data['topPlayers']['points'] as $playerPoints) {
                        if (isset($playerPoints['player'])) {
                            $players[] = $playerPoints['player'];
                        }
                    }
                }
                // Autres structures possibles (assists, rebounds, etc.)
                else {
                    $this->line("🔍 Recherche dans toutes les catégories disponibles...");
                    $categories = ['assists', 'rebounds', 'blocks', 'steals', 'goals', 'saves'];
                    foreach ($categories as $category) {
                        if (isset($data['topPlayers'][$category])) {
                            $this->line("📊 Structure détectée: {$category}");
                            foreach ($data['topPlayers'][$category] as $playerStat) {
                                if (isset($playerStat['player'])) {
                                    // Éviter les doublons
                                    $playerId = $playerStat['player']['id'];
                                    $exists = false;
                                    foreach ($players as $existingPlayer) {
                                        if ($existingPlayer['id'] === $playerId) {
                                            $exists = true;
                                            break;
                                        }
                                    }
                                    if (!$exists) {
                                        $players[] = $playerStat['player'];
                                    }
                                }
                            }
                            break; // Utiliser la première catégorie trouvée
                        }
                    }
                }
            }
            
            return $players;
            
        } catch (\Exception $e) {
            $this->stats['api_errors']++;
            Log::error('Exception lors de la récupération des top-players', [
                'team_sofascore_id' => $teamSofascoreId,
                'league_sofascore_id' => $leagueSofascoreId,
                'season_id' => $seasonId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Traiter un joueur individuel
     */
    private function processPlayer($playerData, $team, $force)
    {
        try {
            $sofascoreId = $playerData['id'] ?? null;
            $name = $playerData['name'] ?? null;
            $slug = $playerData['slug'] ?? null;
            $shortName = $playerData['shortName'] ?? null;
            $position = $playerData['position'] ?? null;
            $userCount = $playerData['userCount'] ?? null;
            $fieldTranslations = $playerData['fieldTranslations'] ?? null;
            
            if (!$sofascoreId || !$name || !$slug) {
                Log::warning("⚠️ Données de joueur incomplètes", [
                    'player_data' => $playerData,
                    'team_id' => $team->id
                ]);
                $this->stats['players_skipped']++;
                return;
            }
            
            // Vérifier si le joueur existe déjà
            $existingPlayer = Player::where('sofascore_id', $sofascoreId)->first();
            
            if ($existingPlayer && !$force) {
                $this->line("⏭️ Joueur ignoré (existe déjà): {$name} (ID: {$sofascoreId})");
                $this->stats['players_skipped']++;
                return;
            }
            
            // Vérification des doublons par nom et slug dans la même équipe
            $duplicateByName = Player::where('name', $name)
                                  ->where('team_id', $team->id)
                                  ->where('sofascore_id', '!=', $sofascoreId)
                                  ->first();
                                  
            $duplicateBySlug = Player::where('slug', $slug)
                                  ->where('team_id', $team->id)
                                  ->where('sofascore_id', '!=', $sofascoreId)
                                  ->first();
            
            if ($duplicateByName || $duplicateBySlug) {
                $this->stats['duplicates_detected']++;
                Log::warning("🔄 Doublon potentiel détecté", [
                    'sofascore_id' => $sofascoreId,
                    'player_name' => $name,
                    'team_id' => $team->id,
                    'duplicate_by_name' => $duplicateByName ? $duplicateByName->id : null,
                    'duplicate_by_slug' => $duplicateBySlug ? $duplicateBySlug->id : null
                ]);
            }
            
            // Créer ou mettre à jour le joueur
            $playerAttributes = [
                'name' => $name,
                'slug' => $slug,
                'short_name' => $shortName,
                'position' => $position,
                'sofascore_id' => $sofascoreId,
                'team_id' => $team->id,
                'user_count' => $userCount,
                'field_translations' => $fieldTranslations
            ];
            
            if ($existingPlayer) {
                $existingPlayer->update($playerAttributes);
                $this->stats['players_updated']++;
                $this->line("🔄 Joueur mis à jour: {$name} (ID: {$sofascoreId}, Slug: {$slug})");
                if ($shortName && $shortName !== $name) {
                    $this->line("   📝 Nom court: {$shortName}");
                }
                if ($position) {
                    $this->line("   🎯 Position: {$position}");
                }
            } else {
                Player::create($playerAttributes);
                $this->stats['players_created']++;
                $this->line("✅ Joueur créé: {$name} (ID: {$sofascoreId}, Slug: {$slug})");
                if ($shortName && $shortName !== $name) {
                    $this->line("   📝 Nom court: {$shortName}");
                }
                if ($position) {
                    $this->line("   🎯 Position: {$position}");
                }
            }
            
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('❌ Erreur lors du traitement du joueur', [
                'player_data' => $playerData,
                'team_id' => $team->id,
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
        $this->line("⚽ Équipes traitées: {$this->stats['teams_processed']}");
        $this->line("🔢 Joueurs traités: {$this->stats['players_processed']}");
        $this->line("✅ Joueurs créés: {$this->stats['players_created']}");
        $this->line("🔄 Joueurs mis à jour: {$this->stats['players_updated']}");
        $this->line("⏭️  Joueurs ignorés: {$this->stats['players_skipped']}");
        $this->line("🔄 Doublons détectés: {$this->stats['duplicates_detected']}");
        $this->line("📅 Saisons non trouvées: {$this->stats['season_not_found']}");
        $this->line("🌐 Erreurs API: {$this->stats['api_errors']}");
        $this->line("❌ Autres erreurs: {$this->stats['errors']}");
        
        $totalPlayers = $this->stats['players_created'] + $this->stats['players_updated'];
        $this->line("📋 Total joueurs ajoutés/modifiés: {$totalPlayers}");
        
        if ($this->stats['players_processed'] > 0) {
            $successRate = round((($totalPlayers) / $this->stats['players_processed']) * 100, 2);
            $this->line("📈 Taux de succès: {$successRate}%");
        }
        
        Log::info('Importation de joueurs par équipe terminée', $this->stats);
    }
}
