<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportFutsalFromSchedule extends Command
{
    protected $signature = 'futsal:import-from-schedule
                            {date? : Date au format YYYY-MM-DD (défaut: aujourd\'hui)}
                            {--no-cache : Désactiver le cache}
                            {--import-teams : Pré-charger aussi les données des équipes (saisons + standings) dans le cache}
                            {--download-logos : Télécharger les logos des équipes dans le cache (nécessite --import-teams)}
                            {--delay=1 : Délai en secondes entre chaque requête API}
                            {--max-pages=50 : Nombre maximum de pages à parcourir}';

    protected $description = 'Phase 1 : Collecte les données de futsal depuis l\'API Sofascore et les stocke en cache local (aucune écriture en BDD). Utiliser futsal:import-from-cache pour la Phase 2.';

    private string $cacheDirectory;

    private array $stats = [
        'pages_fetched' => 0,
        'leagues_discovered' => 0,
        'seasons_cached' => 0,
        'standings_cached' => 0,
        'api_errors' => 0,
    ];

    public function handle(): int
    {
        $date = $this->argument('date') ?? date('Y-m-d');
        $noCache = (bool) $this->option('no-cache');
        $importTeams = (bool) $this->option('import-teams');
        $downloadLogos = (bool) $this->option('download-logos');
        $delay = (int) $this->option('delay');
        $maxPages = (int) $this->option('max-pages');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error("❌ Format de date invalide: {$date}. Utiliser YYYY-MM-DD.");
            return Command::FAILURE;
        }

        $this->info("⚽ Collecte Futsal → cache (Phase 1)");
        $this->line("📅 Date: {$date}");
        $this->line("💾 Cache: " . ($noCache ? 'Désactivé' : 'Activé'));
        $this->line("👥 Pré-charger équipes: " . ($importTeams ? 'Oui' : 'Non'));
        $this->line("📸 Logos en cache: " . ($downloadLogos ? 'Oui' : 'Non'));
        $this->line("⏱️  Délai: {$delay}s | Max pages: {$maxPages}");
        $this->line("");

        $this->setCacheDirectory($date);
        $this->line("📂 Cache: {$this->cacheDirectory}");

        if (!$noCache) {
            $this->cleanExpiredCache();
        }
        $this->line("");

        $this->info("📡 Récupération des tournois programmés...");
        $allTournaments = $this->fetchAllScheduledTournaments($date, $noCache, $delay, $maxPages);

        $this->line("📊 Total brut de tournois récupérés: " . count($allTournaments));

        if (empty($allTournaments)) {
            $this->warn("⚠️ Aucun tournoi trouvé pour la date {$date}.");
            $this->warn("💡 Vérifiez que l'API Sofascore est accessible et que la date est valide.");
            $this->displayStats();
            return Command::SUCCESS;
        }

        $uniqueLeagues = $this->deduplicateTournaments($allTournaments);
        $this->stats['leagues_discovered'] = count($uniqueLeagues);

        $this->info("🏆 {$this->stats['leagues_discovered']} ligues uniques trouvées sur {$this->stats['pages_fetched']} page(s)");
        $this->line("");

        if ($importTeams) {
            $totalLeagues = count($uniqueLeagues);
            $this->info("📡 Pré-chargement des données d'équipes (seasons + standings) pour {$totalLeagues} ligues...");
            $this->line("");

            $num = 0;
            foreach ($uniqueLeagues as $tournamentData) {
                $num++;
                $leagueName = $tournamentData['tournament']['uniqueTournament']['name'] ?? 'Inconnu';
                $categoryName = $tournamentData['tournament']['uniqueTournament']['category']['name'] ?? 'N/A';
                $sofascoreId = $tournamentData['tournament']['uniqueTournament']['id'] ?? null;

                $this->info("── [{$num}/{$totalLeagues}] {$leagueName} ({$categoryName}) ──");

                if (!$sofascoreId) {
                    $this->warn("   ⚠️ Pas de sofascore_id, ignoré");
                    continue;
                }

                $this->line("   🔑 Sofascore ID: {$sofascoreId}");

                $seasonId = $this->getSeasonId($sofascoreId, $noCache);
                if (!$seasonId) {
                    $this->warn("   ⚠️ Aucune saison trouvée → pas de standings à pré-charger");
                    continue;
                }
                $this->line("   📅 Season ID: {$seasonId}");

                $teams = $this->getTeamsFromStandings($sofascoreId, $seasonId, $noCache);
                $teamCount = count($teams);
                if ($teamCount > 0) {
                    $this->stats['standings_cached']++;
                    $this->line("   ✅ {$teamCount} équipes mises en cache");

                    $maxShow = 50;
                    $shown = 0;
                    foreach (array_slice($teams, 0, $maxShow) as $team) {
                        $tName = $team['name'] ?? ($team['shortName'] ?? 'unknown');
                        $tId = $team['id'] ?? ($team['sofascore_id'] ?? 'N/A');
                        $teamCached = $this->teamPlayersCacheExists($tId, $tName);
                        $cacheLabel = $teamCached ? ' [cache]' : '';
                        $this->line("      - {$tName} (sofascore_id: {$tId}){$cacheLabel}");
                        $shown++;
                    }
                    if ($teamCount > $maxShow) {
                        $this->line("      ... + " . ($teamCount - $maxShow) . " autres équipes");
                    }

                    if ($downloadLogos) {
                        $this->info("   📸 Téléchargement des logos pour {$teamCount} équipes...");
                        $logosDownloaded = 0;
                        $logosSkipped = 0;
                        foreach ($teams as $team) {
                            $tId = $team['id'] ?? null;
                            $tName = $team['name'] ?? 'unknown';
                            if ($tId) {
                                $result = $this->downloadTeamLogoToCache($tId, $tName);
                                if ($result === 'downloaded') {
                                    $logosDownloaded++;
                                } else {
                                    $logosSkipped++;
                                }
                            }
                        }
                        $this->line("   📸 Logos: {$logosDownloaded} téléchargés, {$logosSkipped} ignorés/erreurs");
                    }
                } else {
                    $this->line("   ⏭️ Aucune équipe dans les standings");
                }

                if ($delay > 0) {
                    usleep($delay * 500000);
                }
            }

            $this->line("");
        }

        $this->displayStats();
        $this->newLine();
        $this->info("💡 Pour importer en BDD: php artisan futsal:import-from-cache {$date} --import-teams --download-logos");
        return Command::SUCCESS;
    }

    private function setCacheDirectory(string $date): void
    {
        $this->cacheDirectory = storage_path("app/sofascore_cache/futsal_schedule/{$date}");

        if (!file_exists($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0755, true);
        }
    }

    private function cleanExpiredCache(): void
    {
        $parentDir = storage_path('app/sofascore_cache/futsal_schedule');
        if (!is_dir($parentDir)) {
            return;
        }

        $cleaned = 0;
        $cutoff = strtotime('-7 days');

        foreach (scandir($parentDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry)) continue;

            $entryDate = strtotime($entry);
            if ($entryDate && $entryDate < $cutoff) {
                $dirPath = $parentDir . '/' . $entry;
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $file) {
                    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }
                rmdir($dirPath);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->line("🧹 {$cleaned} répertoire(s) de cache futsal expiré(s) supprimé(s)");
        }
    }

    private function readCache(string $cacheFile): ?array
    {
        if (!file_exists($cacheFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($cacheFile), true);
        if (!is_array($data)) {
            return null;
        }

        if (!empty($data['_negative_cache'])) {
            $cacheAge = time() - ($data['_cached_at'] ?? 0);
            if ($cacheAge < 86400) {
                return $data;
            }
            @unlink($cacheFile);
            return null;
        }

        return $data;
    }

    private function writeCache(string $cacheFile, array $data): void
    {
        $dir = dirname($cacheFile);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function writeNegativeCache(string $cacheFile, string $url, int $httpStatus = 0): void
    {
        $this->writeCache($cacheFile, [
            '_negative_cache' => true,
            '_url' => $url,
            '_http_status' => $httpStatus,
            '_cached_at' => time(),
            '_expires_at' => date('Y-m-d H:i:s', time() + 86400),
        ]);
    }

    private function fetchAllScheduledTournaments(string $date, bool $noCache, int $delay, int $maxPages): array
    {
        $allTournaments = [];
        $page = 0;
        $hasNextPage = true;

        $this->line("🔁 Début de la pagination (max {$maxPages} pages)...");

        while ($hasNextPage && $page < $maxPages) {
            $page++;
            $this->line("");
            $this->line("📄 ─── Page {$page}/{$maxPages} ───");

            $data = $this->fetchScheduledTournamentsPage($date, $page, $noCache);

            if ($data === null) {
                $this->error("⚠️ Échec de la récupération de la page {$page}, arrêt de la pagination.");
                $this->error("   Vérifiez les détails de l'erreur ci-dessus.");
                break;
            }

            $scheduled = $data['scheduled'] ?? [];
            $dataKeys = array_keys($data);
            $this->line("   📦 Clés JSON reçues: [" . implode(', ', $dataKeys) . "]");

            if (!empty($scheduled)) {
                $allTournaments = array_merge($allTournaments, $scheduled);
            }

            $this->stats['pages_fetched']++;
            $hasNextPage = $data['hasNextPage'] ?? false;

            $this->info("   ✅ " . count($scheduled) . " tournois | Total cumulé: " . count($allTournaments) . " | hasNextPage: " . ($hasNextPage ? 'true' : 'false'));

            if ($hasNextPage && $delay > 0) {
                sleep($delay);
            }
        }

        return $allTournaments;
    }

    private function fetchScheduledTournamentsPage(string $date, int $page, bool $noCache): ?array
    {
        $url = "https://www.sofascore.com/api/v1/sport/futsal/scheduled-tournaments/{$date}/page/{$page}";

        $cacheFile = $this->cacheDirectory . "/page_{$page}.json";

        if (!$noCache) {
            $cached = $this->readCache($cacheFile);
            if ($cached !== null) {
                if (!empty($cached['_negative_cache'])) {
                    $this->line("   ⏭️  Page {$page} : cache négatif (skip)");
                    return null;
                }
                $this->line("   💾 Page {$page} chargée depuis le cache");
                return $cached;
            }
        }

        $this->line("   🌐 Requête API:");
        $this->line("      URL: {$url}");
        $headers = $this->getHttpHeaders();
        $this->line("      User-Agent: " . substr($headers['User-Agent'], 0, 60) . '...');
        $this->line("      Timeout: 30s | Retry: 3x (2s interval)");

        try {
            $startTime = microtime(true);
            $response = Http::retry(3, 2000)
                ->timeout(30)
                ->withHeaders($headers)
                ->get($url);

            $elapsed = round((microtime(true) - $startTime) * 1000);
            $this->line("   📡 Réponse HTTP: {$response->status()} ({$elapsed}ms)");
            $this->line("      Content-Type: " . ($response->header('Content-Type') ?? 'N/A'));
            $this->line("      Content-Length: " . strlen($response->body()) . " bytes");

            if ($response->status() === 403) {
                $this->handleForbiddenError($response, $url);
                return null;
            }

            if (!$response->successful()) {
                $this->stats['api_errors']++;
                $body = substr($response->body(), 0, 500);
                $this->error("   ❌ Erreur API (HTTP {$response->status()})");
                $this->error("   🔗 URL: {$url}");
                $this->error("   📄 Corps de la réponse:");
                $this->error("      {$body}");
                $this->error("   📋 Headers de réponse:");
                foreach (['Content-Type', 'X-RateLimit-Remaining', 'Retry-After', 'CF-Ray', 'Server'] as $h) {
                    $val = $response->header($h);
                    if ($val) {
                        $this->error("      {$h}: {$val}");
                    }
                }
                Log::warning('Erreur API scheduled-tournaments', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $body,
                    'response_headers' => $response->headers(),
                ]);
                if (!$noCache) {
                    $this->writeNegativeCache($cacheFile, $url, $response->status());
                    $this->line("   🗻 Cache négatif créé (24h) pour page {$page}");
                }
                return null;
            }

            $data = $response->json();
            $this->line("   ✅ JSON parsé avec succès");

            if (!$noCache && is_array($data)) {
                $this->writeCache($cacheFile, $data);
                $this->line("   💾 Cache sauvegardé: {$cacheFile}");
            }

            return $data;
        } catch (\Exception $e) {
            $this->stats['api_errors']++;
            $this->error("   ❌ Exception: {$e->getMessage()}");
            $this->error("   🔗 URL: {$url}");
            $this->error("   📍 " . basename($e->getFile()) . ':' . $e->getLine());
            Log::error('Exception lors de la récupération des tournois programmés', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    private function deduplicateTournaments(array $allTournaments): array
    {
        $seen = [];
        $unique = [];

        foreach ($allTournaments as $entry) {
            $uniqueTournamentId = $entry['tournament']['uniqueTournament']['id'] ?? null;
            if ($uniqueTournamentId === null) {
                continue;
            }

            if (isset($seen[$uniqueTournamentId])) {
                continue;
            }

            $seen[$uniqueTournamentId] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    private function countUniqueTournaments(array $allTournaments): int
    {
        return count($this->deduplicateTournaments($allTournaments));
    }

    private function getSeasonId(int $leagueSofascoreId, bool $noCache): ?int
    {
        $url = "https://www.sofascore.com/api/v1/unique-tournament/{$leagueSofascoreId}/featured-events";
        $cacheFile = $this->cacheDirectory . '/featured_events_' . $leagueSofascoreId . '.json';

        try {
            if (!$noCache) {
                $cached = $this->readCache($cacheFile);
                if ($cached !== null) {
                    if (!empty($cached['_negative_cache'])) {
                        $this->line("      ⏭️  Cache négatif featured-events (ligue {$leagueSofascoreId}) - skip");
                        $this->stats['season_not_found']++;
                        return null;
                    }
                    return $this->extractSeasonId($cached);
                }
            }

            $this->line("      🌐 API: {$url}");
            $response = Http::retry(2, 1000)
                ->timeout(15)
                ->withHeaders($this->getHttpHeaders())
                ->get($url);

            $this->line("      📡 HTTP: {$response->status()}");

            if (!$response->successful()) {
                if ($response->status() === 403) {
                    $this->handleForbiddenError($response, $url);
                }
                $this->stats['api_errors']++;
                $this->error("      ❌ Erreur API featured-events (HTTP {$response->status()})");
                $this->error("      📄 " . substr($response->body(), 0, 300));
                if (!$noCache && $response->status() !== 403) {
                    $this->writeNegativeCache($cacheFile, $url, $response->status());
                    $this->line("      🗻 Cache négatif créé (24h) pour featured-events ligue {$leagueSofascoreId}");
                }
                return null;
            }

            $data = $response->json();

            if (!$noCache && is_array($data)) {
                $this->writeCache($cacheFile, $data);
            }

            $seasonId = $this->extractSeasonId($data);
            if ($seasonId) {
                $this->line("      ✅ Season ID extrait: {$seasonId}");
            } else {
                $this->line("      ⚠️ Aucun season ID trouvé dans featured-events (" . count($data['featuredEvents'] ?? []) . " events)");
            }
            return $seasonId;
        } catch (\Exception $e) {
            $this->stats['api_errors']++;
            $this->error("      ❌ Exception featured-events: {$e->getMessage()}");
            Log::error('Exception récupération season ID', [
                'league_sofascore_id' => $leagueSofascoreId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function extractSeasonId(?array $data): ?int
    {
        if (!$data) {
            return null;
        }

        $events = $data['featuredEvents'] ?? [];
        if (empty($events)) {
            return null;
        }

        return $events[0]['season']['id'] ?? null;
    }

    private function getTeamsFromStandings(int $leagueSofascoreId, int $seasonId, bool $noCache): array
    {
        $url = "https://www.sofascore.com/api/v1/unique-tournament/{$leagueSofascoreId}/season/{$seasonId}/standings/total";
        $cacheFile = $this->cacheDirectory . '/standings_' . $leagueSofascoreId . '_' . $seasonId . '.json';

        try {
            if (!$noCache) {
                $cached = $this->readCache($cacheFile);
                if ($cached !== null) {
                    if (!empty($cached['_negative_cache'])) {
                        $this->line("      ⏭️  Cache négatif standings (ligue {$leagueSofascoreId}) - skip");
                        return [];
                    }
                    return $this->extractTeamsFromStandings($cached);
                }
            }

            $this->line("      🌐 API: {$url}");
            $response = Http::retry(2, 1000)
                ->timeout(15)
                ->withHeaders($this->getHttpHeaders())
                ->get($url);

            $this->line("      📡 HTTP: {$response->status()}");

            if (!$response->successful()) {
                if ($response->status() === 403) {
                    $this->handleForbiddenError($response, $url);
                }
                $this->stats['api_errors']++;
                $this->error("      ❌ Erreur API standings (HTTP {$response->status()})");
                $this->error("      📄 " . substr($response->body(), 0, 300));
                if (!$noCache && $response->status() !== 403) {
                    $this->writeNegativeCache($cacheFile, $url, $response->status());
                    $this->line("      🗻 Cache négatif créé (24h) pour standings ligue {$leagueSofascoreId}");
                }
                return [];
            }

            $data = $response->json();

            if (!$noCache && is_array($data)) {
                $this->writeCache($cacheFile, $data);
            }

            return $this->extractTeamsFromStandings($data);
        } catch (\Exception $e) {
            $this->stats['api_errors']++;
            Log::error('Exception récupération standings', [
                'league_sofascore_id' => $leagueSofascoreId,
                'season_id' => $seasonId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function extractTeamsFromStandings(?array $data): array
    {
        $teams = [];

        if (!$data || !isset($data['standings'])) {
            return $teams;
        }

        foreach ($data['standings'] as $standing) {
            if (!isset($standing['rows'])) {
                continue;
            }
            foreach ($standing['rows'] as $row) {
                if (isset($row['team'])) {
                    $teams[] = $row['team'];
                }
            }
        }

        return $teams;
    }

    private function teamPlayersCacheExists($teamId, $teamName): bool
    {
        try {
            $slug = Str::slug($teamName ?: (string) $teamId);
            $dir = storage_path("app/sofascore_cache/teams_players/{$slug}-{$teamId}");
            return is_dir($dir) || file_exists($dir);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function downloadTeamLogoToCache(int $sofascoreId, string $teamName): string
    {
        $logoDir = $this->cacheDirectory . '/team_logos';
        $logoPath = $logoDir . '/' . $sofascoreId . '.png';

        if (file_exists($logoPath) && filesize($logoPath) > 0) {
            return 'cached';
        }

        $negFile = $logoDir . '/negative_' . $sofascoreId . '.json';
        if (file_exists($negFile)) {
            $meta = json_decode(file_get_contents($negFile), true);
            $age = time() - ($meta['_cached_at'] ?? 0);
            if (($meta['_negative_cache'] ?? false) && $age < 86400) {
                return 'skipped';
            }
            @unlink($negFile);
        }

        $url = "https://api.sofascore.com/api/v1/team/{$sofascoreId}/image";

        try {
            $response = Http::timeout(30)
                ->withHeaders($this->getHttpHeaders())
                ->get($url);

            if ($response->successful()) {
                if (!is_dir($logoDir)) {
                    mkdir($logoDir, 0755, true);
                }
                file_put_contents($logoPath, $response->body());
                $this->line("      📸 Logo caché: {$teamName} (sofascore_id: {$sofascoreId})");
                return 'downloaded';
            }

            if (in_array($response->status(), [403, 404])) {
                if (!is_dir($logoDir)) {
                    mkdir($logoDir, 0755, true);
                }
                file_put_contents($negFile, json_encode([
                    '_negative_cache' => true,
                    '_cached_at' => time(),
                    'sofascore_id' => $sofascoreId,
                    'status' => $response->status(),
                ]));
                return 'skipped';
            }

            return 'error';
        } catch (\Exception $e) {
            Log::warning('Erreur téléchargement logo en cache (futsal Phase 1)', [
                'sofascore_id' => $sofascoreId,
                'team_name' => $teamName,
                'error' => $e->getMessage(),
            ]);
            return 'error';
        }
    }

    private function getHttpHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9,fr;q=0.8',
            'Referer' => 'https://www.sofascore.com/',
            'Cache-Control' => 'no-cache',
        ];
    }

    private function handleForbiddenError($response, string $url): void
    {
        $body = $response->json();
        $reason = $body['error']['reason'] ?? 'unknown';

        $this->error("🚨 ERREUR 403 - Accès interdit");
        $this->error("🔍 Raison: {$reason}");
        $this->error("🔗 URL: {$url}");
        $this->error("💡 Suggestions:");
        $this->error("   - Attendre quelques minutes avant de relancer");
        $this->error("   - Utiliser un VPN ou changer d'IP");
        $this->error("   - Augmenter le délai (--delay=3)");

        Log::error('Erreur 403 - Challenge détecté', [
            'url' => $url,
            'reason' => $reason,
            'response_body' => $body,
        ]);
    }

    private function displayStats(): void
    {
        $this->newLine();
        $this->info('🏁 Collecte terminée (Phase 1) !');
        $this->newLine();
        $this->info('📊 === Statistiques ===');
        $this->line("📄 Pages récupérées: {$this->stats['pages_fetched']}");
        $this->line("🏆 Ligues découvertes: {$this->stats['leagues_discovered']}");
        $this->line("📅 Saisons mises en cache: {$this->stats['seasons_cached']}");
        $this->line("👥 Standings mis en cache: {$this->stats['standings_cached']}");
        $this->line("🌐 Erreurs API: {$this->stats['api_errors']}");

        Log::info('Futsal Phase 1 (cache) terminée', $this->stats);
    }
}
