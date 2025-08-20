<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\TeamLogoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportTennisPlayers extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'tennis:import-players {--force : Forcer l\'importation même si le joueur existe déjà} {--delay=1 : Délai en secondes entre chaque requête API} {--no-cache : Désactiver le cache} {--download-images : Télécharger les images des joueurs}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Importer les joueurs de tennis depuis l\'API des tournois en cours de Sofascore';

    /**
     * Répertoire de cache
     */
    private $cacheDirectory;

    /**
     * Service de téléchargement de logos
     */
    private $logoService;

    /**
     * Statistiques d'importation
     */
    private $stats = [
        'tournaments_processed' => 0,
        'matches_processed' => 0,
        'players_processed' => 0,
        'players_created' => 0,
        'players_updated' => 0,
        'players_skipped' => 0,
        'images_downloaded' => 0,
        'duplicates_detected' => 0,
        'errors' => 0,
        'api_errors' => 0
    ];

    /**
     * Exécuter la commande console.
     */
    public function handle(TeamLogoService $logoService)
    {
        $this->logoService = $logoService;
        $force = $this->option('force');
        $delay = (int) $this->option('delay');
        $noCache = $this->option('no-cache');
        $downloadImages = $this->option('download-images');

        $this->line("🎾 Début de l'importation des joueurs de tennis");
        $this->line("🔄 Mode force: " . ($force ? 'Activé' : 'Désactivé'));
        $this->line("💾 Cache: " . ($noCache ? 'Désactivé' : 'Activé'));
        $this->line("🖼️  Téléchargement d'images: " . ($downloadImages ? 'Activé' : 'Désactivé'));
        $this->line("⏱️  Délai entre requêtes: {$delay} seconde(s)");
        $this->line("");

        // Définir le répertoire de cache
        $this->setCacheDirectory();

        // Récupérer les tournois en cours
        $tournaments = $this->getOngoingTournaments($noCache);

        if (empty($tournaments)) {
            $this->warn('Aucun tournoi de tennis en cours trouvé.');
            return 0;
        }

        $this->line("🏆 Nombre de tournois trouvés: " . count($tournaments));
        
        // Afficher la liste des tournois avant de commencer l'importation
        $this->line("\n📋 Liste des tournois à traiter:");
        foreach ($tournaments as $index => $tournament) {
            $tournamentName = $tournament['tournament']['name'] ?? 'Tournoi inconnu';
            $matchCount = count($tournament['events']);
            $this->line(sprintf("   %d. %s (%d matchs)", $index + 1, $tournamentName, $matchCount));
        }
        
        $this->line("\n🚀 Début de l'importation des joueurs...");

        // Traiter chaque tournoi
        foreach ($tournaments as $tournament) {
            $this->processTournament($tournament, $force, $delay, $noCache, $downloadImages);
            $this->stats['tournaments_processed']++;

            if ($delay > 0) {
                sleep($delay);
            }
        }

        $this->displayStats();
        return 0;
    }

    /**
     * Définir le répertoire de cache
     */
    private function setCacheDirectory()
    {
        $this->cacheDirectory = storage_path('app/sofascore_cache/tennis_players');
        
        if (!file_exists($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0755, true);
        }
    }

    /**
     * Récupérer les tournois de tennis en cours
     */
    private function getOngoingTournaments($noCache)
    {
        try {
            // URL pour récupérer les tournois en cours (sport tennis = 5)
            $currentDate = date('Y-m-d');
            $url = "https://www.sofascore.com/api/v1/sport/tennis/scheduled-events/{$currentDate}";
            $cacheKey = md5($url);
            $cacheFile = $this->cacheDirectory . '/' . $cacheKey . '.json';

            // Vérifier le cache
            if (!$noCache && file_exists($cacheFile)) {
                $cacheAge = round((time() - filemtime($cacheFile)) / 60, 1);
                $this->line("💾 Utilisation du cache pour les tournois (âge: {$cacheAge} min)");
                $data = json_decode(file_get_contents($cacheFile), true);
            } else {
                $this->line("🌐 Requête API en direct pour les tournois en cours");
                $this->line("🔗 URL: {$url}");
                
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://www.sofascore.com/'
                ])->timeout(15)->get($url);
                
                if (!$response->successful()) {
                    if ($response->status() === 403) {
                        $this->handleForbiddenError($response, $url);
                        return [];
                    }
                    
                    $this->stats['api_errors']++;
                    Log::warning('Erreur API lors de la récupération des tournois', [
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
            
            // Extraire les événements de tennis
            $events = $data['events'] ?? [];
            
            // Grouper par tournoi
            $tournaments = [];
            foreach ($events as $event) {
                if (isset($event['tournament']['uniqueTournament']['id'])) {
                    $tournamentId = $event['tournament']['uniqueTournament']['id'];
                    if (!isset($tournaments[$tournamentId])) {
                        $tournaments[$tournamentId] = [
                            'tournament' => $event['tournament'],
                            'events' => []
                        ];
                    }
                    $tournaments[$tournamentId]['events'][] = $event;
                }
            }
            
            return array_values($tournaments);
            
        } catch (\Exception $e) {
            $this->stats['api_errors']++;
            Log::error('Exception lors de la récupération des tournois', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Traiter un tournoi
     */
    private function processTournament($tournamentData, $force, $delay, $noCache, $downloadImages)
    {
        try {
            $tournament = $tournamentData['tournament'];
            $events = $tournamentData['events'];
            
            $tournamentName = $tournament['name'] ?? 'Tournoi inconnu';
            $this->line("\n🏆 Traitement du tournoi: {$tournamentName}");
            $this->line("📊 Nombre de matchs: " . count($events));
            
            // Traiter chaque match
            foreach ($events as $event) {
                $this->processMatch($event, $force, $downloadImages);
                $this->stats['matches_processed']++;
                
                if ($delay > 0) {
                    usleep($delay * 100000); // Délai plus court entre les matchs
                }
            }
            
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('Erreur lors du traitement du tournoi', [
                'tournament' => $tournamentData['tournament']['name'] ?? 'Inconnu',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Traiter un match pour extraire les joueurs
     */
    private function processMatch($event, $force, $downloadImages)
    {
        try {
            // Extraire homeTeam et awayTeam
            $homeTeam = $event['homeTeam'] ?? null;
            $awayTeam = $event['awayTeam'] ?? null;
            
            // Détecter si c'est une compétition en double
            $isDoubles = $this->isDoublesCompetition($event);
            
            if ($homeTeam) {
                $this->processPlayer($homeTeam, $force, $downloadImages, $isDoubles);
            }
            
            if ($awayTeam) {
                $this->processPlayer($awayTeam, $force, $downloadImages, $isDoubles);
            }
            
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('Erreur lors du traitement du match', [
                'event_id' => $event['id'] ?? 'Inconnu',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Détecter si c'est une compétition en double
     */
    private function isDoublesCompetition($event)
    {
        // Vérifier le nom du tournoi ou de la catégorie pour détecter les doubles
        $tournament = $event['tournament'] ?? [];
        $tournamentName = strtolower($tournament['name'] ?? '');
        
        // Rechercher des mots-clés indiquant une compétition en double
        $doublesKeywords = ['doubles', 'double', 'mixed doubles', 'men doubles', 'women doubles'];
        
        foreach ($doublesKeywords as $keyword) {
            if (strpos($tournamentName, $keyword) !== false) {
                return true;
            }
        }
        
        // Vérifier aussi dans la catégorie si elle existe
        $category = $event['category'] ?? [];
        $categoryName = strtolower($category['name'] ?? '');
        
        foreach ($doublesKeywords as $keyword) {
            if (strpos($categoryName, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Traiter un joueur individuel
     */
    private function processPlayer($playerData, $force, $downloadImages, $isDoubles = false)
    {
        try {
            $sofascoreId = $playerData['id'] ?? null;
            $name = $playerData['name'] ?? null;
            $slug = $playerData['slug'] ?? null;
            $shortName = $playerData['shortName'] ?? null;
            $gender = $playerData['gender'] ?? null;
            $country = $playerData['country'] ?? null;
            
            if (!$sofascoreId || !$name || !$slug) {
                Log::warning("⚠️ Données de joueur incomplètes", [
                    'player_data' => $playerData
                ]);
                $this->stats['players_skipped']++;
                return;
            }
            
            $this->stats['players_processed']++;
            
            // Vérifier si le joueur existe déjà
            $existingPlayer = Team::where('sofascore_id', $sofascoreId)->first();
            
            if ($existingPlayer && !$force) {
                $this->line("⏭️ Joueur ignoré (existe déjà): {$name} (ID: {$sofascoreId})");
                $this->stats['players_skipped']++;
                return;
            }
            
            // Vérification des doublons par nom
            $duplicateByName = Team::where('name', $name)
                                  ->whereNull('league_id')
                                  ->where('sofascore_id', '!=', $sofascoreId)
                                  ->first();
            
            if ($duplicateByName) {
                $this->stats['duplicates_detected']++;
                Log::warning("🔄 Doublon potentiel détecté", [
                    'sofascore_id' => $sofascoreId,
                    'player_name' => $name,
                    'duplicate_id' => $duplicateByName->id
                ]);
            }
            
            // Déterminer le gender approprié
            $finalGender = $isDoubles ? 'double' : $gender;
            
            // Créer ou mettre à jour le joueur
            $playerAttributes = [
                'name' => $name,
                'slug' => $slug,
                'nickname' => $shortName,
                'sofascore_id' => $sofascoreId,
                'league_id' => null, // Joueurs de tennis n'ont pas de ligue
                'gender' => $finalGender,
                'country_code' => $country['alpha2'] ?? null
            ];
            
            if ($existingPlayer) {
                $existingPlayer->update($playerAttributes);
                $this->stats['players_updated']++;
                $this->line("🔄 Joueur mis à jour: {$name} (ID: {$sofascoreId}) - Genre: {$finalGender}");
                $player = $existingPlayer;
            } else {
                $player = Team::create($playerAttributes);
                $this->stats['players_created']++;
                $this->line("✅ Joueur créé: {$name} (ID: {$sofascoreId}) - Genre: {$finalGender}");
            }
            
            // Télécharger l'image si demandé
            if ($downloadImages && $player) {
                $this->downloadPlayerImage($player);
            }
            
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('❌ Erreur lors du traitement du joueur', [
                'player_data' => $playerData,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Télécharger l'image d'un joueur
     */
    private function downloadPlayerImage($player)
    {
        try {
            // Vérifier si l'image existe déjà
            if ($player->img && Storage::disk('public')->exists($player->img)) {
                return;
            }
            
            $result = $this->logoService->ensureTeamLogo($player);
            
            if ($result) {
                $this->stats['images_downloaded']++;
                $this->line("📸 Image téléchargée pour: {$player->name}");
            }
            
        } catch (\Exception $e) {
            Log::error('Erreur lors du téléchargement de l\'image', [
                'player_id' => $player->id,
                'player_name' => $player->name,
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
        $this->line("🏆 Tournois traités: {$this->stats['tournaments_processed']}");
        $this->line("🎾 Matchs traités: {$this->stats['matches_processed']}");
        $this->line("🔢 Joueurs traités: {$this->stats['players_processed']}");
        $this->line("✅ Joueurs créés: {$this->stats['players_created']}");
        $this->line("🔄 Joueurs mis à jour: {$this->stats['players_updated']}");
        $this->line("⏭️  Joueurs ignorés: {$this->stats['players_skipped']}");
        $this->line("📸 Images téléchargées: {$this->stats['images_downloaded']}");
        $this->line("🔄 Doublons détectés: {$this->stats['duplicates_detected']}");
        $this->line("🌐 Erreurs API: {$this->stats['api_errors']}");
        $this->line("❌ Autres erreurs: {$this->stats['errors']}");
        
        $totalPlayers = $this->stats['players_created'] + $this->stats['players_updated'];
        $this->line("📋 Total joueurs ajoutés/modifiés: {$totalPlayers}");
        
        if ($this->stats['players_processed'] > 0) {
            $successRate = round((($totalPlayers) / $this->stats['players_processed']) * 100, 2);
            $this->line("📈 Taux de succès: {$successRate}%");
        }
        
        Log::info('Importation de joueurs de tennis terminée', $this->stats);
    }
}