<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\League;
use App\Models\Sport;
use App\Models\Team;
use App\Services\LeagueLogoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportFootballFromCache extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'football:import-from-cache
                            {date? : Date au format YYYY-MM-DD (défaut: aujourd\'hui)}
                            {--force : Forcer la mise à jour même si les données existent}
                            {--import-teams : Importer aussi les équipes depuis le cache des standings}
                            {--download-logos : Télécharger les logos des ligues et des équipes}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Phase 2 : Importe les ligues et équipes de football depuis les fichiers de cache vers la BDD (aucun appel API). Exécuter football:import-from-schedule d\'abord.';

    /**
     * Répertoire de cache
     */
    private string $cacheDirectory;

    /**
     * Statistiques d'importation
     */
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
        'errors' => 0,
    ];

    /**
     * Exécuter la commande console.
     */
    public function handle(): int
    {
        $date = $this->argument('date') ?? date('Y-m-d');
        $force = (bool) $this->option('force');
        $importTeams = (bool) $this->option('import-teams');
        $downloadLogos = (bool) $this->option('download-logos');

        // Valider le format de la date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error("❌ Format de date invalide: {$date}. Utiliser YYYY-MM-DD.");
            return Command::FAILURE;
        }

        $this->info("⚽ Import Football depuis le cache → BDD (Phase 2)");
        $this->line("📅 Date: {$date}");
        $this->line("🔄 Force: " . ($force ? 'Oui' : 'Non'));
        $this->line("👥 Import équipes: " . ($importTeams ? 'Oui' : 'Non'));
        $this->line("📸 Logos: " . ($downloadLogos ? 'Oui' : 'Non'));
        $this->line("");

        // Initialiser le répertoire de cache
        $this->cacheDirectory = storage_path("app/sofascore_cache/football_schedule/{$date}");
        $this->line("📂 Cache: {$this->cacheDirectory}");

        if (!is_dir($this->cacheDirectory)) {
            $this->error("❌ Répertoire de cache introuvable: {$this->cacheDirectory}");
            $this->error("   Exécutez d'abord: php artisan football:import-from-schedule {$date}");
            return Command::FAILURE;
        }
        $this->line("");

        // Récupérer le sport Football
        $this->line("🔍 Recherche du sport Football en base...");
        $footballSport = $this->getFootballSport();
        if (!$footballSport) {
            return Command::FAILURE;
        }

        // Charger les tournois depuis le cache
        $this->info("📥 Chargement des tournois depuis le cache...");
        $allTournaments = $this->loadTournamentsFromCache();

        $this->line("📊 Total brut de tournois chargés: " . count($allTournaments));

        if (empty($allTournaments)) {
            $this->warn("⚠️ Aucun tournoi trouvé dans le cache pour la date {$date}.");
            $this->warn("   Exécutez d'abord: php artisan football:import-from-schedule {$date}");
            $this->displayStats();
            return Command::SUCCESS;
        }

        // Dédupliquer par uniqueTournament.id
        $uniqueLeagues = $this->deduplicateTournaments($allTournaments);
        $totalLeagues = count($uniqueLeagues);

        $this->info("🏆 {$totalLeagues} ligues uniques trouvées sur {$this->stats['pages_loaded']} page(s)");
        $this->line("");

        // Traiter chaque ligue (toujours verbose, comme Tennis)
        foreach ($uniqueLeagues as $i => $tournamentData) {
            $num = $i + 1;
            $leagueName = $tournamentData['tournament']['uniqueTournament']['name'] ?? 'N/A';
            $this->newLine();
            $this->info("── [{$num}/{$totalLeagues}] {$leagueName} ──");
            $this->processScheduledTournament($tournamentData, $footballSport, $force, $importTeams, $downloadLogos);
        }
        $this->newLine();

        $this->displayStats();
        return Command::SUCCESS;
    }

    // ─── CACHE (lecture seule) ──────────────────────────────────────────

    /**
     * Lire un fichier de cache JSON.
     * Retourne null si fichier absent ou invalide.
     * Si c'est un cache négatif (tombstone < 24h), retourne ['_negative_cache' => true].
     */
    private function readCache(string $cacheFile): ?array
    {
        if (!file_exists($cacheFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($cacheFile), true);
        if (!is_array($data)) {
            return null;
        }

        // Détecter un cache négatif (tombstone)
        if (!empty($data['_negative_cache'])) {
            $cacheAge = time() - ($data['_cached_at'] ?? 0);
            if ($cacheAge < 86400) {
                return $data;
            }
            return null;
        }

        return $data;
    }

    /**
     * Charger tous les tournois depuis les fichiers de cache paginés.
     */
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

    /**
     * Dédupliquer les tournois par uniqueTournament.id.
     */
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

    /**
     * Traiter un tournoi : créer/mettre à jour la ligue, puis optionnellement importer les équipes.
     */
    private function processScheduledTournament(
        array $tournamentData,
        Sport $footballSport,
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

            // Marqueur par ligue (évite de re-traiter une ligue déjà importée pour la même date)
            $dateForMarker = basename($this->cacheDirectory);
            $leagueMarker = storage_path("app/sofascore_cache/football_LEAGUE_DONE_{$dateForMarker}_{$sofascoreId}");
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

            // Étape A : Trouver ou créer le pays
            $country = $this->findOrCreateCountry($category);

            if (!$country) {
                $this->stats['errors']++;
                $this->error("   ❌ Pays introuvable pour la catégorie: {$categoryName}");
                Log::warning('Pays introuvable pour le tournoi (football from-cache)', [
                    'tournament' => $name,
                    'category' => $category,
                ]);
                return;
            }

            $this->line("   ✅ Pays: {$country->name} (ID: {$country->id})");

            // Étape B : Créer ou mettre à jour la ligue
            $league = $this->createOrUpdateLeague($sofascoreId, $name, $slug, $country, $footballSport, $force);

            if (!$league) {
                $this->error("   ❌ Échec création/mise à jour de la ligue {$name}");
                return;
            }

            // Étape C : Télécharger les logos de la ligue
            if ($downloadLogos) {
                $this->line("   📸 Téléchargement logo ligue...");
                $this->downloadLeagueLogos($league, $force);
            }

            // Étape D : Importer les équipes depuis le cache
            if ($importTeams) {
                $this->line("   👥 Import des équipes depuis le cache...");
                $this->importTeamsFromCache($league, $force, $downloadLogos);
            }
            // Si on arrive ici sans exception, écrire un marker indiquant que la ligue a été traitée pour cette date
            try {
                if (!empty($leagueMarker)) {
                    @file_put_contents($leagueMarker, json_encode(['done_at' => time(), 'sofascore_id' => $sofascoreId, 'name' => $name]));
                }
            } catch (\Throwable $e) {
                Log::warning('Impossible d\'écrire le marker de ligue (football)', ['file' => $leagueMarker ?? null, 'error' => $e->getMessage()]);
            }
        } catch (\Exception $e) {
            $this->stats['errors']++;
            $tournamentName = $tournamentData['tournament']['uniqueTournament']['name'] ?? 'unknown';
            $this->error("   ❌ Exception pour le tournoi {$tournamentName}: {$e->getMessage()}");
            Log::error('Erreur traitement tournoi (football from-cache)', [
                'tournament_data' => $tournamentName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // ─── COUNTRY ────────────────────────────────────────────────────────

    /**
     * Trouver ou créer un pays depuis les données de catégorie.
     */
    private function findOrCreateCountry(array $categoryData): ?Country
    {
        if (empty($categoryData)) {
            return null;
        }

        $name = $categoryData['name'] ?? null;
        $slug = $categoryData['slug'] ?? null;
        $alpha2 = $categoryData['alpha2'] ?? null;
        $flag = $categoryData['flag'] ?? null;

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

            Log::info('Pays créé automatiquement (football from-cache)', [
                'name' => $name,
                'code' => $alpha2,
                'slug' => $slug,
            ]);

            return $country;
        } catch (\Exception $e) {
            Log::error('Erreur création pays (football from-cache)', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── LEAGUE ─────────────────────────────────────────────────────────

    /**
     * Créer ou mettre à jour une ligue.
     */
    private function createOrUpdateLeague(
        int $sofascoreId,
        string $name,
        ?string $slug,
        Country $country,
        Sport $sport,
        bool $force
    ): ?League {
        try {
            $existingLeague = League::where('sofascore_id', $sofascoreId)->first();

            if ($existingLeague && !$force) {
                $this->stats['leagues_skipped']++;
                $this->line("   ⏭️ Ligue existante: {$existingLeague->name} (ID: {$existingLeague->id})");
                return $existingLeague;
            }

            $league = League::updateOrCreate(
                ['sofascore_id' => $sofascoreId],
                [
                    'name' => $name,
                    'slug' => $slug ?: Str::slug($name),
                    'country_id' => $country->id,
                    'sport_id' => $sport->id,
                ]
            );

            if ($existingLeague) {
                $this->stats['leagues_updated']++;
                $this->line("   🔄 Ligue mise à jour: {$name} (ID: {$league->id})");
            } else {
                $this->stats['leagues_created']++;
                $this->info("   ✅ Ligue créée: {$name} (ID: {$league->id})");
            }

            return $league;
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('Erreur création/mise à jour ligue (football from-cache)', [
                'sofascore_id' => $sofascoreId,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── LEAGUE LOGOS ───────────────────────────────────────────────────

    /**
     * Télécharger les logos d'une ligue via le LeagueLogoService.
     */
    private function downloadLeagueLogos(League $league, bool $force): void
    {
        try {
            $logoService = app(LeagueLogoService::class);
            $result = $logoService->ensureLeagueLogos($league, $force);

            if ($result && !empty($result['img_updated'])) {
                $this->stats['logos_downloaded']++;
            }
        } catch (\Exception $e) {
            Log::warning('Erreur téléchargement logo ligue (football from-cache)', [
                'league_id' => $league->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── TEAMS FROM CACHE ───────────────────────────────────────────────

    /**
     * Importer les équipes d'une ligue depuis le cache (featured-events + standings).
     * Aucun appel API — lecture cache uniquement.
     */
    private function importTeamsFromCache(League $league, bool $force, bool $downloadLogos): void
    {
        if (!$league->sofascore_id) {
            $this->line("      ⚠️ Pas de sofascore_id pour la ligue {$league->name}");
            return;
        }

        // Lire la saison depuis le cache
        $this->line("      🔍 Recherche du season ID dans le cache...");
        $seasonId = $this->getSeasonIdFromCache($league->sofascore_id);

        if (!$seasonId) {
            $this->stats['season_not_found']++;
            $this->warn("      ⚠️ Saison non trouvée dans le cache pour {$league->name} (sofascore_id: {$league->sofascore_id})");
            return;
        }

        $this->line("      📅 Saison trouvée: ID {$seasonId}");

        // Lire les standings depuis le cache
        $this->line("      📊 Lecture des standings depuis le cache...");
        $teams = $this->getTeamsFromStandingsCache($league->sofascore_id, $seasonId);

        if (empty($teams)) {
            $this->line("      ⚠️ Aucune équipe trouvée dans le cache standings");
            return;
        }

        $this->line("      👥 " . count($teams) . " équipes trouvées dans les standings");

        // Traiter chaque équipe
        foreach ($teams as $teamData) {
            $this->processTeam($teamData, $league, $force, $downloadLogos);
        }
    }

    /**
     * Lire le season ID depuis le cache des featured-events.
     */
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

    /**
     * Lire les équipes depuis le cache des standings.
     */
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

    /**
     * Vérifier si le cache par équipe existe (répertoire teams_players/{slug}-{id}).
     */
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

    /**
     * Traiter une équipe individuelle (créer/mettre à jour + pivot + logo).
     */
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
            // Si un cache local par équipe existe, on considère que le travail joueurs/équipe est fait → skip (sauf --force)
            if (!$force && $this->teamPlayersCacheExists($sofascoreId, $name)) {
                $this->stats['teams_skipped']++;
                $this->line("         ⏭️ Cache par équipe présent — skip: {$name} (sofascore_id: {$sofascoreId})");
                return;
            }

            // Vérifier si l'équipe existe déjà
            $existingTeam = Team::where('sofascore_id', $sofascoreId)->first();

            if ($existingTeam && !$force) {
                $existingTeam->leagues()->syncWithoutDetaching([$league->id]);
                // Copier le logo depuis le cache même si l'équipe existe (si pas encore de logo)
                if ($downloadLogos && empty($existingTeam->img)) {
                    $this->downloadTeamLogo($existingTeam, false);
                }
                $this->stats['teams_skipped']++;
                $this->line("         ⏭️ Équipe existante: {$name} (ID: {$existingTeam->id})");
                return;
            }

            // Vérification des doublons par nom dans la même ligue (via pivot league_team)
            $duplicateByName = Team::where('name', $name)
                ->where('sofascore_id', '!=', $sofascoreId)
                ->whereHas('leagues', function ($q) use ($league) {
                    $q->where('leagues.id', $league->id);
                })->first();

            if ($duplicateByName) {
                $this->stats['duplicates_detected']++;
                $this->warn("         ⚠️ Doublon potentiel: '{$name}' existe déjà (team ID: {$duplicateByName->id}, sofascore_id: {$duplicateByName->sofascore_id})");
                Log::warning('Doublon potentiel détecté (football from-cache)', [
                    'sofascore_id' => $sofascoreId,
                    'name' => $name,
                    'league_id' => $league->id,
                    'duplicate_id' => $duplicateByName->id,
                ]);
            }

            // Ajouter le nickname via Team::addNickname pour éviter les doublons
            $short = trim((string) $shortName);

            // Créer ou mettre à jour l'équipe (sans écrire le champ nickname directement)
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
            } else {
                $team = Team::create($attributes);
                if ($short !== '') {
                    $team->addNickname($short);
                }
                $this->stats['teams_created']++;
                $this->info("         ✅ Équipe créée: {$name} (ID: {$team->id}, nickname: {$team->nickname})");
            }

            // Mettre à jour la table pivot league_team
            $team->leagues()->syncWithoutDetaching([$league->id]);
            $this->line("         🔗 Pivot league_team synchronisé (league: {$league->id}, team: {$team->id})");

            // Télécharger le logo si demandé
            if ($downloadLogos) {
                $this->line("         📸 Téléchargement logo pour {$name}...");
                $this->downloadTeamLogo($team, $force);
            }
        } catch (\Exception $e) {
            $this->stats['errors']++;
            $this->error("         ❌ Erreur équipe " . ($teamData['name'] ?? 'unknown') . ": {$e->getMessage()}");
            Log::error('Erreur traitement équipe (football from-cache)', [
                'team_data' => $teamData['name'] ?? 'unknown',
                'league_id' => $league->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // ─── TEAM LOGOS ─────────────────────────────────────────────────────

    /**
     * Copier le logo d'une équipe depuis le cache vers le stockage public.
     * Cache source : {cacheDirectory}/team_logos/{sofascore_id}.png
     * Destination  : storage/app/public/team_logos/{team->id}.png
     *
     * Aucun appel API — Phase 2 lit uniquement depuis le cache.
     */
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

            // Vérifier si le logo existe déjà en destination
            if (file_exists($destinationPath) && !$force) {
                if ($team->img !== $logoPath) {
                    $team->update(['img' => $logoPath]);
                }
                $this->line("         ⏭️ Logo déjà présent pour {$team->name}");
                $this->stats['logos_skipped']++;
                Log::info('logo_skip_destination_exists', [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'destination' => $destinationPath,
                    'sofascore_id' => $team->sofascore_id,
                    'force' => $force,
                ]);
                return;
            }

            // Chercher le logo dans le cache par sofascore_id
            $cacheLogoPath = $this->cacheDirectory . '/team_logos/' . $team->sofascore_id . '.png';

            if (!file_exists($cacheLogoPath)) {
                $this->line("         ⚠️ Logo non trouvé dans le cache pour {$team->name} (sofascore_id: {$team->sofascore_id})");
                $this->stats['logos_missing']++;
                Log::warning('logo_missing_in_cache', [
                    'team_id' => $team->id ?? null,
                    'team_name' => $team->name,
                    'sofascore_id' => $team->sofascore_id ?? null,
                    'cache_path' => $cacheLogoPath,
                ]);
                return;
            }

            // Créer le répertoire de destination si nécessaire
            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }

            // Copier depuis le cache en renommant sofascore_id → team->id
            if (copy($cacheLogoPath, $destinationPath)) {
                $team->update(['img' => $logoPath]);
                $this->stats['logos_downloaded']++;
                $this->line("         📸 Logo copié: {$team->name} ({$team->sofascore_id}.png → {$team->id}.png)");
                Log::info('logo_copied', [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'sofascore_id' => $team->sofascore_id,
                    'source' => $cacheLogoPath,
                    'destination' => $destinationPath,
                ]);
            } else {
                $this->warn("         ⚠️ Échec de la copie du logo pour {$team->name}");
                $this->stats['logos_failed']++;
                Log::warning('logo_copy_failed', [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'sofascore_id' => $team->sofascore_id,
                    'source' => $cacheLogoPath,
                    'destination' => $destinationPath,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Erreur copie logo équipe (football from-cache)', [
                'team_id' => $team->id,
                'sofascore_id' => $team->sofascore_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── SPORT ──────────────────────────────────────────────────────────

    /**
     * Récupérer le sport Football depuis la base de données.
     */
    private function getFootballSport(): ?Sport
    {
        $sport = Sport::where('sofascore_id', 1)->first();

        if (!$sport) {
            $sport = Sport::where('name', 'like', '%football%')
                ->orWhere('slug', 'football')
                ->first();
        }

        if (!$sport) {
            $this->error("❌ Sport Football introuvable en base de données.");
            $this->error("   Assurez-vous qu'un sport avec sofascore_id=1 ou nom 'Football' existe.");
            return null;
        }

        $this->info("⚽ Sport: {$sport->name} (ID: {$sport->id})");
        return $sport;
    }

    // ─── STATS ──────────────────────────────────────────────────────────

    /**
     * Afficher les statistiques d'importation.
     */
    private function displayStats(): void
    {
        $this->newLine();
        $this->info('🏁 Import Football (from-cache) terminé!');
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

        Log::info('Import football from-cache terminé', $this->stats);
    }
}
