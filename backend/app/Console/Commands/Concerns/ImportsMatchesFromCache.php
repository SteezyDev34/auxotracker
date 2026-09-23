<?php

namespace App\Console\Commands\Concerns;

use App\Models\League;
use App\Models\MatchModel;
use App\Models\Sport;
use App\Models\Team;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Import des matchs (schedule) depuis les fichiers de cache Sofascore
 * `featured_events_*.json` vers la table `matches`.
 *
 * Extrait du pattern déjà en place pour le tennis
 * (ImportTennisPlayersFromCache::processEventCacheFiles /
 * processMatchFromCache) et généralisé à tous les sports dont le fetch
 * (fetch_sofascore_cache.py) produit ce même format de fichier.
 *
 * Persistés : matchs à venir ET matchs en cours (live). Seuls les matchs
 * terminés (status type = finished) sont ignorés, sauf si --force est actif.
 * Aucun appel API ici : lecture seule du cache.
 */
trait ImportsMatchesFromCache
{
    /**
     * Parcourt {cacheDirectory}/featured_events_*.json et persiste chaque
     * événement en base via MatchModel::updateOrCreate.
     *
     * @param string $cacheDirectory Répertoire contenant les featured_events_*.json
     * @param int    $sofascoreSportId sofascore_id du sport (table sports)
     * @param string $sportSlug        Slug utilisé dans l'URL sofascore.com (ex: 'football')
     * @param bool   $force            Persister aussi les matchs terminés
     * @return array Statistiques : processed, created, updated, skipped, errors
     */
    protected function importMatchesFromCache(
        string $cacheDirectory,
        int $sofascoreSportId,
        string $sportSlug,
        bool $force
    ): array {
        $stats = [
            'matches_processed' => 0,
            'matches_created'   => 0,
            'matches_updated'   => 0,
            'matches_unchanged' => 0,
            'matches_skipped'   => 0,
            'matches_errors'    => 0,
        ];

        // tournament_events_{uid}.json : programme réel du jour, écrit par
        // fetch_generic_scheduled_events() (fetch_sofascore_cache.py) via l'endpoint
        // unique-tournament/{uid}/scheduled-events/{date} (bloqué en HTTP direct côté
        // PHP — 403 confirmé — d'où le passage par Chrome headless en amont).
        // ⚠️ Ne PAS lire featured_events_*.json ici : c'est un seul match "à la une"
        // par tournoi (souvent hors-date, parfois déjà terminé), pas le programme du jour.
        $eventFiles = glob($cacheDirectory . '/tournament_events_*.json');

        if (empty($eventFiles)) {
            $this->line("📌 Aucun fichier tournament_events_*.json dans le cache ({$sportSlug}) — aucun match à importer");
            return $stats;
        }

        $this->line("\n⚽ Importation des matchs {$sportSlug} depuis le cache: " . count($eventFiles) . " fichier(s)");

        $sport   = Sport::where('sofascore_id', $sofascoreSportId)->first();
        $sportId = $sport?->id;

        foreach ($eventFiles as $eventFile) {
            try {
                $data = json_decode(file_get_contents($eventFile), true);

                if (!empty($data['_negative_cache'])) {
                    continue;
                }

                $events = $data['events'] ?? [];

                if (empty($events)) {
                    continue;
                }

                foreach ($events as $event) {
                    $this->persistMatchFromCacheEvent($event, $sofascoreSportId, $sportId, $sportSlug, $force, $stats);
                }
            } catch (\Exception $e) {
                $stats['matches_errors']++;
                Log::error("Erreur lecture fichier événement {$sportSlug}", [
                    'file'  => $eventFile,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->line("✅ Matchs {$sportSlug} traités: {$stats['matches_processed']} | créés: {$stats['matches_created']} | màj: {$stats['matches_updated']} | déjà à jour: {$stats['matches_unchanged']} | ignorés: {$stats['matches_skipped']}");

        return $stats;
    }

    private function persistMatchFromCacheEvent(
        array $event,
        int $sofascoreSportId,
        ?int $sportId,
        string $sportSlug,
        bool $force,
        array &$stats
    ): void {
        $stats['matches_processed']++;

        $eventId    = $event['id'] ?? null;
        $startTs    = $event['startTimestamp'] ?? null;
        $homeTeam   = $event['homeTeam'] ?? null;
        $awayTeam   = $event['awayTeam'] ?? null;
        $statusType = $event['status']['type'] ?? null; // 'notstarted' | 'inprogress' | 'finished'

        if (empty($startTs)) {
            $this->line("   [match] Pas de startTimestamp pour event_id={$eventId} — ignoré");
            Log::warning('match_persist_skip_no_timestamp', ['event_id' => $eventId, 'sport' => $sportSlug]);
            $stats['matches_skipped']++;
            return;
        }

        try {
            $tz      = new \DateTimeZone('Europe/Paris');
            $eventDt = new \DateTime('@' . (int) $startTs);
            $eventDt->setTimezone($tz);

            // Ignorer uniquement les matchs terminés (sauf --force)
            if ($statusType === 'finished' && !$force) {
                $this->line("   [match] event_id={$eventId} terminé (status=finished) — ignoré");
                Log::info('match_persist_skip_finished', ['event_id' => $eventId, 'start' => $eventDt->format('Y-m-d H:i:s')]);
                $stats['matches_skipped']++;
                return;
            }

            $team1Id          = $homeTeam['id'] ?? null;
            $team2Id          = $awayTeam['id'] ?? null;
            $tournamentSofaId = $event['tournament']['uniqueTournament']['id'] ?? $event['tournament']['id'] ?? null;
            $tournamentName   = $event['tournament']['uniqueTournament']['name'] ?? $event['tournament']['name'] ?? null;
            $slug             = $event['slug'] ?? '';
            $customId         = $event['customId'] ?? '';
            $sofascoreLink    = "https://www.sofascore.com/fr/{$sportSlug}/match/{$slug}/{$customId}#id:{$eventId}";

            // Le match référence des sofascore_id d'équipe qui peuvent ne jamais
            // avoir été créées par l'import ligues/équipes (ex: compétitions
            // amateurs sans standings) — sans ça la ligne de match s'affiche
            // sans nom d'équipe côté frontend malgré une jointure correcte.
            $effectiveSportId = $sportId ?? $sofascoreSportId;
            if ($team1Id) {
                $this->ensureTeamExists((int) $team1Id, $homeTeam['name'] ?? null, $effectiveSportId, $tournamentSofaId, $tournamentName);
            }
            if ($team2Id) {
                $this->ensureTeamExists((int) $team2Id, $awayTeam['name'] ?? null, $effectiveSportId, $tournamentSofaId, $tournamentName);
            }

            $record = MatchModel::updateOrCreate(
                ['event_id' => $eventId],
                [
                    'team_1_sofascore_id'     => $team1Id,
                    'team_2_sofascore_id'     => $team2Id,
                    'match_start_date'        => $eventDt->format('Y-m-d'),
                    'match_start_time'        => $eventDt->format('H:i:s'),
                    'tournament_sofascore_id' => $tournamentSofaId,
                    'sport_id'                => $sportId ?? $sofascoreSportId,
                    'sofascore_link'          => $sofascoreLink,
                ]
            );

            // wasChanged() reflète les attributs réellement modifiés par le
            // save() qui vient d'avoir lieu — sans cette vérification, un
            // match déjà à jour s'affichait quand même comme "mis à jour"
            // alors qu'Eloquent court-circuite l'UPDATE quand rien n'est dirty.
            $action = $record->wasRecentlyCreated ? 'créé' : ($record->wasChanged() ? 'mis à jour' : 'déjà à jour');
            $this->line("   [match] {$action} en BDD (id={$record->id}) event_id={$eventId}");
            Log::info('match_persisted', [
                'action'     => $action,
                'id'         => $record->id,
                'event_id'   => $eventId,
                'start'      => $eventDt->format('Y-m-d H:i:s'),
                'team1'      => $team1Id,
                'team2'      => $team2Id,
                'tournament' => $tournamentSofaId,
                'sport'      => $sportSlug,
                'link'       => $sofascoreLink,
            ]);

            if ($record->wasRecentlyCreated) {
                $stats['matches_created']++;
                ActivityLogger::matchCreated("event #{$eventId}", $record->id, $sportSlug, "{$sportSlug}:import-from-cache", ['event_id' => $eventId, 'link' => $sofascoreLink]);
            } elseif ($record->wasChanged()) {
                $stats['matches_updated']++;
            } else {
                $stats['matches_unchanged']++;
            }
        } catch (\Throwable $e) {
            $stats['matches_errors']++;
            $this->error("   [match] ERREUR persistance event_id={$eventId} : " . $e->getMessage());
            Log::error('match_persist_error', [
                'event_id' => $eventId,
                'sport'    => $sportSlug,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Crée une équipe minimale (nom + sofascore_id) si elle n'existe pas déjà,
     * en la rattachant à une ligue elle-même créée à la volée si besoin
     * (teams.league_id est NOT NULL). Ne fait rien si les données sont
     * insuffisantes (pas de nom) ou si l'équipe existe déjà.
     */
    private function ensureTeamExists(
        int $sofascoreTeamId,
        ?string $teamName,
        int $sportId,
        ?int $tournamentSofascoreId,
        ?string $tournamentName
    ): void {
        // Le tournoi doit être garanti d'exister À CHAQUE match, indépendamment
        // de si l'équipe/le joueur existe déjà — sinon un joueur déjà connu
        // (importé par ailleurs, ex: liste globale des joueurs tennis) mais
        // jouant dans un tournoi jamais créé laisse le match sans compétition.
        $league = $this->ensureLeagueExists($tournamentSofascoreId, $tournamentName, $sportId);

        if (Team::where('sofascore_id', $sofascoreTeamId)->exists()) {
            return;
        }
        if (empty($teamName) || !$league) {
            return;
        }

        try {
            $slug = Str::slug($teamName) . '-' . $sofascoreTeamId;
            $team = Team::create([
                'name'         => $teamName,
                'slug'         => $slug,
                'sofascore_id' => $sofascoreTeamId,
                'league_id'    => $league->id,
            ]);
            $team->leagues()->syncWithoutDetaching([$league->id]);

            try {
                app(\App\Services\TeamLogoService::class)->ensureTeamLogo($team);
            } catch (\Throwable $e) {
                Log::warning('team_logo_from_match_import_failed', [
                    'team_id' => $team->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            Log::info('team_created_from_match_import', [
                'team_id'      => $team->id,
                'sofascore_id' => $sofascoreTeamId,
                'name'         => $teamName,
                'league_id'    => $league->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('team_create_from_match_import_failed', [
                'sofascore_id' => $sofascoreTeamId,
                'name'         => $teamName,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Garantit qu'une League existe pour ce tournoi (sofascore_id + sport_id),
     * la créant a minima (nom + slug) si besoin. Appelée à chaque match, pas
     * seulement à la création d'équipe — un joueur/équipe déjà connu peut très
     * bien jouer dans un tournoi qui, lui, n'a encore jamais été créé.
     */
    private function ensureLeagueExists(?int $tournamentSofascoreId, ?string $tournamentName, int $sportId): ?League
    {
        if (!$tournamentSofascoreId) {
            return null;
        }

        try {
            $league = League::where('sofascore_id', $tournamentSofascoreId)
                ->where('sport_id', $sportId)
                ->first();

            if ($league) {
                return $league;
            }

            $leagueName = $tournamentName ?: 'Compétition inconnue';
            $league = League::firstOrCreate(
                ['sofascore_id' => $tournamentSofascoreId, 'sport_id' => $sportId],
                ['name' => $leagueName, 'slug' => Str::slug($leagueName) . '-' . $tournamentSofascoreId]
            );

            // Ligues créées par ce chemin (via les données de match, pas via le
            // scan de marqueurs de createTournamentLeagues) : sans cet appel
            // explicite, elles ne recevraient jamais de tentative de logo.
            if ($league->wasRecentlyCreated) {
                try {
                    app(\App\Services\LeagueLogoService::class)->ensureLeagueLogos($league);
                } catch (\Throwable $e) {
                    Log::warning('league_logo_from_match_import_failed', [
                        'league_id' => $league->id,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            return $league;
        } catch (\Throwable $e) {
            Log::warning('league_create_from_match_import_failed', [
                'tournament_sofascore_id' => $tournamentSofascoreId,
                'error'                   => $e->getMessage(),
            ]);
            return null;
        }
    }
}
