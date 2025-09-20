<?php

namespace App\Console\Commands;

use App\Models\Team;
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
    protected $signature = 'tennis:cache-players {--delay=1 : Délai en secondes entre chaque requête API} {--no-cache : Désactiver le cache} {--force : Forcer la récupération des données en ignorant le cache existant} {--export-data : Exporter les données collectées pour synchronisation} {--limit= : Limiter le nombre de joueurs à collecter} {--download-images : Télécharger les images des joueurs pendant la mise en cache}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Collecter et mettre en cache les données des joueurs de tennis depuis l\'API Sofascore sans les persister en base. Utiliser --force pour ignorer le cache existant.';

    /**
     * Répertoire de cache
     */
    private $cacheDirectory;

    /**
     * Statistiques d'importation avec cache intelligent
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
        'api_errors' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'cache_size_mb' => 0,
        'cache_files_cleaned' => 0
    ];

    /**
     * Exécuter la commande console.
     */
    public function handle()
    {
        $delay = (int) $this->option('delay');
        $noCache = $this->option('no-cache');
        $force = $this->option('force');
        $exportData = $this->option('export-data');
        $downloadImages = $this->option('download-images');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->line("🎾 Début de la collecte des données des joueurs de tennis");
        $this->line("💾 Cache: " . ($noCache ? 'Désactivé' : 'Activé'));
        $this->line("🔄 Mode force: " . ($force ? 'Activé (ignore le cache)' : 'Désactivé'));
        $this->line("📤 Export des données: " . ($exportData ? 'Activé' : 'Désactivé'));
        $this->line("📸 Téléchargement d'images: " . ($downloadImages ? 'Activé' : 'Désactivé'));
        $this->line("⏱️ Délai entre requêtes: {$delay} seconde(s)");
        if ($limit) {
            $this->line("🔢 Limite de joueurs: {$limit}");
        }
        $this->line("");

        // Définir le répertoire de cache
        $this->setCacheDirectory();

        // Nettoyage automatique du cache expiré (une fois par jour)
        if (!$noCache) {
            $this->cleanExpiredCache();
            $this->calculateCacheStats();
        }

        // Récupérer les tournois en cours
        $tournaments = $this->getOngoingTournaments($noCache, $force);

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
        
        $this->line("\n🚀 Début de la collecte des joueurs...");

        // Traiter chaque tournoi
        foreach ($tournaments as $tournament) {
            // Vérifier si la limite est atteinte
            if ($limit && $this->stats['players_processed'] >= $limit) {
                $this->line("🔢 Limite de {$limit} joueurs atteinte, arrêt de la collecte.");
                break;
            }

            $this->processTournament($tournament, $delay, $noCache, $force, $downloadImages, $limit);
            $this->stats['tournaments_processed']++;

            if ($delay > 0) {
                sleep($delay);
            }
        }

        // Export des données si demandé
        if ($exportData) {
            $this->exportImportedData();
        }

        $this->displayStats();
        return 0;
    }

    /**
     * Définir le répertoire de cache avec structure hiérarchique
     */
    private function setCacheDirectory()
    {
        $this->cacheDirectory = storage_path('app/sofascore_cache/tennis_players');
        
        // Créer la structure de cache hiérarchique
        $subdirs = [
            'tournaments',    // Cache des tournois
            'players',       // Cache des joueurs
            'players/logos', // Images des joueurs
            'metadata',      // Cache des métadonnées
            'compressed'     // Cache compressé pour les gros volumes
        ];
        
        foreach ($subdirs as $subdir) {
            $path = $this->cacheDirectory . '/' . $subdir;
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Système de cache intelligent avec TTL adaptatif
     */
    private function getSmartCache($url, $cacheType = 'default', $force = false)
    {
        // Si force est activé, ignorer complètement le cache
        if ($force) {
            $this->line("🔄 Mode force activé - cache ignoré pour {$cacheType}");
            $this->stats['cache_misses']++;
            return null;
        }
        
        $cacheKey = $this->generateSmartCacheKey($url, $cacheType);
        $cacheFile = $this->getCacheFilePath($cacheKey, $cacheType);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = $this->readCacheFile($cacheFile);
        if (!$cacheData) {
            return null;
        }
        
        // Vérifier la validité du cache avec TTL adaptatif
        if ($this->isCacheValid($cacheData, $cacheType)) {
            $age = round((time() - $cacheData['timestamp']) / 60, 1);
            $this->line("💾 Cache intelligent utilisé (âge: {$age}min, type: {$cacheType})");
            $this->stats['cache_hits']++;
            return $cacheData['data'];
        }
        
        $this->stats['cache_misses']++;
        return null;
    }

    /**
     * Sauvegarder dans le cache intelligent
     */
    private function setSmartCache($url, $data, $cacheType = 'default')
    {
        $cacheKey = $this->generateSmartCacheKey($url, $cacheType);
        $cacheFile = $this->getCacheFilePath($cacheKey, $cacheType);
        
        $cacheData = [
            'timestamp' => time(),
            'url' => $url,
            'type' => $cacheType,
            'data' => $data,
            'size' => strlen(json_encode($data)),
            'checksum' => md5(json_encode($data))
        ];
        
        $this->writeCacheFile($cacheFile, $cacheData, $cacheType);
        
        $size = round($cacheData['size'] / 1024, 2);
        $this->line("💾 Cache intelligent sauvegardé ({$size}KB, type: {$cacheType})");
    }

    /**
     * Générer une clé de cache intelligente
     */
    private function generateSmartCacheKey($url, $cacheType)
    {
        $baseKey = md5($url);
        $date = date('Y-m-d');
        
        // Clés différentes selon le type de cache
        switch ($cacheType) {
            case 'tournaments':
                return "tournaments_{$date}_{$baseKey}";
            case 'players':
                return "players_{$baseKey}";
            case 'metadata':
                return "meta_{$date}_{$baseKey}";
            default:
                return "default_{$baseKey}";
        }
    }

    /**
     * Obtenir le chemin du fichier de cache
     */
    private function getCacheFilePath($cacheKey, $cacheType)
    {
        $subdir = in_array($cacheType, ['tournaments', 'players', 'metadata']) ? $cacheType : 'default';
        return $this->cacheDirectory . '/' . $subdir . '/' . $cacheKey . '.cache';
    }

    /**
     * Vérifier la validité du cache avec TTL adaptatif
     */
    private function isCacheValid($cacheData, $cacheType)
    {
        $age = time() - $cacheData['timestamp'];
        
        // TTL adaptatif selon le type de données
        $ttl = match($cacheType) {
            'tournaments' => 3600,      // 1 heure pour les tournois
            'players' => 86400 * 7,     // 7 jours pour les joueurs
            'metadata' => 1800,         // 30 minutes pour les métadonnées
            default => 3600             // 1 heure par défaut
        };
        
        return $age < $ttl;
    }

    /**
     * Lire un fichier de cache avec décompression si nécessaire
     */
    private function readCacheFile($cacheFile)
    {
        try {
            $content = file_get_contents($cacheFile);
            
            // Détecter si le contenu est compressé
            if (substr($content, 0, 2) === "\x1f\x8b") {
                $content = gzuncompress($content);
            }
            
            return json_decode($content, true);
        } catch (\Exception $e) {
            Log::warning('Erreur lecture cache', ['file' => $cacheFile, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Écrire un fichier de cache avec compression si nécessaire
     */
    private function writeCacheFile($cacheFile, $cacheData, $cacheType)
    {
        try {
            $content = json_encode($cacheData, JSON_PRETTY_PRINT);
            
            // Compresser les gros fichiers (> 50KB)
            if (strlen($content) > 51200) {
                $content = gzcompress($content, 6);
                $this->line("🗜️ Cache compressé pour économiser l'espace");
            }
            
            file_put_contents($cacheFile, $content);
        } catch (\Exception $e) {
            Log::warning('Erreur écriture cache', ['file' => $cacheFile, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Cache de métadonnées pour éviter les requêtes inutiles
     */
    private function getMetadataCache($key)
    {
        $metaFile = $this->cacheDirectory . '/metadata/' . md5($key) . '.meta';
        
        if (file_exists($metaFile)) {
            $metadata = json_decode(file_get_contents($metaFile), true);
            
            // Vérifier la validité (30 minutes)
            if (time() - $metadata['timestamp'] < 1800) {
                return $metadata['data'];
            }
        }
        
        return null;
    }

    /**
     * Sauvegarder les métadonnées
     */
    private function setMetadataCache($key, $data)
    {
        $metaFile = $this->cacheDirectory . '/metadata/' . md5($key) . '.meta';
        
        $metadata = [
            'timestamp' => time(),
            'key' => $key,
            'data' => $data
        ];
        
        file_put_contents($metaFile, json_encode($metadata));
    }

    /**
     * Nettoyer le cache expiré
     */
    private function cleanExpiredCache()
    {
        $this->line("🧹 Nettoyage du cache expiré...");
        $cleaned = 0;
        
        $directories = ['tournaments', 'players', 'metadata', 'default'];
        
        foreach ($directories as $dir) {
            $path = $this->cacheDirectory . '/' . $dir;
            if (!is_dir($path)) continue;
            
            $files = glob($path . '/*.{cache,meta}', GLOB_BRACE);
            
            foreach ($files as $file) {
                if (time() - filemtime($file) > 86400 * 7) { // 7 jours
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        if ($cleaned > 0) {
            $this->line("🗑️ {$cleaned} fichiers de cache expirés supprimés");
            $this->stats['cache_files_cleaned'] = $cleaned;
        }
    }

    /**
     * Calculer les statistiques du cache
     */
    private function calculateCacheStats()
    {
        $totalSize = 0;
        $directories = ['tournaments', 'players', 'metadata', 'default'];
        
        foreach ($directories as $dir) {
            $path = $this->cacheDirectory . '/' . $dir;
            if (!is_dir($path)) continue;
            
            $files = glob($path . '/*.{cache,meta}', GLOB_BRACE);
            
            foreach ($files as $file) {
                $totalSize += filesize($file);
            }
        }
        
        $this->stats['cache_size_mb'] = round($totalSize / (1024 * 1024), 2);
        
        if ($this->stats['cache_size_mb'] > 0) {
            $this->line("📊 Taille du cache: {$this->stats['cache_size_mb']} MB");
        }
    }



    /**
     * Créer une requête HTTP simple
     */
    private function makeHttpRequest($url)
    {
        try {
            // Headers de base simples
            $headers = [
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Referer' => 'https://www.sofascore.com/',
                'Origin' => 'https://www.sofascore.com',
                'Connection' => 'keep-alive',
            ];
            
            $this->line("🌐 Requête HTTP vers: {$url}");
            
            // Configuration HTTP simple
            $httpClient = Http::withHeaders($headers)
                ->timeout(30);
            
            $options = [
                'verify' => false,
                'allow_redirects' => true,
                'http_errors' => false,
            ];
            
            $response = $httpClient->withOptions($options)->get($url);
            
            if ($response->successful()) {
                $this->line("✅ Requête réussie (statut: {$response->status()})");
                return $response;
            } else {
                $this->warn("⚠️ Erreur HTTP {$response->status()} pour: {$url}");
                return null;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Exception lors de la requête: {$e->getMessage()}");
            return null;
        }
    }











    /**
     * Récupérer les tournois de tennis en cours avec cache intelligent
     */
    private function getOngoingTournaments($noCache, $force = false)
    {
        try {
            // URL pour récupérer les tournois en cours (sport tennis = 5)
            $currentDate = date('Y-m-d');
            $url = "https://www.sofascore.com/api/v1/sport/tennis/scheduled-events/{$currentDate}";
            
            // Vérifier d'abord les métadonnées pour éviter les requêtes inutiles (sauf si force)
            if (!$force) {
                $metaKey = "tournaments_count_{$currentDate}";
                $cachedMeta = $this->getMetadataCache($metaKey);
                
                if ($cachedMeta && $cachedMeta['count'] === 0) {
                    $this->line("📊 Métadonnées: Aucun tournoi aujourd'hui (cache)");
                    return [];
                }
            }

            // Vérifier le cache intelligent
            if (!$noCache) {
                $cachedData = $this->getSmartCache($url, 'tournaments', $force);
                if ($cachedData !== null) {
                    $data = $cachedData;
                } else {
                    $data = $this->fetchTournamentsFromAPI($url, $noCache);
                }
            } else {
                $data = $this->fetchTournamentsFromAPI($url, $noCache);
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
            
            $tournamentsList = array_values($tournaments);
            
            // Sauvegarder les métadonnées pour optimiser les futures requêtes
            $metaKey = "tournaments_count_{$currentDate}";
            $this->setMetadataCache($metaKey, [
                'count' => count($tournamentsList),
                'last_check' => time(),
                'has_events' => count($events) > 0
            ]);
            
            return $tournamentsList;
            
        } catch (\Exception $e) {
            $this->stats['api_errors']++;
            Log::error('Exception lors de la récupération des tournois', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Récupérer les tournois depuis l'API avec gestion d'erreur intelligente
     */
    private function fetchTournamentsFromAPI($url, $noCache)
    {
        $this->line("🌐 Requête API en direct pour les tournois en cours");
        
        $response = $this->makeHttpRequest($url);
        
        if (!$response) {
            $this->stats['api_errors']++;
            Log::warning('Erreur API lors de la récupération des tournois', [
                'url' => $url
            ]);
            
            // En cas d'erreur, essayer de récupérer un cache expiré comme fallback
            $expiredCache = $this->getExpiredCacheAsFallback($url, 'tournaments');
            if ($expiredCache) {
                $this->warn("⚠️ Utilisation du cache expiré comme fallback");
                return $expiredCache;
            }
            
            return [];
        }
        
        $data = $response->json();
        
        // Sauvegarder en cache intelligent
        if (!$noCache) {
            $this->setSmartCache($url, $data, 'tournaments');
        }
        
        return $data;
    }

    /**
     * Récupérer un cache expiré comme fallback en cas d'erreur API
     */
    private function getExpiredCacheAsFallback($url, $cacheType)
    {
        $cacheKey = $this->generateSmartCacheKey($url, $cacheType);
        $cacheFile = $this->getCacheFilePath($cacheKey, $cacheType);
        
        if (file_exists($cacheFile)) {
            $cacheData = $this->readCacheFile($cacheFile);
            if ($cacheData && isset($cacheData['data'])) {
                $age = round((time() - $cacheData['timestamp']) / 3600, 1);
                $this->line("🕰️ Cache expiré utilisé comme fallback (âge: {$age}h)");
                return $cacheData['data'];
            }
        }
        
        return null;
    }

    /**
     * Traiter un tournoi
     */
    private function processTournament($tournamentData, $delay, $noCache, $force, $downloadImages, $limit = null)
    {
        try {
            $tournament = $tournamentData['tournament'];
            $events = $tournamentData['events'];
            
            $tournamentName = $tournament['name'] ?? 'Tournoi inconnu';
            $this->line("\n🏆 Traitement du tournoi: {$tournamentName}");
            $this->line("📊 Nombre de matchs: " . count($events));
            
            // Traiter chaque match
            foreach ($events as $event) {
                // Vérifier si la limite est atteinte
                if ($limit && $this->stats['players_processed'] >= $limit) {
                    $this->line("🔢 Limite de {$limit} joueurs atteinte dans le tournoi {$tournamentName}.");
                    break;
                }

                $this->processMatch($event, $noCache, $force, $downloadImages, $limit);
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
    private function processMatch($event, $noCache, $force, $downloadImages, $limit = null)
    {
        try {
            // Extraire homeTeam et awayTeam
            $homeTeam = $event['homeTeam'] ?? null;
            $awayTeam = $event['awayTeam'] ?? null;
            
            // Détecter si c'est une compétition en double
            $isDoubles = $this->isDoublesCompetition($event);
            
            if ($homeTeam && (!$limit || $this->stats['players_processed'] < $limit)) {
                $this->cachePlayerData($homeTeam, $isDoubles, $noCache, $force, $downloadImages);
            }
            
            if ($awayTeam && (!$limit || $this->stats['players_processed'] < $limit)) {
                $this->cachePlayerData($awayTeam, $isDoubles, $noCache, $force, $downloadImages);
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
     * Collecter et mettre en cache les données d'un joueur
     */
    private function cachePlayerData($playerData, $isDoubles = false, $noCache = false, $force = false, $downloadImages = false)
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
            
            // Déterminer le gender approprié
            $finalGender = $isDoubles ? 'double' : $gender;
            
            // Préparer les données de base du joueur
            $playerBasicData = [
                'name' => $name,
                'slug' => $slug,
                'nickname' => $shortName,
                'sofascore_id' => $sofascoreId,
                'league_id' => ($finalGender == 'M') ? 26535 : ($finalGender == 'F' ? 28924 : ($finalGender == 'double' ? 26534 : null)),
                'gender' => $finalGender,
                'country_code' => $country['alpha2'] ?? null
            ];
            
            // Mettre en cache les données de base
            $this->cacheBasicPlayerData($playerBasicData);
            
            // Récupérer et mettre en cache les détails complets du joueur
            $this->fetchAndCachePlayerDetails($sofascoreId, $noCache, $force);
            
            // Télécharger l'image du joueur si l'option est activée
            if ($downloadImages) {
                $this->downloadPlayerImage($sofascoreId, $name);
            }
            
            $this->line("📦 Données collectées et mises en cache: {$name} (ID: {$sofascoreId}) - Genre: {$finalGender}");
            
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('❌ Erreur lors de la collecte des données du joueur', [
                'player_data' => $playerData,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Mettre en cache les données de base d'un joueur
     */
    private function cacheBasicPlayerData($playerData)
    {
        $sofascoreId = $playerData['sofascore_id'];
        $cacheKey = "player_basic_{$sofascoreId}";
        $cacheFile = $this->cacheDirectory . '/players/' . $cacheKey . '.json';
        $metadataFile = $this->cacheDirectory . '/metadata/' . md5($cacheKey) . '.meta';
        
        // Créer les répertoires si nécessaire
        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0755, true);
        }
        if (!is_dir(dirname($metadataFile))) {
            mkdir(dirname($metadataFile), 0755, true);
        }
        
        // Sauvegarder les données de base
        file_put_contents($cacheFile, json_encode($playerData, JSON_PRETTY_PRINT));
        
        // Sauvegarder les métadonnées
        $metadata = [
            'cache_key' => $cacheKey,
            'created_at' => now()->toISOString(),
            'type' => 'player_basic',
            'sofascore_id' => $sofascoreId
        ];
        file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Récupérer et mettre en cache les détails complets d'un joueur
     */
    private function fetchAndCachePlayerDetails($sofascoreId, $noCache = false, $force = false)
    {
        try {
            $url = "https://www.sofascore.com/api/v1/team/{$sofascoreId}";
            $cacheKey = "player_details_{$sofascoreId}";
            $cacheFile = $this->cacheDirectory . '/players/' . $cacheKey . '.json';
            $metadataFile = $this->cacheDirectory . '/metadata/' . md5($cacheKey) . '.meta';
            
            // Créer les répertoires s'ils n'existent pas
            $playersDir = $this->cacheDirectory . '/players';
            $metadataDir = $this->cacheDirectory . '/metadata';
            if (!is_dir($playersDir)) {
                mkdir($playersDir, 0755, true);
            }
            if (!is_dir($metadataDir)) {
                mkdir($metadataDir, 0755, true);
            }
            
            $playerDetails = null;
            $fromCache = false;
            
            // Ignorer le cache si force est activé
            if ($force) {
                $this->line("🔄 Mode force activé - Ignorer le cache pour le joueur ID: {$sofascoreId}");
            }
            
            // Vérifier le cache
            if (!$noCache && !$force && file_exists($cacheFile) && file_exists($metadataFile)) {
                $metadata = json_decode(file_get_contents($metadataFile), true);
                $cacheAge = time() - $metadata['timestamp'];
                
                // Cache valide pendant 7 jours pour les détails des joueurs
                if ($cacheAge < (7 * 24 * 3600)) {
                    $playerDetails = json_decode(file_get_contents($cacheFile), true);
                    $fromCache = true;
                    $this->stats['cache_hits']++;
                    $playerName = $metadata['player_name'] ?? "ID: {$sofascoreId}";
                    $this->line("💾 Détails du joueur depuis le cache: {$playerName} (âge: " . round($cacheAge/3600, 1) . "h)");
                }
            }
            
            // Récupérer depuis l'API si pas en cache
            if (!$playerDetails) {
                $this->stats['cache_misses']++;
                $this->line("🌐 Récupération des détails du joueur ID: {$sofascoreId}");
                
                $response = $this->makeHttpRequest($url, 3);
                
                if ($response && $response->successful()) {
                    $playerDetails = $response->json();
                    $playerName = $playerDetails['team']['name'] ?? "ID: {$sofascoreId}";
                    
                    // Sauvegarder en cache
                    $cacheWritten = file_put_contents($cacheFile, json_encode($playerDetails, JSON_PRETTY_PRINT));
                    $this->line("🗂️ Cache écrit: {$cacheFile} ({$cacheWritten} bytes)");
                    
                    // Sauvegarder les métadonnées
                    $metadata = [
                        'timestamp' => time(),
                        'url' => $url,
                        'player_id' => $sofascoreId,
                        'player_name' => $playerName
                    ];
                    $metaWritten = file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
                    $this->line("📋 Metadata écrite: {$metadataFile} ({$metaWritten} bytes)");
                    
                    $this->line("✅ Détails du joueur récupérés et mis en cache: {$playerName}");
                } else {
                    $this->warn("⚠️ Impossible de récupérer les détails du joueur ID: {$sofascoreId}");
                    return;
                }
            }
            
            // Les détails sont maintenant en cache, pas besoin de traitement en base ici
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des détails du joueur', [
                'sofascore_id' => $sofascoreId,
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
     * Exporter les données importées pour synchronisation
     */
    private function exportImportedData()
    {
        $this->line("\n📤 Export des données importées...");
        
        try {
            $exportDir = storage_path('app/tennis_exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            
            // Export des équipes (joueurs de tennis) modifiées récemment
            $teams = Team::where('updated_at', '>=', now()->subHours(24))
                         ->orWhere('created_at', '>=', now()->subHours(24))
                         ->get()
                         ->toArray();
            
            $teamsFile = $exportDir . "/teams_export_{$timestamp}.json";
            file_put_contents($teamsFile, json_encode($teams, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Export des joueurs modifiés récemment
            $players = \App\Models\Player::whereHas('team', function($query) {
                $query->where('updated_at', '>=', now()->subHours(24))
                      ->orWhere('created_at', '>=', now()->subHours(24));
            })->get()->toArray();
            
            $playersFile = $exportDir . "/players_export_{$timestamp}.json";
            file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Créer un fichier de métadonnées
            $metadata = [
                'export_date' => now()->toISOString(),
                'stats' => $this->stats,
                'files' => [
                    'teams' => basename($teamsFile),
                    'players' => basename($playersFile)
                ],
                'counts' => [
                    'teams' => count($teams),
                    'players' => count($players)
                ]
            ];
            
            $metadataFile = $exportDir . "/export_metadata_{$timestamp}.json";
            file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $this->line("✅ Export terminé:");
            $this->line("   📁 Répertoire: {$exportDir}");
            $this->line("   👥 Équipes exportées: " . count($teams));
            $this->line("   🎾 Joueurs exportés: " . count($players));
            $this->line("   📋 Métadonnées: {$metadataFile}");
            
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de l'export: " . $e->getMessage());
            Log::error('Erreur export tennis data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Télécharger l'image d'un joueur
     */
    private function downloadPlayerImage($sofascoreId, $playerName)
    {
        try {
            $imageUrl = "https://api.sofascore.com/api/v1/team/{$sofascoreId}/image";
            $this->line("📸 Téléchargement de l'image: {$playerName} (ID: {$sofascoreId})");
            
            $response = $this->makeHttpRequest($imageUrl);
            
            if (!$response || !$response->successful()) {
                $this->warn("⚠️ Échec du téléchargement de l'image pour: {$playerName}");
                return false;
            }
            
            // Définir le chemin de l'image avec le sofascore_id comme nom
            $logoPath = $this->cacheDirectory . '/players/logos/' . $sofascoreId . '.png';
            
            // Créer le répertoire s'il n'existe pas
            $logoDir = dirname($logoPath);
            if (!file_exists($logoDir)) {
                mkdir($logoDir, 0755, true);
            }
            
            // Sauvegarder l'image
            file_put_contents($logoPath, $response->body());
            
            $this->line("✅ Image sauvegardée: {$logoPath}");
            $this->stats['images_downloaded']++;
            
            Log::info("Image téléchargée pour le joueur {$playerName}", [
                'sofascore_id' => $sofascoreId,
                'path' => $logoPath,
                'image_url' => $imageUrl
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors du téléchargement de l'image pour {$playerName}: {$e->getMessage()}");
            Log::error("Échec du téléchargement de l'image", [
                'sofascore_id' => $sofascoreId,
                'player_name' => $playerName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
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
        
        // Statistiques de cache intelligent
        $this->line("\n💾 === Statistiques de cache intelligent ===");
        $this->line("✅ Cache hits: {$this->stats['cache_hits']}");
        $this->line("❌ Cache misses: {$this->stats['cache_misses']}");
        $this->line("📦 Taille du cache: {$this->stats['cache_size_mb']} MB");
        $this->line("🗑️ Fichiers nettoyés: {$this->stats['cache_files_cleaned']}");
        
        $totalCacheRequests = $this->stats['cache_hits'] + $this->stats['cache_misses'];
        if ($totalCacheRequests > 0) {
            $cacheHitRate = round(($this->stats['cache_hits'] / $totalCacheRequests) * 100, 2);
            $this->line("📈 Taux de cache hit: {$cacheHitRate}%");
            
            $apiRequestsSaved = $this->stats['cache_hits'];
            $this->line("🚀 Requêtes API économisées: {$apiRequestsSaved}");
        }
        
        $totalPlayers = $this->stats['players_created'] + $this->stats['players_updated'];
        $this->line("\n📋 Total joueurs ajoutés/modifiés: {$totalPlayers}");
        
        if ($this->stats['players_processed'] > 0) {
            $successRate = round((($totalPlayers) / $this->stats['players_processed']) * 100, 2);
            $this->line("📈 Taux de succès: {$successRate}%");
        }
        
        Log::info('Importation de joueurs de tennis terminée', $this->stats);
    }
}