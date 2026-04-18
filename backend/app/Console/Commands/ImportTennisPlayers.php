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
    protected $signature = 'tennis:import-from-schedule
                                {--delay=1 : Délai en secondes entre chaque requête API}
                                {--no-cache : Désactiver le cache}
                                {--force : Forcer la récupération des données en ignorant le cache existant}
                                {--limit= : Limiter le nombre de joueurs à collecter}
                                {--import-teams : Pré-charger aussi les données des équipes (saisons + standings) dans le cache}
                                {--download-images : Télécharger les images des joueurs pendant la mise en cache}
                                {--download-logos : Télécharger les logos des tournois pendant la mise en cache}';

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
     * IDs des joueurs déjà traités dans cette exécution (évite les doublons)
     */
    private array $processedPlayerIds = [];

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
        'tournament_logos_downloaded' => 0,
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
        $downloadImages = $this->option('download-images');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->line("🎾 Début de la collecte des données des joueurs de tennis");
        $this->line("💾 Cache: " . ($noCache ? 'Désactivé' : 'Activé'));
        $this->line("🔄 Mode force: " . ($force ? 'Activé (ignore le cache)' : 'Désactivé'));
        // option --export-data supprimée : export géré séparément si nécessaire
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

        // L'export JSON a été retiré de la commande automatique.

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
            'players/statistics', // Statistiques des joueurs
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
        $ttl = match ($cacheType) {
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

        $directories = ['tournaments', 'players', 'players/statistics', 'metadata', 'default'];

        foreach ($directories as $dir) {
            $path = $this->cacheDirectory . '/' . $dir;
            if (!is_dir($path)) continue;

            $files = glob($path . '/*.{cache,meta,json}', GLOB_BRACE);

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
     * Écrire un cache négatif (tombstone) pour éviter de re-tenter les URLs en erreur.
     * Expire automatiquement après 24h.
     * (Harmonisé avec Football/Basketball)
     */
    private function writeNegativeCache(string $cacheFile, string $metadataFile, string $url, int $sofascoreId, array $extraMeta = []): void
    {
        $negativeData = [
            '_negative_cache' => true,
            '_url' => $url,
            '_http_status' => 0,
            '_cached_at' => time(),
            '_expires_at' => date('Y-m-d H:i:s', time() + 86400),
        ];
        file_put_contents($cacheFile, json_encode($negativeData, JSON_PRETTY_PRINT));

        $negativeMeta = array_merge([
            'timestamp' => time(),
            'url' => $url,
            'player_id' => $sofascoreId,
            'player_name' => "ID: {$sofascoreId}",
            'negative_cache' => true,
        ], $extraMeta);
        file_put_contents($metadataFile, json_encode($negativeMeta, JSON_PRETTY_PRINT));
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
     * Headers HTTP pour les requêtes Sofascore.
     * (Harmonisé avec Football/Basketball)
     */
    private function getHttpHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Referer' => 'https://www.sofascore.com/',
            'Origin' => 'https://www.sofascore.com',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ];
    }

    /**
     * Créer une requête HTTP avec retry.
     * (Harmonisé avec Football/Basketball : retry 3x, timeout 30s)
     */
    private function makeHttpRequest($url)
    {
        try {
            $headers = $this->getHttpHeaders();

            $this->line("🌐 Requête HTTP vers: {$url}");

            $response = Http::retry(1, 2000)
                ->timeout(30)
                ->withHeaders($headers)
                ->withOptions([
                    'verify' => false,
                    'allow_redirects' => true,
                    'http_errors' => false,
                ])
                ->get($url);

            if ($response->successful()) {
                $this->line("✅ Requête réussie (statut: {$response->status()})");
                return $response;
            }

            // Gestion spécifique du 403
            if ($response->status() === 403) {
                $this->handleForbiddenError($response, $url);
                return null;
            }

            $this->stats['api_errors']++;
            $this->warn("⚠️ Erreur HTTP {$response->status()} pour: {$url}");
            return null;
        } catch (\Exception $e) {
            $this->stats['api_errors']++;
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

            // --- Marqueur par ligue (tennis) ---
            // Empêche de re-traiter un même tournoi pour la même date
            $uniqueTournamentId = $tournament['uniqueTournament']['id'] ?? $tournament['id'] ?? null;
            $dateForMarker = date('Y-m-d');
            $leagueMarker = storage_path("app/sofascore_cache/tennis_LEAGUE_DONE_{$dateForMarker}_" . ($uniqueTournamentId ?? 'unknown'));
            $markerExists = file_exists($leagueMarker);
            $this->line("DEBUG: Vérification marker tournoi: {$leagueMarker} (exists=" . ($markerExists ? 'yes' : 'no') . ", force=" . ($force ? 'yes' : 'no') . ")");

            if ($uniqueTournamentId && $markerExists && !$force) {
                $this->line("   ⏭️ Tournoi déjà mis en cache (marker présent): {$tournamentName} (sofascore_id: {$uniqueTournamentId})");
                return;
            }

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

            // Si on arrive ici sans exception, écrire un marker indiquant que le tournoi a été mis en cache pour cette date
            try {
                if (!empty($uniqueTournamentId)) {
                    @file_put_contents($leagueMarker, json_encode(['done_at' => time(), 'sofascore_id' => $uniqueTournamentId, 'name' => $tournamentName]));

                    // Télécharger le logo du tournoi dans le cache (Phase 1: API → cache)
                    if ($downloadImages) {
                        $this->downloadTournamentLogo($uniqueTournamentId, $tournamentName);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Impossible d\'écrire le marker de tournoi (tennis)', ['file' => $leagueMarker ?? null, 'error' => $e->getMessage()]);
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

            // Éviter de traiter deux fois le même joueur dans la même exécution
            if (in_array($sofascoreId, $this->processedPlayerIds, true)) {
                $this->stats['duplicates_detected']++;
                $this->line("⏭️  Joueur déjà traité dans cette exécution: {$name} (ID: {$sofascoreId})");
                return;
            }
            $this->processedPlayerIds[] = $sofascoreId;

            // Vérification rapide : si le cache du joueur est déjà valide, on skip entièrement
            // NOTE: les statistiques annuelles peuvent être manquantes sans que les détails ne le soient.
            // Pour éviter des requêtes 404 inutiles, considérer les `player_basic` + `player_details`
            // comme suffisants pour un skip ; les stats resteront optionnelles et peuvent être
            // (re)générées via l'option --force ou une commande dédiée.
            if (!$force && !$noCache) {
                $basicCacheFile = $this->cacheDirectory . '/players/player_basic_' . $sofascoreId . '.json';
                $detailsCacheFile = $this->cacheDirectory . '/players/player_details_' . $sofascoreId . '.json';
                $statsCacheFile = $this->cacheDirectory . '/players/statistics/player_statistics_' . $sofascoreId . '.json';

                // Si les données de base et les détails existent, on considère le joueur déjà mis en cache.
                if (file_exists($basicCacheFile) && file_exists($detailsCacheFile)) {
                    // Si les stats existent et sont fraîches, tout est valide
                    if (file_exists($statsCacheFile)) {
                        $statsAge = time() - filemtime($statsCacheFile);
                        if ($statsAge < 86400) {
                            $this->stats['players_skipped']++;
                            $this->stats['cache_hits']++;
                            $this->line("⏭️  Cache déjà valide: {$name} (ID: {$sofascoreId}) - skip");
                            return;
                        }
                    }

                    // Détails présents mais statistiques absentes ou expirées : éviter l'appel HTTP immédiat
                    $this->stats['players_skipped']++;
                    $this->stats['cache_hits']++;
                    $this->line("⏭️  Détails en cache, statistiques manquantes/expirées: {$name} (ID: {$sofascoreId}) - skip (pas de fetch stats)");
                    return;
                }
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

            // Récupérer et mettre en cache les statistiques annuelles du joueur
            $this->fetchAndCachePlayerStatistics($sofascoreId, $noCache, $force);

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

            // DEBUG: afficher chemins et contenu des métadonnées avant vérification
            $this->line("DEBUG: Vérification cache détails joueur ID={$sofascoreId}");
            $this->line("DEBUG: cacheFile={$cacheFile} exists=" . (file_exists($cacheFile) ? 'yes' : 'no') . " metadataFile={$metadataFile} exists=" . (file_exists($metadataFile) ? 'yes' : 'no'));
            if (file_exists($metadataFile)) {
                $metaRaw = @file_get_contents($metadataFile);
                $metaJson = @json_decode($metaRaw, true);
                $metaKeys = is_array($metaJson) ? implode(',', array_keys($metaJson)) : 'invalid';
                $metaTimestamp = $metaJson['timestamp'] ?? ($metaJson['created_at'] ?? 'null');
                $this->line("DEBUG: metadata keys={$metaKeys} timestamp={$metaTimestamp}");
            }

            // Vérifier le cache
            if (!$noCache && !$force && file_exists($cacheFile) && file_exists($metadataFile)) {
                $metadata = json_decode(file_get_contents($metadataFile), true);
                $cacheAge = time() - ($metadata['timestamp'] ?? 0);

                // Cache négatif (404) : valide 24h, on skip sans requête
                if (!empty($metadata['negative_cache']) && $cacheAge < (24 * 3600)) {
                    $this->stats['cache_hits']++;
                    $this->stats['players_skipped']++;
                    $this->line("⏭️  Cache négatif (détails): joueur ID {$sofascoreId} - skip (âge: " . round($cacheAge / 3600, 1) . "h)");
                    return;
                }

                // Cache valide pendant 7 jours pour les détails des joueurs
                if ($cacheAge < (7 * 24 * 3600)) {
                    $playerDetails = json_decode(file_get_contents($cacheFile), true);
                    // Vérifier que ce n'est pas un ancien tombstone au format _negative_cache
                    if (!empty($playerDetails['_negative_cache'])) {
                        if ($cacheAge < (24 * 3600)) {
                            $this->stats['cache_hits']++;
                            $this->stats['players_skipped']++;
                            $this->line("⏭️  Cache négatif (détails): joueur ID {$sofascoreId} - skip");
                            return;
                        }
                        // Tombstone expiré, on re-tente
                        $playerDetails = null;
                    } else {
                        $fromCache = true;
                        $this->stats['cache_hits']++;
                        $playerName = $metadata['player_name'] ?? "ID: {$sofascoreId}";
                        $this->line("💾 Détails du joueur depuis le cache: {$playerName} (âge: " . round($cacheAge / 3600, 1) . "h)");
                    }
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
                    // Cache négatif : format harmonisé avec Football/Basketball
                    $this->writeNegativeCache($cacheFile, $metadataFile, $url, $sofascoreId);
                    $this->warn("⚠️ Impossible de récupérer les détails du joueur ID: {$sofascoreId} - cache négatif créé (24h)");
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
     * Récupérer et mettre en cache les statistiques annuelles d'un joueur
     */
    private function fetchAndCachePlayerStatistics($sofascoreId, $noCache = false, $force = false)
    {
        try {
            // Calculer l'année appropriée (année actuelle - 4 mois)
            $currentDate = new \DateTime();
            $currentMonth = (int) $currentDate->format('n');
            $currentYear = (int) $currentDate->format('Y');

            // Si on est dans les 4 premiers mois de l'année, utiliser l'année précédente
            $statisticsYear = ($currentMonth <= 2) ? $currentYear - 1 : $currentYear;

            $url = "https://www.sofascore.com/api/v1/team/{$sofascoreId}/year-statistics/{$statisticsYear}";
            $cacheKey = "player_statistics_{$sofascoreId}";
            $cacheFile = $this->cacheDirectory . '/players/statistics/' . $cacheKey . '.json';
            $metadataFile = $this->cacheDirectory . '/metadata/' . md5($cacheKey) . '.meta';

            // Créer les répertoires s'ils n'existent pas
            $statisticsDir = $this->cacheDirectory . '/players/statistics';
            $metadataDir = $this->cacheDirectory . '/metadata';
            if (!is_dir($statisticsDir)) {
                mkdir($statisticsDir, 0755, true);
            }
            if (!is_dir($metadataDir)) {
                mkdir($metadataDir, 0755, true);
            }

            $playerStatistics = null;
            $fromCache = false;

            // Ignorer le cache si force est activé
            if ($force) {
                $this->line("🔄 Mode force activé - Ignorer le cache des statistiques pour le joueur ID: {$sofascoreId}");
            }

            // Vérifier le cache
            if (!$noCache && !$force && file_exists($cacheFile) && file_exists($metadataFile)) {
                $metadata = json_decode(file_get_contents($metadataFile), true);
                $cacheAge = time() - ($metadata['timestamp'] ?? 0);

                // Cache négatif (404) : valide 24h, on skip sans requête
                if (!empty($metadata['negative_cache']) && $cacheAge < (24 * 3600)) {
                    $this->stats['cache_hits']++;
                    $this->line("⏭️  Cache négatif (stats): joueur ID {$sofascoreId} - skip (âge: " . round($cacheAge / 3600, 1) . "h)");
                    return;
                }

                // Cache valide pendant 24 heures pour les statistiques (données plus volatiles)
                if ($cacheAge < (24 * 3600)) {
                    $playerStatistics = json_decode(file_get_contents($cacheFile), true);
                    // Vérifier que ce n'est pas un tombstone au format _negative_cache
                    if (!empty($playerStatistics['_negative_cache'])) {
                        if ($cacheAge < (24 * 3600)) {
                            $this->stats['cache_hits']++;
                            $this->line("⏭️  Cache négatif (stats): joueur ID {$sofascoreId} - skip");
                            return;
                        }
                        $playerStatistics = null;
                    } else {
                        $fromCache = true;
                        $this->stats['cache_hits']++;
                        $playerName = $metadata['player_name'] ?? "ID: {$sofascoreId}";
                        $this->line("📊 Statistiques du joueur depuis le cache: {$playerName} ({$statisticsYear}) (âge: " . round($cacheAge / 3600, 1) . "h)");
                    }
                }
            }

            // Récupérer depuis l'API si pas en cache
            if (!$playerStatistics) {
                $this->stats['cache_misses']++;
                $this->line("📈 Récupération des statistiques du joueur ID: {$sofascoreId} pour l'année {$statisticsYear}");

                $response = $this->makeHttpRequest($url, 1);

                if ($response && $response->successful()) {
                    $playerStatistics = $response->json();
                    $playerName = $playerStatistics['team']['name'] ?? "ID: {$sofascoreId}";

                    // Sauvegarder en cache
                    $cacheWritten = file_put_contents($cacheFile, json_encode($playerStatistics, JSON_PRETTY_PRINT));
                    $this->line("📊 Cache des statistiques écrit: {$cacheFile} ({$cacheWritten} bytes)");

                    // Sauvegarder les métadonnées
                    $metadata = [
                        'timestamp' => time(),
                        'url' => $url,
                        'player_id' => $sofascoreId,
                        'player_name' => $playerName,
                        'statistics_year' => $statisticsYear
                    ];
                    $metaWritten = file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
                    $this->line("📋 Metadata des statistiques écrite: {$metadataFile} ({$metaWritten} bytes)");

                    $this->line("✅ Statistiques du joueur récupérées et mises en cache: {$playerName} ({$statisticsYear})");
                } else {
                    // Cache négatif : format harmonisé avec Football/Basketball
                    $this->writeNegativeCache($cacheFile, $metadataFile, $url, $sofascoreId, [
                        'statistics_year' => $statisticsYear
                    ]);
                    $this->warn("⚠️ Impossible de récupérer les statistiques du joueur ID: {$sofascoreId} ({$statisticsYear}) - cache négatif créé (24h)");
                    return;
                }
            }

            // Les statistiques sont maintenant en cache

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques du joueur', [
                'sofascore_id' => $sofascoreId,
                'statistics_year' => $statisticsYear ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }



    /**
     * Gérer les erreurs 403 (anti-bot Sofascore).
     * (Harmonisé avec Football/Basketball : log + continue, pas exit(1))
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

        Log::error('🚨 Erreur 403 - Challenge détecté', [
            'status' => $response->status(),
            'url' => $url,
            'challenge_type' => $challengeType,
            'response_body' => $responseBody
        ]);
    }

    /**
     * Exporter les données importées pour synchronisation
     */
    // exportImportedData() removed: exports are handled by separate jobs/tools now.

    /**
     * Télécharger le logo d'un tournoi dans le cache (avec cache négatif)
     */
    private function downloadTournamentLogo($tournamentId, $tournamentName)
    {
        try {
            $logoDir = storage_path('app/sofascore_cache/tennis_leagues/logos');
            $logoPath = $logoDir . '/' . $tournamentId . '.png';
            $metaDir = storage_path('app/sofascore_cache/metadata');
            $metaFile = $metaDir . '/tournament_logo_' . $tournamentId . '.meta';
            $force = (bool) $this->option('force');

            // Vérifier si le logo existe déjà en cache
            if (!$force && file_exists($logoPath) && filesize($logoPath) > 0) {
                $this->line("      ⏭️ Logo tournoi déjà en cache: {$tournamentName} (ID: {$tournamentId})");
                // Supprimer le tombstone négatif s'il existe
                if (file_exists($metaFile)) {
                    $existingMeta = json_decode(file_get_contents($metaFile), true);
                    if (!empty($existingMeta['negative_cache'])) {
                        @unlink($metaFile);
                    }
                }
                return true;
            }

            // Créer les répertoires si nécessaire
            if (!is_dir($logoDir)) {
                mkdir($logoDir, 0755, true);
            }
            if (!is_dir($metaDir)) {
                mkdir($metaDir, 0755, true);
            }

            // Si un tombstone négatif existe et est encore valide (24h), on skip
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true);
                if (!empty($meta['negative_cache']) && (time() - ($meta['timestamp'] ?? 0)) < 86400) {
                    $this->line("      ⏭️ Tombstone négatif logo tournoi présent: {$tournamentName} (ID: {$tournamentId}) - skip");
                    return false;
                }
            }

            $imageUrl = "https://api.sofascore.com/api/v1/unique-tournament/{$tournamentId}/image";
            $this->line("      📸 Téléchargement logo tournoi: {$tournamentName} (ID: {$tournamentId})");

            $response = $this->makeHttpRequest($imageUrl);

            if (!$response || !$response->successful()) {
                // Écrire un tombstone négatif pour éviter de retenter pendant 24h
                $negativeMeta = [
                    'timestamp' => time(),
                    'url' => $imageUrl,
                    'tournament_id' => $tournamentId,
                    'tournament_name' => $tournamentName,
                    'negative_cache' => true
                ];
                @file_put_contents($metaFile, json_encode($negativeMeta, JSON_PRETTY_PRINT));
                $this->line("      ⚠️ Logo tournoi non disponible: {$tournamentName} - tombstone écrit (24h)");
                Log::info('tournament_logo_unavailable', [
                    'tournament_id' => $tournamentId,
                    'tournament_name' => $tournamentName,
                    'status' => $response ? $response->status() : 'no_response',
                    'tombstone' => $metaFile
                ]);
                return false;
            }

            // Sauvegarder le logo
            file_put_contents($logoPath, $response->body());

            // Supprimer le tombstone négatif s'il existe
            if (file_exists($metaFile)) {
                $existingMeta = json_decode(file_get_contents($metaFile), true);
                if (!empty($existingMeta['negative_cache'])) {
                    @unlink($metaFile);
                }
            }

            $this->line("      ✅ Logo tournoi téléchargé: {$tournamentName}");
            $this->stats['tournament_logos_downloaded']++;
            Log::info('tournament_logo_downloaded', [
                'tournament_id' => $tournamentId,
                'tournament_name' => $tournamentName,
                'cache_path' => $logoPath
            ]);

            return true;
        } catch (\Exception $e) {
            $this->warn("      ⚠️ Erreur téléchargement logo tournoi {$tournamentName}: " . $e->getMessage());
            Log::error('tournament_logo_download_error', [
                'tournament_id' => $tournamentId,
                'tournament_name' => $tournamentName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Télécharger l'image d'un joueur
     */
    private function downloadPlayerImage($sofascoreId, $playerName)
    {
        try {
            $logoPath = $this->cacheDirectory . '/players/logos/' . $sofascoreId . '.png';
            $force = (bool) $this->option('force');

            // Vérifier si l'image existe déjà en cache
            if (!$force && file_exists($logoPath) && filesize($logoPath) > 0) {
                $this->line("⏭️  Image déjà en cache: {$playerName} (ID: {$sofascoreId})");
                return true;
            }
            $imageUrl = "https://api.sofascore.com/api/v1/team/{$sofascoreId}/image";
            $this->line("📸 Téléchargement de l'image: {$playerName} (ID: {$sofascoreId})");

            // Metadata/cache files pour l'image (tombstone négatif)
            $logoDir = dirname($logoPath);
            // Utiliser un nom lisible pour permettre un nettoyage ciblé (player_image_{id}.meta)
            $metaFile = $this->cacheDirectory . '/metadata/player_image_' . $sofascoreId . '.meta';
            if (!is_dir($logoDir)) {
                mkdir($logoDir, 0755, true);
            }
            if (!is_dir(dirname($metaFile))) {
                mkdir(dirname($metaFile), 0755, true);
            }

            // Si un tombstone négatif existe et est encore valide (24h), on skip
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true);
                if (!empty($meta['negative_cache']) && (time() - ($meta['timestamp'] ?? 0)) < 86400) {
                    $this->line("⏭️  Tombstone négatif image présent: {$playerName} (ID: {$sofascoreId}) - skip");
                    return false;
                }
            }

            $response = $this->makeHttpRequest($imageUrl);

            if (!$response || !$response->successful()) {
                // Écrire un tombstone négatif pour éviter de retenter pendant 24h
                $negativeMeta = [
                    'timestamp' => time(),
                    'url' => $imageUrl,
                    'player_id' => $sofascoreId,
                    'player_name' => $playerName,
                    'negative_cache' => true
                ];
                @file_put_contents($metaFile, json_encode($negativeMeta, JSON_PRETTY_PRINT));
                $this->warn("⚠️ Échec du téléchargement de l'image pour: {$playerName} - tombstone écrit (24h)");
                return false;
            }

            // Sauvegarder l'image
            file_put_contents($logoPath, $response->body());

            // Supprimer le tombstone négatif s'il existe
            if (file_exists($metaFile)) {
                $existingMeta = json_decode(file_get_contents($metaFile), true);
                if (!empty($existingMeta['negative_cache'])) {
                    @unlink($metaFile);
                }
            }

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
        $this->line("🏆 Logos de tournois téléchargés: {$this->stats['tournament_logos_downloaded']}");
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
