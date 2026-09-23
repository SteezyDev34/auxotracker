<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ImportsMatchesFromCache;
use App\Models\Country;
use App\Models\League;
use App\Models\Player;
use App\Models\Sport;
use App\Models\Team;
use App\Services\ActivityLogger;
use App\Services\LeagueLogoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Commande générique "Phase 2" (cache → BDD) pour tous les sports basés sur le
 * pipeline scheduled-tournaments, à l'exception du tennis (qui a ses propres
 * particularités : point-by-point, catégories ATP/WTA/Challenger/ITF/UTR,
 * tennis_points — voir ImportTennisPlayersFromCache.php, non concerné ici).
 *
 * Remplace les 8 commandes ImportFootballFromCache/ImportBasketballFromCache/...
 * qui étaient des copies quasi identiques (~85% du code dupliqué 8 fois), avec
 * quelques divergences silencieuses accumulées au fil des copier-coller :
 * - ice-hockey avait perdu la méthode downloadTeamLogo (crash au premier import
 *   d'équipe avec logos).
 * - handball avait perdu la synchronisation du pivot league_team + le logo pour
 *   les équipes nouvellement créées/mises à jour.
 * - baseball/futsal/handball/rugby ne recherchaient le Sport local que par nom/
 *   slug (texte fragile), sans jamais utiliser son id — contrairement à
 *   football/basketball qui utilisaient sofascore_id. Ici on utilise directement
 *   l'id local (le plus robuste, connu et stable) avec repli sur le nom/slug.
 * - --import-teams n'existait dans AUCUNE des 8 signatures alors que tous les
 *   scripts shell (script/import_*.sh) le passaient déjà en croyant qu'il
 *   fonctionnait : la commande plantait donc systématiquement avec
 *   "The "--import-teams" option does not exist."
 */
class ImportSportFromCache extends Command
{
    use ImportsMatchesFromCache;

    /**
     * Configuration par sport : id local (table sports), id Sofascore du sport
     * (transmis au trait ImportsMatchesFromCache pour l'import des matchs),
     * sous-répertoire de cache, libellé et emoji pour les logs.
     */
    private const SPORTS = [
        'football' => [
            'label' => 'Football', 'emoji' => '⚽',
            'cache_subdir' => 'football_schedule',
            'sofascore_sport_id' => 1, 'sport_id' => 3,
        ],
        'basketball' => [
            'label' => 'Basketball', 'emoji' => '🏀',
            'cache_subdir' => 'basketball_schedule',
            'sofascore_sport_id' => 2, 'sport_id' => 4,
        ],
        'rugby' => [
            'label' => 'Rugby', 'emoji' => '🏉',
            'cache_subdir' => 'rugby_schedule',
            'sofascore_sport_id' => 12, 'sport_id' => 5,
        ],
        'handball' => [
            'label' => 'Handball', 'emoji' => '🤾',
            'cache_subdir' => 'handball_schedule',
            'sofascore_sport_id' => 6, 'sport_id' => 8,
        ],
        'ice-hockey' => [
            'label' => 'Ice Hockey', 'emoji' => '🏒',
            'cache_subdir' => 'ice_hockey_schedule',
            'sofascore_sport_id' => 4, 'sport_id' => 9,
        ],
        'baseball' => [
            'label' => 'Baseball', 'emoji' => '⚾',
            'cache_subdir' => 'baseball_schedule',
            'sofascore_sport_id' => 64, 'sport_id' => 10,
        ],
        'volleyball' => [
            'label' => 'Volleyball', 'emoji' => '🏐',
            'cache_subdir' => 'volleyball_schedule',
            'sofascore_sport_id' => 23, 'sport_id' => 13,
        ],
        'futsal' => [
            'label' => 'Futsal', 'emoji' => '🏟️',
            'cache_subdir' => 'futsal_schedule',
            'sofascore_sport_id' => 29, 'sport_id' => 17,
        ],
    ];

    protected $signature = 'sport:import-from-cache
                            {sport : Sport à importer (football, basketball, rugby, handball, ice-hockey, baseball, volleyball, futsal)}
                            {date? : Date au format YYYY-MM-DD (défaut: aujourd\'hui)}
                            {--force : Forcer la mise à jour même si les données existent}
                            {--download-logos : Télécharger les logos des ligues et des équipes}
                            {--import-teams : Importer les équipes depuis le cache (standings)}';

    protected $description = 'Phase 2 : Importe les ligues et équipes d\'un sport (hors tennis) depuis les fichiers de cache vers la BDD (aucun appel API). Exécuter {sport}:import-from-schedule d\'abord.';

    private string $cacheDirectory;
    private array $sportConfig;
    private string $sportSlug;

    private array $stats = [
        'pages_loaded' => 0,
        'tournaments_processed' => 0,
        'countries_created' => 0,
        'leagues_created' => 0,
        'leagues_updated' => 0,
        'leagues_skipped' => 0,
        'teams_created' => 0,
        'teams_updated' => 0,
        'teams_skipped' => 0,
        'teams_processed' => 0,
        'duplicates_detected' => 0,
        'logos_downloaded' => 0,
        'logos_skipped' => 0,
        'logos_missing' => 0,
        'logos_failed' => 0,
        'season_not_found' => 0,
        'players_processed' => 0,
        'players_created' => 0,
        'players_updated' => 0,
        'players_transferred' => 0,
        'players_skipped' => 0,
        'player_images_downloaded' => 0,
        'errors' => 0,
        'matches_processed' => 0,
        'matches_created' => 0,
        'matches_updated' => 0,
        'matches_skipped' => 0,
        'matches_errors' => 0,
    ];

    public function handle(): int
    {
        $this->sportSlug = strtolower((string) $this->argument('sport'));
        if (!isset(self::SPORTS[$this->sportSlug])) {
            $this->error("❌ Sport inconnu: {$this->sportSlug}. Disponibles: " . implode(', ', array_keys(self::SPORTS)));
            return Command::FAILURE;
        }
        $this->sportConfig = self::SPORTS[$this->sportSlug];
        $emoji = $this->sportConfig['emoji'];
        $label = $this->sportConfig['label'];

        $date = $this->argument('date') ?? date('Y-m-d');
        $force = (bool) $this->option('force');
        $importTeams = (bool) $this->option('import-teams');
        $downloadLogos = (bool) $this->option('download-logos');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error("❌ Format de date invalide: {$date}. Utiliser YYYY-MM-DD.");
            return Command::FAILURE;
        }

        $this->info("{$emoji} Import {$label} depuis le cache → BDD (Phase 2)");
        $this->line("📅 Date: {$date}");
        $this->line("🔄 Force: " . ($force ? 'Oui' : 'Non'));
        $this->line("👥 Import équipes: " . ($importTeams ? 'Oui' : 'Non'));
        $this->line("📸 Logos: " . ($downloadLogos ? 'Oui' : 'Non'));
        $this->line("");

        $this->cacheDirectory = storage_path("app/sofascore_cache/{$this->sportConfig['cache_subdir']}/{$date}");
        $this->line("📂 Cache: {$this->cacheDirectory}");

        if (!is_dir($this->cacheDirectory)) {
            $this->error("❌ Répertoire de cache introuvable: {$this->cacheDirectory}");
            $this->error("   Exécutez d'abord: php artisan {$this->sportSlug}:import-from-schedule {$date}");
            return Command::FAILURE;
        }
        $this->line("");

        $matchStats = $this->importMatchesFromCache(
            $this->cacheDirectory, $this->sportConfig['sofascore_sport_id'], $this->sportSlug, $force
        );
        $this->stats = array_merge($this->stats, $matchStats);

        $this->line("🔍 Recherche du sport {$label} en base...");
        $sport = $this->resolveSport();
        if (!$sport) {
            return Command::FAILURE;
        }

        $this->info("📥 Chargement des tournois depuis le cache...");
        $allTournaments = $this->loadTournamentsFromCache();
        $this->line("📊 Total brut de tournois chargés: " . count($allTournaments));

        if (empty($allTournaments)) {
            $this->warn("⚠️ Aucun tournoi trouvé dans le cache pour la date {$date}.");
            $this->warn("   Exécutez d'abord: php artisan {$this->sportSlug}:import-from-schedule {$date}");
            $this->displayStats();
            return Command::SUCCESS;
        }

        $uniqueLeagues = $this->deduplicateTournaments($allTournaments);
        $totalLeagues = count($uniqueLeagues);

        $this->info("🏆 {$totalLeagues} ligues uniques trouvées sur {$this->stats['pages_loaded']} page(s)");
        $this->line("");

        foreach ($uniqueLeagues as $i => $tournamentData) {
            $num = $i + 1;
            $leagueName = $tournamentData['tournament']['uniqueTournament']['name'] ?? 'N/A';
            $this->newLine();
            $this->info("── [{$num}/{$totalLeagues}] {$leagueName} ──");
            $this->processScheduledTournament($tournamentData, $sport, $force, $importTeams, $downloadLogos);
        }
        $this->newLine();

        $this->displayStats();
        return Command::SUCCESS;
    }

    // ─── CACHE (lecture seule) ──────────────────────────────────────────

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
            return $cacheAge < 86400 ? $data : null;
        }

        return $data;
    }

    private function loadTournamentsFromCache(): array
    {
        $allTournaments = [];
        $page = 1;

        while (true) {
            $cacheFile = $this->cacheDirectory . "/page_{$page}.json";
            $cached = $this->readCache($cacheFile);

            if ($cached === null || !empty($cached['_negative_cache'])) {
                break;
            }

            $this->stats['pages_loaded']++;
            $scheduled = $cached['scheduled'] ?? [];
            $allTournaments = array_merge($allTournaments, $scheduled);

            $this->line("💾 Page {$page} chargée (" . count($scheduled) . " tournois)");

            if (!($cached['hasNextPage'] ?? false)) {
                break;
            }

            $page++;
        }

        return $allTournaments;
    }

    // ─── DEDUPLICATION ──────────────────────────────────────────────────

    private function deduplicateTournaments(array $allTournaments): array
    {
        $seen = [];
        $unique = [];

        foreach ($allTournaments as $entry) {
            $uniqueTournamentId = $entry['tournament']['uniqueTournament']['id'] ?? null;
            if ($uniqueTournamentId === null || isset($seen[$uniqueTournamentId])) {
                continue;
            }
            $seen[$uniqueTournamentId] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    // ─── PROCESS TOURNAMENT ─────────────────────────────────────────────

    private function processScheduledTournament(
        array $tournamentData,
        Sport $sport,
        bool $force,
        bool $importTeams,
        bool $downloadLogos
    ): void {
        $this->stats['tournaments_processed']++;

        try {
            $tournament = $tournamentData['tournament'] ?? [];
            $uniqueTournament = $tournament['uniqueTournament'] ?? [];
            $category = $tournament['category'] ?? $uniqueTournament['category'] ?? [];

            $sofascoreId = $uniqueTournament['id'] ?? null;
            $name = $uniqueTournament['name'] ?? $tournament['name'] ?? null;
            $slug = $uniqueTournament['slug'] ?? $tournament['slug'] ?? null;

            $dateForMarker = basename($this->cacheDirectory);
            $leagueMarker = storage_path("app/sofascore_cache/{$this->sportSlug}_LEAGUE_DONE_{$dateForMarker}_{$sofascoreId}");
            if ($sofascoreId && file_exists($leagueMarker) && !$force) {
                $this->stats['leagues_skipped']++;
                $this->line("   ⏭️ Ligue déjà importée (marker présent): {$name} (sofascore_id: {$sofascoreId})");
                return;
            }

            if (!$sofascoreId || !$name) {
                $this->line("   ⏭️ Tournoi ignoré (données incomplètes)");
                $this->stats['leagues_skipped']++;
                return;
            }

            $categoryName = $category['name'] ?? 'N/A';
            $this->line("");
            $this->line("   🏆 Ligue: {$name} (Sofascore ID: {$sofascoreId})");
            $this->line("   🌍 Catégorie: {$categoryName}");

            $country = $this->findOrCreateCountry($category);

            if (!$country) {
                $this->stats['errors']++;
                $this->error("   ❌ Pays introuvable pour la catégorie: {$categoryName}");
                Log::warning("Pays introuvable pour le tournoi ({$this->sportSlug} from-cache)", [
                    'tournament' => $name,
                    'category' => $category,
                ]);
                return;
            }

            $this->line("   ✅ Pays: {$country->name} (ID: {$country->id})");

            // tournament.priority (pas uniqueTournament.priority) : échelle Sofascore
            // de l'importance du championnat, utilisée par MatchController::today()
            // pour faire remonter les grandes ligues en premier dans "Matchs du jour".
            $priority = $tournament['priority'] ?? null;
            $league = $this->createOrUpdateLeague($sofascoreId, $name, $slug, $country, $sport, $force, $priority);

            if (!$league) {
                $this->error("   ❌ Échec création/mise à jour de la ligue {$name}");
                return;
            }

            if ($downloadLogos) {
                $this->line("   📸 Téléchargement logo ligue...");
                $this->downloadLeagueLogos($league, $force);
            }

            if ($importTeams) {
                $this->line("   👥 Import des équipes depuis le cache...");
                $this->importTeamsFromCache($league, $force, $downloadLogos);
            }

            try {
                if (!empty($leagueMarker)) {
                    @file_put_contents($leagueMarker, json_encode(['done_at' => time(), 'sofascore_id' => $sofascoreId, 'name' => $name]));
                }
            } catch (\Throwable $e) {
                Log::warning("Impossible d'écrire le marker de ligue ({$this->sportSlug})", ['file' => $leagueMarker ?? null, 'error' => $e->getMessage()]);
            }
        } catch (\Exception $e) {
            $this->stats['errors']++;
            $tournamentName = $tournamentData['tournament']['uniqueTournament']['name'] ?? 'unknown';
            $this->error("   ❌ Exception pour le tournoi {$tournamentName}: {$e->getMessage()}");
            Log::error("Erreur traitement tournoi ({$this->sportSlug} from-cache)", [
                'tournament_data' => $tournamentName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // ─── COUNTRY ────────────────────────────────────────────────────────

    private function findOrCreateCountry(array $categoryData): ?Country
    {
        if (empty($categoryData)) {
            return null;
        }

        $name = $categoryData['name'] ?? null;
        $slug = $categoryData['slug'] ?? null;
        $alpha2 = $categoryData['alpha2'] ?? null;

        if (!$name) {
            return null;
        }

        if ($alpha2) {
            $country = Country::where('code', $alpha2)->first();
            if ($country) {
                return $country;
            }
        }

        $country = Country::where('name', $name)->first();
        if ($country) {
            return $country;
        }

        if ($slug) {
            $country = Country::where('slug', $slug)->first();
            if ($country) {
                return $country;
            }
        }

        try {
            $country = Country::create([
                'name' => $name,
                'code' => $alpha2,
                'slug' => $slug ?: Str::slug($name),
                'img' => null,
            ]);

            $this->stats['countries_created']++;
            $this->line("   🏴 Pays créé: {$country->name}");

            Log::info("Pays créé automatiquement ({$this->sportSlug} from-cache)", [
                'name' => $name,
                'code' => $alpha2,
                'slug' => $slug,
            ]);

            return $country;
        } catch (\Exception $e) {
            Log::error("Erreur création pays ({$this->sportSlug} from-cache)", [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── LEAGUE ─────────────────────────────────────────────────────────

    private function createOrUpdateLeague(
        int $sofascoreId,
        string $name,
        ?string $slug,
        Country $country,
        Sport $sport,
        bool $force,
        ?int $priority = null
    ): ?League {
        try {
            $existingLeague = League::where('sofascore_id', $sofascoreId)->first();

            if ($existingLeague && !$force) {
                $this->stats['leagues_skipped']++;
                $this->line("   ⏭️ Ligue existante: {$existingLeague->name} (ID: {$existingLeague->id})");

                // Backfill/rafraîchissement de priority même en mode normal (sans
                // --force) : ce chemin "ligue existante" ne repasse jamais par le
                // updateOrCreate ci-dessous, donc sans ça la vraie échelle Sofascore
                // ne remplacerait jamais un ancien flag manuel 0/1.
                if (!is_null($priority) && $existingLeague->priority !== $priority) {
                    $existingLeague->update(['priority' => $priority]);
                }
                return $existingLeague;
            }

            $league = League::updateOrCreate(
                ['sofascore_id' => $sofascoreId],
                [
                    'name' => $name,
                    'slug' => $slug ?: Str::slug($name),
                    'country_id' => $country->id,
                    'sport_id' => $sport->id,
                    'priority' => $priority ?? 0,
                ]
            );

            if ($existingLeague) {
                $this->stats['leagues_updated']++;
                $this->line("   🔄 Ligue mise à jour: {$name} (ID: {$league->id})");
                ActivityLogger::leagueUpdated($name, $league->id, $league->img ?? null, $this->sportSlug, "{$this->sportSlug}:import-from-cache");
            } else {
                $this->stats['leagues_created']++;
                $this->info("   ✅ Ligue créée: {$name} (ID: {$league->id})");
                ActivityLogger::leagueCreated($name, $league->id, $league->img ?? null, $this->sportSlug, "{$this->sportSlug}:import-from-cache");
            }

            return $league;
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error("Erreur création/mise à jour ligue ({$this->sportSlug} from-cache)", [
                'sofascore_id' => $sofascoreId,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── LEAGUE LOGOS ───────────────────────────────────────────────────

    private function downloadLeagueLogos(League $league, bool $force): void
    {
        try {
            $logoService = app(LeagueLogoService::class);
            $result = $logoService->ensureLeagueLogos($league, $force);

            if ($result && !empty($result['img_updated'])) {
                $this->stats['logos_downloaded']++;
            }
        } catch (\Exception $e) {
            Log::warning("Erreur téléchargement logo ligue ({$this->sportSlug} from-cache)", [
                'league_id' => $league->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── TEAMS FROM CACHE ───────────────────────────────────────────────

    private function importTeamsFromCache(League $league, bool $force, bool $downloadLogos): void
    {
        if (!$league->sofascore_id) {
            $this->line("      ⚠️ Pas de sofascore_id pour la ligue {$league->name}");
            return;
        }

        $this->line("      🔍 Recherche du season ID dans le cache...");
        $seasonId = $this->getSeasonIdFromCache($league->sofascore_id);

        if (!$seasonId) {
            $this->stats['season_not_found']++;
            $this->warn("      ⚠️ Saison non trouvée dans le cache pour {$league->name} (sofascore_id: {$league->sofascore_id})");
            return;
        }

        $this->line("      📅 Saison trouvée: ID {$seasonId}");

        $this->line("      📊 Lecture des standings depuis le cache...");
        $teams = $this->getTeamsFromStandingsCache($league->sofascore_id, $seasonId);

        if (empty($teams)) {
            $this->line("      ⚠️ Aucune équipe trouvée dans le cache standings");
            return;
        }

        $this->line("      👥 " . count($teams) . " équipes trouvées dans les standings");

        foreach ($teams as $teamData) {
            $this->processTeam($teamData, $league, $force, $downloadLogos);
        }
    }

    private function getSeasonIdFromCache(int $leagueSofascoreId): ?int
    {
        $cacheFile = $this->cacheDirectory . '/featured_events_' . $leagueSofascoreId . '.json';
        $cached = $this->readCache($cacheFile);

        if ($cached === null || !empty($cached['_negative_cache'])) {
            return null;
        }

        $events = $cached['featuredEvents'] ?? [];
        return !empty($events) ? ($events[0]['season']['id'] ?? null) : null;
    }

    private function getTeamsFromStandingsCache(int $leagueSofascoreId, int $seasonId): array
    {
        $cacheFile = $this->cacheDirectory . '/standings_' . $leagueSofascoreId . '_' . $seasonId . '.json';
        $cached = $this->readCache($cacheFile);

        if ($cached === null || !empty($cached['_negative_cache'])) {
            return [];
        }

        $teams = [];
        foreach (($cached['standings'] ?? []) as $standing) {
            foreach (($standing['rows'] ?? []) as $row) {
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

    // ─── PROCESS TEAM ───────────────────────────────────────────────────

    private function processTeam(array $teamData, League $league, bool $force, bool $downloadLogos): void
    {
        try {
            $sofascoreId = $teamData['id'] ?? null;
            $name = $teamData['name'] ?? null;
            $slug = $teamData['slug'] ?? null;
            $shortName = $teamData['shortName'] ?? null;

            if (!$sofascoreId || !$name || !$slug) {
                $this->stats['teams_skipped']++;
                $this->warn("         ⚠️ Équipe ignorée (données incomplètes: id=" . ($sofascoreId ?? 'null') . ", name=" . ($name ?? 'null') . ", slug=" . ($slug ?? 'null') . ")");
                return;
            }

            $this->stats['teams_processed']++;
            $this->line("         🔍 Traitement: {$name} (sofascore_id: {$sofascoreId}, slug: {$slug}" . ($shortName ? ", short: {$shortName}" : '') . ")");

            if (!$force && $this->teamPlayersCacheExists($sofascoreId, $name)) {
                $this->stats['teams_skipped']++;
                $this->line("         ⏭️ Cache par équipe présent — skip: {$name} (sofascore_id: {$sofascoreId})");
                return;
            }

            $existingTeam = Team::where('sofascore_id', $sofascoreId)->first();

            if ($existingTeam && !$force) {
                $existingTeam->leagues()->syncWithoutDetaching([$league->id]);
                if ($downloadLogos && empty($existingTeam->img)) {
                    $this->downloadTeamLogo($existingTeam, false);
                }
                $this->stats['teams_skipped']++;
                $this->line("         ⏭️ Équipe existante: {$name} (ID: {$existingTeam->id})");
                return;
            }

            $duplicateByName = Team::where('name', $name)
                ->where('sofascore_id', '!=', $sofascoreId)
                ->whereHas('leagues', function ($q) use ($league) {
                    $q->where('leagues.id', $league->id);
                })->first();

            if ($duplicateByName) {
                $this->stats['duplicates_detected']++;
                $this->warn("         ⚠️ Doublon potentiel: '{$name}' existe déjà (team ID: {$duplicateByName->id}, sofascore_id: {$duplicateByName->sofascore_id})");
                Log::warning("Doublon potentiel détecté ({$this->sportSlug} from-cache)", [
                    'sofascore_id' => $sofascoreId,
                    'name' => $name,
                    'league_id' => $league->id,
                    'duplicate_id' => $duplicateByName->id,
                ]);
            }

            $short = trim((string) $shortName);

            $attributes = [
                'name' => $name,
                'slug' => $slug,
                'sofascore_id' => $sofascoreId,
                'league_id' => $league->id,
            ];

            if ($existingTeam) {
                $existingTeam->update($attributes);
                $team = $existingTeam;
                if ($short !== '') {
                    $team->addNickname($short);
                }
                $this->stats['teams_updated']++;
                $this->line("         🔄 Équipe mise à jour: {$name} (ID: {$team->id}, nickname: {$team->nickname})");
                $teamWasCreated = false;
            } else {
                $team = Team::create($attributes);
                if ($short !== '') {
                    $team->addNickname($short);
                }
                $this->stats['teams_created']++;
                $this->info("         ✅ Équipe créée: {$name} (ID: {$team->id}, nickname: {$team->nickname})");
                $teamWasCreated = true;
            }

            // Pivot league_team + logo : toujours exécuté, quel que soit le chemin
            // (create ou update) — c'est précisément ce bloc que la copie handball
            // avait perdu, laissant des équipes sans pivot ni logo.
            $team->leagues()->syncWithoutDetaching([$league->id]);
            $this->line("         🔗 Pivot league_team synchronisé (league: {$league->id}, team: {$team->id})");

            if ($downloadLogos) {
                $this->line("         📸 Téléchargement logo pour {$name}...");
                $this->downloadTeamLogo($team, $force);
                $team->refresh();
            }

            // Joueurs de l'équipe depuis le cache (top-players/overall), remplace
            // l'ancienne commande players:import-by-team qui appelait l'API
            // Sofascore en direct (bloquée en 403 depuis le serveur de prod).
            $this->importPlayersForTeam($team, $downloadLogos);

            if ($teamWasCreated) {
                ActivityLogger::teamCreated($name, $team->id, $team->img ?? null, $this->sportSlug, "{$this->sportSlug}:import-from-cache");
            } else {
                ActivityLogger::teamUpdated($name, $team->id, $team->img ?? null, $this->sportSlug, ['league' => $league->name], "{$this->sportSlug}:import-from-cache");
            }
        } catch (\Exception $e) {
            $this->stats['errors']++;
            $this->error("         ❌ Erreur équipe " . ($teamData['name'] ?? 'unknown') . ": {$e->getMessage()}");
            Log::error("Erreur traitement équipe ({$this->sportSlug} from-cache)", [
                'team_data' => $teamData['name'] ?? 'unknown',
                'league_id' => $league->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // ─── TEAM LOGOS ─────────────────────────────────────────────────────

    private function downloadTeamLogo(Team $team, bool $force): void
    {
        try {
            if (!$team->sofascore_id) {
                $this->warn("         ⚠️ Pas de sofascore_id pour {$team->name}, logo ignoré");
                return;
            }

            $logoPath = "team_logos/{$team->id}.png";
            $destinationDir = storage_path('app/public/team_logos');
            $destinationPath = $destinationDir . '/' . $team->id . '.png';

            if (file_exists($destinationPath) && !$force) {
                if ($team->img !== $logoPath) {
                    $team->update(['img' => $logoPath]);
                }
                $this->line("         ⏭️ Logo déjà présent pour {$team->name}");
                $this->stats['logos_skipped']++;
                return;
            }

            $cacheLogoPath = $this->cacheDirectory . '/team_logos/' . $team->sofascore_id . '.png';

            if (!file_exists($cacheLogoPath)) {
                $this->line("         ⚠️ Logo non trouvé dans le cache pour {$team->name} (sofascore_id: {$team->sofascore_id})");
                $this->stats['logos_missing']++;
                return;
            }

            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }

            if (copy($cacheLogoPath, $destinationPath)) {
                $team->update(['img' => $logoPath]);
                $this->stats['logos_downloaded']++;
                $this->line("         📸 Logo copié: {$team->name} ({$team->sofascore_id}.png → {$team->id}.png)");
            } else {
                $this->warn("         ⚠️ Échec de la copie du logo pour {$team->name}");
                $this->stats['logos_failed']++;
            }
        } catch (\Exception $e) {
            Log::warning("Erreur copie logo équipe ({$this->sportSlug} from-cache)", [
                'team_id' => $team->id,
                'sofascore_id' => $team->sofascore_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── PLAYERS FROM CACHE ─────────────────────────────────────────────

    /**
     * Importe l'effectif complet d'une équipe depuis le cache
     * team_players/{sofascoreId}.json (fetch navigateur fait en Phase 1 par
     * fetch_sofascore_cache.py via team/{id}/players — aucun appel API depuis
     * cette commande, contrairement à l'ancienne players:import-by-team qui
     * appelait Sofascore en direct et plantait en 403 sur le serveur).
     *
     * team/{id}/players renvoie le vrai effectif (contrairement à
     * team/.../top-players/overall, testé puis abandonné en développement :
     * ne renvoie que les meneurs statistiques par catégorie et 404 pour de
     * nombreuses petites équipes/compétitions).
     *
     * Si un joueur existe déjà mais que ses données diffèrent (en particulier
     * team_id), c'est qu'il a changé d'équipe entre-temps : on met à jour au
     * lieu de l'ignorer, pour refléter le transfert.
     */
    private function importPlayersForTeam(Team $team, bool $downloadImages): void
    {
        if (!$team->sofascore_id) {
            return;
        }

        $cacheFile = $this->cacheDirectory . '/team_players/' . $team->sofascore_id . '.json';
        $cached = $this->readCache($cacheFile);

        if ($cached === null || !empty($cached['_negative_cache'])) {
            return;
        }

        $seen = [];
        foreach ($cached['players'] ?? [] as $entry) {
            $playerData = $entry['player'] ?? null;
            if (!$playerData || empty($playerData['id']) || isset($seen[$playerData['id']])) {
                continue;
            }
            $seen[$playerData['id']] = true;
            $this->processPlayer($playerData, $team, $downloadImages);
        }
    }

    /**
     * Crée ou met à jour un joueur. Contrairement à l'ancienne commande
     * (qui ignorait tout joueur déjà existant sauf --force), on compare les
     * attributs et on met à jour dès qu'ils diffèrent — un changement de
     * team_id signifie que le joueur a été transféré, il faut le refléter
     * même sans --force.
     */
    private function processPlayer(array $playerData, Team $team, bool $downloadImages): void
    {
        try {
            $sofascoreId = $playerData['id'] ?? null;
            $name = $playerData['name'] ?? null;
            $slug = $playerData['slug'] ?? null;

            if (!$sofascoreId || !$name || !$slug) {
                $this->stats['players_skipped']++;
                return;
            }

            $this->stats['players_processed']++;

            $attributes = [
                'name' => $name,
                'slug' => $slug,
                'short_name' => $playerData['shortName'] ?? null,
                'position' => $playerData['position'] ?? null,
                'sofascore_id' => $sofascoreId,
                'team_id' => $team->id,
                'user_count' => $playerData['userCount'] ?? null,
                'field_translations' => $playerData['fieldTranslations'] ?? null,
            ];

            $existingPlayer = Player::where('sofascore_id', $sofascoreId)->first();

            if (!$existingPlayer) {
                $player = Player::create($attributes);
                $this->stats['players_created']++;
                $this->line("         ✅ Joueur créé: {$name} (ID: {$sofascoreId})");
            } else {
                $changedKeys = [];
                foreach ($attributes as $key => $value) {
                    if ($key === 'field_translations') {
                        continue; // structure variable, non significative pour détecter un changement
                    }
                    if ($existingPlayer->{$key} != $value) {
                        $changedKeys[] = $key;
                    }
                }

                if (empty($changedKeys)) {
                    $this->stats['players_skipped']++;
                    $player = $existingPlayer;
                } else {
                    $previousTeamId = $existingPlayer->team_id;
                    $existingPlayer->update($attributes);
                    $player = $existingPlayer;
                    $this->stats['players_updated']++;

                    if (in_array('team_id', $changedKeys, true) && $previousTeamId !== $team->id) {
                        $this->stats['players_transferred']++;
                        $this->line("         🔀 Joueur transféré: {$name} (team {$previousTeamId} → {$team->id})");
                    } else {
                        $this->line("         🔄 Joueur mis à jour: {$name} (ID: {$sofascoreId}, champs modifiés: " . implode(', ', $changedKeys) . ')');
                    }
                }
            }

            if ($downloadImages) {
                $this->downloadPlayerImage($player);
            }
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error("Erreur traitement joueur ({$this->sportSlug} from-cache)", [
                'player_data' => $playerData['name'] ?? 'unknown',
                'team_id' => $team->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Copie l'image d'un joueur depuis le cache vers le stockage public.
     * Cache source : {cacheDirectory}/player_images/{sofascore_id}.png
     * Destination  : storage/app/public/player_images/{player->id}.png
     */
    private function downloadPlayerImage(Player $player): void
    {
        try {
            if (!$player->sofascore_id) {
                return;
            }

            $imgPath = "player_images/{$player->id}.png";
            $destinationDir = storage_path('app/public/player_images');
            $destinationPath = $destinationDir . '/' . $player->id . '.png';

            if (file_exists($destinationPath)) {
                if ($player->img !== $imgPath) {
                    $player->update(['img' => $imgPath]);
                }
                return;
            }

            $cacheImagePath = $this->cacheDirectory . '/player_images/' . $player->sofascore_id . '.png';
            if (!file_exists($cacheImagePath)) {
                return;
            }

            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }

            if (copy($cacheImagePath, $destinationPath)) {
                $player->update(['img' => $imgPath]);
                $this->stats['player_images_downloaded']++;
            }
        } catch (\Exception $e) {
            Log::warning("Erreur copie image joueur ({$this->sportSlug} from-cache)", [
                'player_id' => $player->id,
                'sofascore_id' => $player->sofascore_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── SPORT ──────────────────────────────────────────────────────────

    /**
     * Résout le Sport local par id (le plus robuste — connu et stable pour
     * chaque sport de la config), avec repli par nom/slug si jamais l'id
     * attendu ne correspond pas (base modifiée manuellement, migration
     * différente selon l'environnement, etc.).
     */
    private function resolveSport(): ?Sport
    {
        $label = $this->sportConfig['label'];
        $sport = Sport::find($this->sportConfig['sport_id']);

        if (!$sport) {
            $sport = Sport::where('name', 'like', '%' . $label . '%')
                ->orWhere('slug', $this->sportSlug)
                ->first();
        }

        if (!$sport) {
            $this->error("❌ Sport {$label} introuvable en base de données.");
            $this->error("   Assurez-vous qu'un sport avec id={$this->sportConfig['sport_id']} ou nom '{$label}' existe.");
            return null;
        }

        $this->info("{$this->sportConfig['emoji']} Sport: {$sport->name} (ID: {$sport->id})");
        return $sport;
    }

    // ─── STATS ──────────────────────────────────────────────────────────

    private function displayStats(): void
    {
        $label = $this->sportConfig['label'];

        $this->newLine();
        $this->info("🏁 Import {$label} (from-cache) terminé!");
        $this->newLine();
        $this->info('📊 === Statistiques ===');
        $this->line("📄 Pages cache chargées: {$this->stats['pages_loaded']}");
        $this->line("🏆 Tournois traités: {$this->stats['tournaments_processed']}");
        $this->line("🏴 Pays créés: {$this->stats['countries_created']}");
        $this->line("✅ Ligues créées: {$this->stats['leagues_created']}");
        $this->line("🔄 Ligues mises à jour: {$this->stats['leagues_updated']}");
        $this->line("⏭️  Ligues ignorées: {$this->stats['leagues_skipped']}");

        if ($this->stats['teams_processed'] > 0) {
            $this->line("👥 Équipes traitées: {$this->stats['teams_processed']}");
            $this->line("✅ Équipes créées: {$this->stats['teams_created']}");
            $this->line("🔄 Équipes mises à jour: {$this->stats['teams_updated']}");
            $this->line("⏭️  Équipes ignorées: {$this->stats['teams_skipped']}");
            $this->line("🔄 Doublons détectés: {$this->stats['duplicates_detected']}");
        }

        if ($this->stats['players_processed'] > 0) {
            $this->line("🧑‍🤝‍🧑 Joueurs traités: {$this->stats['players_processed']}");
            $this->line("✅ Joueurs créés: {$this->stats['players_created']}");
            $this->line("🔄 Joueurs mis à jour: {$this->stats['players_updated']}");
            $this->line("🔀 Joueurs transférés (changement d'équipe): {$this->stats['players_transferred']}");
            $this->line("⏭️  Joueurs inchangés/ignorés: {$this->stats['players_skipped']}");
            $this->line("📸 Images joueurs téléchargées: {$this->stats['player_images_downloaded']}");
        }

        $this->line("📅 Saisons non trouvées: {$this->stats['season_not_found']}");
        $this->line("📸 Logos téléchargés: {$this->stats['logos_downloaded']}");
        $this->line("⏭️  Logos ignorés (déjà présents): {$this->stats['logos_skipped']}");
        $this->line("⚠️  Logos manquants dans le cache: {$this->stats['logos_missing']}");
        $this->line("❌  Logos échoués à la copie: {$this->stats['logos_failed']}");
        $this->line("❌ Erreurs: {$this->stats['errors']}");

        $totalLeagues = $this->stats['leagues_created'] + $this->stats['leagues_updated'];
        $totalTeams = $this->stats['teams_created'] + $this->stats['teams_updated'];
        $this->line("📋 Total ligues ajoutées/modifiées: {$totalLeagues}");
        $this->line("📋 Total équipes ajoutées/modifiées: {$totalTeams}");

        $this->newLine();
        $this->info("{$this->sportConfig['emoji']} === MATCHS ===");
        $this->line("⚽ Matchs traités: {$this->stats['matches_processed']}");
        $this->line("✅ Matchs créés: {$this->stats['matches_created']}");
        $this->line("🔄 Matchs mis à jour: {$this->stats['matches_updated']}");
        $this->line("⏭️  Matchs ignorés: {$this->stats['matches_skipped']}");
        $this->line("❌ Erreurs matchs: {$this->stats['matches_errors']}");

        Log::info("Import {$this->sportSlug} from-cache terminé", $this->stats);

        ActivityLogger::importFinished("{$this->sportSlug}:import-from-cache", $this->sportSlug, [
            'tournaments' => $this->stats['tournaments_processed'] ?? 0,
            'leagues_created' => $this->stats['leagues_created'] ?? 0,
            'leagues_updated' => $this->stats['leagues_updated'] ?? 0,
            'teams_created' => $this->stats['teams_created'] ?? 0,
            'teams_updated' => $this->stats['teams_updated'] ?? 0,
            'errors' => $this->stats['errors'] ?? 0,
        ]);
        ActivityLogger::flush();
    }
}
