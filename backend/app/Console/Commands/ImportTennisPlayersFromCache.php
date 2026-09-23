<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Console\Commands\Concerns\ImportsMatchesFromCache;
use App\Models\MatchModel;
use App\Models\Team;
use App\Services\ActivityLogger;
use App\Models\League;
use App\Models\Sport;
use App\Models\Country;
use App\Services\TeamLogoService;
use App\Services\LeagueLogoService;
use App\Models\TennisPlayerSeasonStat;
use App\Models\TennisH2hMatch;
use App\Models\TennisMatchOdds;
use App\Models\TennisPlayerGameTensionStat;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportTennisPlayersFromCache extends Command
{
    // Réutilise uniquement ensureTeamExists() (crée équipe + ligue à la volée si
    // absentes) — la lecture de cache reste spécifique au tennis (voir
    // processEventCacheFiles ci-dessous, format différent des autres sports).
    use ImportsMatchesFromCache;

    /**
     * Service de téléchargement des logos
     */
    private $teamLogoService;

    /**
     * Signature de la commande
     */
    protected $signature = 'tennis:import-from-cache
                            {--force : Forcer la mise à jour des joueurs existants}
                            {--limit= : Limiter le nombre de joueurs à traiter}
                            {--import-teams : Importer les joueurs depuis le cache}
                            {--download-images : Télécharge les images des joueurs}
                            {--download-logos : Télécharge les logos des ligues de tournois}
                            {--skip-archive : Ne pas déplacer les fichiers vers processed/ après import (utiliser en local avant rsync)}';

    /**
     * Description de la commande
     */
    protected $description = 'Importer les joueurs de tennis depuis les fichiers de cache vers la base de données';

    /**
     * Répertoire de cache
     */
    protected $cacheDirectory;

    /**
     * Ligue Tennis (ATP/WTA)
     */
    protected $tennisLeague;

    /**
     * Statistiques de traitement
     */
    protected $stats = [
        'players_processed' => 0,
        'players_created' => 0,
        'players_updated' => 0,
        'players_unchanged' => 0,
        'players_skipped' => 0,
        'duplicates_detected' => 0,
        'errors' => 0,
        'cache_files_found' => 0,
        'cache_files_processed' => 0,
        'cache_files_cleaned' => 0,
        'images_downloaded' => 0,
        'images_skipped' => 0,
        'images_missing' => 0,
        'images_failed' => 0,
        'tournament_leagues_created' => 0,
        'tournament_leagues_updated' => 0,
        'tournament_leagues_unchanged' => 0,
        'tournament_leagues_skipped' => 0,
        'tournament_logos_downloaded' => 0,
        'matches_processed' => 0,
        'matches_created' => 0,
        'matches_updated' => 0,
        'matches_unchanged' => 0,
        'matches_skipped' => 0,
        'matches_errors' => 0,
        'rankings_updated' => 0,
        'season_stats_upserted' => 0,
        'h2h_upserted' => 0,
        'odds_upserted' => 0,
        'tension_files_processed' => 0,
        'tension_stats_upserted' => 0,
    ];

    /**
     * Constructeur
     */
    public function __construct(TeamLogoService $teamLogoService)
    {
        parent::__construct();
        $this->teamLogoService = $teamLogoService;
        $this->cacheDirectory = storage_path('app/sofascore_cache/tennis_players');
    }

    /**
     * Exécuter la commande
     */
    public function handle()
    {
        $this->info('🚀 Démarrage de l\'importation des joueurs depuis le cache...');

        $force         = $this->option('force');
        $limit         = $this->option('limit') ? (int) $this->option('limit') : null;
        $downloadImages = $this->option('download-images'); // images joueurs
        $downloadLogos  = $this->option('download-logos');  // logos ligues/tournois
        $skipArchive   = $this->option('skip-archive');

        $this->line("📋 Options:");
        $this->line("   - Forcer la mise à jour: " . ($force ? 'Oui' : 'Non'));
        $this->line("   - Limite: " . ($limit ? $limit . ' joueurs' : 'Aucune'));
        $this->line("   - Télécharger les images joueurs: " . ($downloadImages ? 'Oui' : 'Non'));
        $this->line("   - Télécharger les logos ligues: " . ($downloadLogos ? 'Oui' : 'Non'));

        // Récupérer ou créer la ligue Tennis
        $this->tennisLeague = $this->getTennisLeague();
        if (!$this->tennisLeague) {
            $this->error("❌ Impossible de récupérer ou créer la ligue Tennis");
            return 1;
        }
        $this->line("🎾 Ligue Tennis: {$this->tennisLeague->name} (ID: {$this->tennisLeague->id})");

        // Logo de la ligue Tennis globale (cache uniquement — pas d'appel API)
        if ($downloadLogos) {
            $this->downloadTennisLeagueLogo($this->tennisLeague, $force);
        }

        // Vérifier que le répertoire de cache existe
        if (!is_dir($this->cacheDirectory)) {
            $this->error("❌ Répertoire de cache introuvable: {$this->cacheDirectory}");
            return 1;
        }

        // Créer/mettre à jour les ligues de tournois EN PREMIER
        $this->createTournamentLeagues($force, $downloadLogos);

        // Importer les matchs depuis les fichiers d'événements en cache (Phase 2)
        $this->processEventCacheFiles($force);

        // Traiter les fichiers de cache des joueurs
        $this->processBasicPlayerCacheFiles($force, $limit, $downloadImages, $skipArchive);

        // Classements ATP/WTA + UTR + livetennis (indépendant du cache "déjà présent")
        $this->processPlayerRankings();

        // Statistiques de saison par surface (year-statistics)
        $this->processSeasonStats();

        // H2H détaillé + cotes bookmaker pour les matchs du jour
        $this->processH2hMatches();
        $this->processMatchOdds();

        // Fréquence empirique 15A/30A/40A depuis l'historique point-by-point
        $this->processPointByPointTension($skipArchive);

        // Afficher les statistiques finales
        $this->displayFinalStats();

        return 0;
    }

    /**
     * Importer les matchs depuis les fichiers d'événements en cache (cache → BDD).
     * Aucun appel API ici : lecture seule du cache tournaments/events/.
     */
    private function processEventCacheFiles(bool $force): void
    {
        $eventsDir = $this->cacheDirectory . '/tournaments/events';

        if (!is_dir($eventsDir)) {
            $this->line("📌 Répertoire des événements introuvable: {$eventsDir} — aucun match à importer");
            return;
        }

        $eventFiles = glob($eventsDir . '/event_*.json');

        if (empty($eventFiles)) {
            $this->line("📌 Aucun fichier d'événement dans le cache");
            return;
        }

        $this->line("\n⚽ Importation des matchs depuis le cache: " . count($eventFiles) . " fichier(s)");

        foreach ($eventFiles as $eventFile) {
            try {
                $event = json_decode(file_get_contents($eventFile), true);
                if (!$event || !isset($event['id'])) {
                    $this->warn("⚠️ Fichier d'événement invalide: " . basename($eventFile));
                    continue;
                }
                $this->processMatchFromCache($event, $force);
            } catch (\Exception $e) {
                $this->stats['matches_errors']++;
                Log::error('Erreur lecture fichier événement tennis', [
                    'file'  => $eventFile,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->line("✅ Matchs traités: {$this->stats['matches_processed']} | créés: {$this->stats['matches_created']} | màj: {$this->stats['matches_updated']} | ignorés: {$this->stats['matches_skipped']}");
    }

    /**
     * Persister un match en base depuis les données du cache (Phase 2).
     * Persistés : matchs à venir ET matchs en cours (live). Seuls les matchs
     * terminés (status type = finished) sont ignorés, sauf si --force est actif.
     */
    private function processMatchFromCache(array $event, bool $force): void
    {
        $this->stats['matches_processed']++;

        $eventId    = $event['id'] ?? null;
        $startTs    = $event['startTimestamp'] ?? null;
        $homeTeam   = $event['homeTeam'] ?? null;
        $awayTeam   = $event['awayTeam'] ?? null;
        $statusType = $event['status']['type'] ?? null; // 'notstarted' | 'inprogress' | 'finished'

        if (empty($startTs)) {
            $this->line("   [match] Pas de startTimestamp pour event_id={$eventId} — ignoré");
            Log::warning('match_persist_skip_no_timestamp', ['event_id' => $eventId]);
            $this->stats['matches_skipped']++;
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
                $this->stats['matches_skipped']++;
                return;
            }

            $team1Id          = $homeTeam['id'] ?? null;
            $team2Id          = $awayTeam['id'] ?? null;
            $tournamentSofaId = $event['tournament']['uniqueTournament']['id'] ?? $event['tournament']['id'] ?? null;
            $tournamentName   = $event['tournament']['uniqueTournament']['name'] ?? $event['tournament']['name'] ?? null;
            $slug             = $event['slug'] ?? '';
            $customId         = $event['customId'] ?? '';
            $sofascoreLink    = 'https://www.sofascore.com/fr/tennis/match/' . $slug . '/' . $customId . '#id:' . $eventId;

            // Récupérer l'ID interne du sport Tennis (sofascore_id = 5)
            $tennisSport = Sport::where('sofascore_id', 5)->orWhere('slug', 'tennis')->first();
            $sportId     = $tennisSport?->id ?? 5;

            // Le tournoi du match peut ne jamais avoir été créé par
            // createTournamentLeagues() (basée sur des marqueurs LEAGUE_DONE qui
            // ne couvrent pas forcément tous les tournois apparaissant en live/
            // featured) — sans ça le match s'affiche sans compétition/joueurs.
            if ($team1Id) {
                $this->ensureTeamExists((int) $team1Id, $homeTeam['name'] ?? null, $sportId, $tournamentSofaId, $tournamentName);
            }
            if ($team2Id) {
                $this->ensureTeamExists((int) $team2Id, $awayTeam['name'] ?? null, $sportId, $tournamentSofaId, $tournamentName);
            }

            $record = MatchModel::updateOrCreate(
                ['event_id' => $eventId],
                [
                    'team_1_sofascore_id'      => $team1Id,
                    'team_2_sofascore_id'      => $team2Id,
                    'match_start_date'         => $eventDt->format('Y-m-d'),
                    'match_start_time'         => $eventDt->format('H:i:s'),
                    'tournament_sofascore_id'  => $tournamentSofaId,
                    'sport_id'                 => $sportId,
                    'sofascore_link'           => $sofascoreLink,
                ]
            );

            // wasChanged() reflète les attributs réellement modifiés par le
            // save() qui vient d'avoir lieu — sans cette vérification, un
            // match déjà à jour (aucune valeur différente) s'affichait quand
            // même comme "mis à jour" alors qu'aucune écriture n'a eu lieu
            // (Eloquent court-circuite l'UPDATE quand rien n'est dirty).
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
                'sport_id'   => 5,
                'link'       => $sofascoreLink,
            ]);

            if ($record->wasRecentlyCreated) {
                $this->stats['matches_created']++;
                ActivityLogger::matchCreated("event #{$eventId}", $record->id, 'tennis', 'tennis:import-from-cache', ['event_id' => $eventId, 'link' => $sofascoreLink]);
            } elseif ($record->wasChanged()) {
                $this->stats['matches_updated']++;
            } else {
                $this->stats['matches_unchanged']++;
            }
        } catch (\Throwable $e) {
            $this->stats['matches_errors']++;
            $this->error("   [match] ERREUR persistance event_id={$eventId} : " . $e->getMessage());
            Log::error('match_persist_error', [
                'event_id' => $eventId,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Traiter les fichiers de cache des données de base des joueurs
     */
    private function processBasicPlayerCacheFiles($force, $limit, $downloadImages, bool $skipArchive = false)
    {
        $playersDir = $this->cacheDirectory . '/players';

        if (!is_dir($playersDir)) {
            $this->warn("⚠️ Répertoire des joueurs introuvable: {$playersDir}");
            return;
        }

        // Rechercher tous les fichiers player_basic_*.json
        $basicFiles = glob($playersDir . '/player_basic_*.json');
        $this->stats['cache_files_found'] = count($basicFiles);

        $this->info("📁 {$this->stats['cache_files_found']} fichiers de cache trouvés");

        foreach ($basicFiles as $cacheFile) {
            if ($limit && $this->stats['players_processed'] >= $limit) {
                $this->line("🔢 Limite de {$limit} joueurs atteinte");
                break;
            }

            $this->processBasicPlayerCacheFile($cacheFile, $force, $downloadImages, $skipArchive);
        }
    }

    /**
     * Traiter un fichier de cache de données de base d'un joueur
     */
    private function processBasicPlayerCacheFile($cacheFile, $force, $downloadImages, bool $skipArchive = false)
    {
        try {
            $this->stats['cache_files_processed']++;

            // Lire les données de base depuis le cache
            $basicData = json_decode(file_get_contents($cacheFile), true);

            if (!$basicData || !isset($basicData['sofascore_id'])) {
                $this->warn("⚠️ Fichier de cache invalide: " . basename($cacheFile));
                $this->stats['errors']++;
                return;
            }

            $sofascoreId = $basicData['sofascore_id'];
            $name = $basicData['name'];

            $this->stats['players_processed']++;

            // Vérifier si le joueur existe déjà
            $existingPlayer = Team::where('sofascore_id', $sofascoreId)->first();

            if ($existingPlayer && !$force) {
                // Synchroniser la table pivot league_team (comme le football)
                $existingPlayer->leagues()->syncWithoutDetaching([$this->tennisLeague->id]);

                // Télécharger le logo si manquant (comme le football)
                if ($downloadImages && ($force || empty($existingPlayer->img))) {
                    $this->downloadPlayerImage($sofascoreId, $name, $force);
                }

                $this->line("⏭️ Joueur existant synchronisé: {$name} (ID: {$sofascoreId})");
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

            // Préparer les données pour le modèle Team (exclure les champs non mappés)
            // league_id dans le cache = ID Sofascore de la ligue ATP/WTA, pas l'ID BDD → on l'exclut
            // La relation League ↔ Team passe par le pivot league_team (syncWithoutDetaching ci-dessous)
            $teamData = array_diff_key($basicData, array_flip(['league_id']));

            // Créer ou mettre à jour le joueur
            if ($existingPlayer) {
                // Préserver le nickname existant
                $updateData = array_diff_key($teamData, array_flip(['nickname']));
                // isDirty() doit être vérifié AVANT save() : Eloquent court-
                // circuite l'UPDATE (et le timestamp) si rien n'a réellement
                // changé — le touch() précédent masquait ça en forçant un
                // écriture même sans changement, d'où le message "mis à jour"
                // trompeur signalé par l'utilisateur.
                $existingPlayer->fill($updateData);
                $playerChanged = $existingPlayer->isDirty();
                $existingPlayer->save();
                if ($playerChanged) {
                    $this->stats['players_updated']++;
                    $this->line("🔄 Joueur mis à jour: {$name} (ID: {$sofascoreId}) - nickname préservé");
                    ActivityLogger::teamUpdated($name, $existingPlayer->id, $existingPlayer->img ?? null, 'tennis', array_keys($updateData), 'tennis:import-from-cache');
                } else {
                    $this->stats['players_unchanged']++;
                    $this->line("⏸️ Joueur déjà à jour: {$name} (ID: {$sofascoreId})");
                }
                $player = $existingPlayer;
            } else {
                $player = Team::create($teamData);
                $this->stats['players_created']++;
                $this->line("✅ Joueur créé: {$name} (ID: {$sofascoreId})");
                ActivityLogger::teamCreated($name, $player->id, $player->img ?? null, 'tennis', 'tennis:import-from-cache');
            }

            // Synchroniser la table pivot league_team
            $player->leagues()->syncWithoutDetaching([$this->tennisLeague->id]);
            $this->line("   🔗 Pivot league_team synchronisé (league: {$this->tennisLeague->id}, player: {$player->id})");

            // Mettre à jour avec les détails complets si disponibles
            $this->updatePlayerWithDetailsFromCache($player);

            // Télécharger l'image si demandé
            if ($downloadImages) {
                $this->downloadPlayerImage($sofascoreId, $name, $force);
            }

            // Archiver (déplacer) le fichier de cache traité vers le dossier 'processed'
            // En mode local (avant rsync), --skip-archive préserve les fichiers pour que rsync
            // puisse les envoyer au serveur. L'archivage est alors effectué côté serveur (Phase 5).
            if (!$skipArchive) {
                try {
                    $processedDir = $this->cacheDirectory . '/players/processed';
                    if (!is_dir($processedDir)) {
                        mkdir($processedDir, 0755, true);
                    }

                    $destPath = $processedDir . '/' . basename($cacheFile);

                    if (file_exists($cacheFile)) {
                        if (rename($cacheFile, $destPath)) {
                            $this->stats['cache_files_cleaned']++;
                            $this->line("📦 Fichier archivé: " . basename($cacheFile));
                            Log::info('cache_file_archived', ['file' => basename($cacheFile), 'dest' => $destPath]);
                        } else {
                            Log::warning('Échec déplacement du fichier de cache traité', ['file' => $cacheFile, 'dest' => $destPath]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur lors de l\'archivage du fichier de cache', ['file' => $cacheFile, 'error' => $e->getMessage()]);
                }
            } else {
                $this->line("📂 Fichier conservé (--skip-archive actif): " . basename($cacheFile));
            }
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('❌ Erreur lors du traitement du fichier de cache', [
                'cache_file' => $cacheFile,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mettre à jour un joueur avec ses détails depuis le cache
     */
    private function updatePlayerWithDetailsFromCache($player)
    {
        $sofascoreId = $player->sofascore_id;
        $detailsFile = $this->cacheDirectory . '/players/player_details_' . $sofascoreId . '.json';

        if (!file_exists($detailsFile)) {
            return;
        }

        try {
            $playerDetails = json_decode(file_get_contents($detailsFile), true);

            if ($playerDetails && isset($playerDetails['team']['playerTeamInfo'])) {
                $this->updatePlayerWithDetails($player, $playerDetails['team']['playerTeamInfo']);
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la lecture des détails du joueur depuis le cache', [
                'player_id' => $player->id,
                'sofascore_id' => $sofascoreId,
                'details_file' => $detailsFile,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mettre à jour un joueur avec ses détails complets depuis playerTeamInfo.
     *
     * Structure réelle de l'API /api/v1/team/{id} → team.playerTeamInfo :
     *   height, weight, plays, birthDateTimestamp, turnedPro,
     *   birthplace (string), residence (string),
     *   birthCity { name, country { name } }, residenceCity { name, country { name } },
     *   currentRanking
     */
    private function updatePlayerWithDetails($player, $playerDetails)
    {
        try {
            $updates = [];

            if (isset($playerDetails['birthDateTimestamp'])) {
                $updates['birth_date'] = date('Y-m-d', $playerDetails['birthDateTimestamp']);
            }

            if (isset($playerDetails['height'])) {
                $updates['height'] = $playerDetails['height'];
            }

            if (isset($playerDetails['weight'])) {
                $updates['weight'] = $playerDetails['weight'];
            }

            if (isset($playerDetails['plays'])) {
                $updates['plays'] = $playerDetails['plays'];
            }

            // birthplace est une string ("Fort Worth, Texas, USA"), pas un objet
            if (!empty($playerDetails['birthplace'])) {
                $updates['birth_place'] = $playerDetails['birthplace'];
            } elseif (isset($playerDetails['birthCity']['name'])) {
                $countryName = $playerDetails['birthCity']['country']['name'] ?? '';
                $updates['birth_place'] = $playerDetails['birthCity']['name'] . ($countryName ? ', ' . $countryName : '');
            }

            // residence est aussi une string ("Dallas, Texas, USA")
            if (!empty($playerDetails['residence'])) {
                $updates['residence'] = $playerDetails['residence'];
            } elseif (isset($playerDetails['residenceCity']['name'])) {
                $countryName = $playerDetails['residenceCity']['country']['name'] ?? '';
                $updates['residence'] = $playerDetails['residenceCity']['name'] . ($countryName ? ', ' . $countryName : '');
            }

            // Classement actuel (plus précis que le ranking de l'event)
            if (isset($playerDetails['currentRanking'])) {
                $updates['ranking'] = $playerDetails['currentRanking'];
            }

            if (!empty($updates)) {
                $player->update($updates);
                $player->touch();
                $this->line("   📝 Détails mis à jour: " . implode(', ', array_map(
                    fn($k, $v) => "{$k}: {$v}",
                    array_keys($updates),
                    array_values($updates)
                )));
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour des détails du joueur', [
                'player_id' => $player->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Copier l'image d'un joueur depuis le cache vers le dossier team_logos
     */
    private function downloadPlayerImage($sofascoreId, $playerName, bool $force = false)
    {
        try {
            // Trouver le joueur (team) existant pour obtenir son ID de base de données
            $team = Team::where('sofascore_id', $sofascoreId)->first();

            if (!$team) {
                $this->warn("⚠️ Joueur (team) non trouvé en base pour sofascore_id: {$sofascoreId}");
                return false;
            }

            // Chemin du logo dans le cache
            $cacheLogoPath = $this->cacheDirectory . '/players/logos/' . $sofascoreId . '.png';

            // Vérifier si le logo existe dans le cache
            if (!file_exists($cacheLogoPath)) {
                $this->line("⚠️ Logo non trouvé dans le cache pour: {$playerName} (ID: {$sofascoreId})");
                $this->stats['images_missing']++;
                Log::warning('player_logo_missing_in_cache', [
                    'sofascore_id' => $sofascoreId,
                    'player_name' => $playerName,
                    'cache_path' => $cacheLogoPath,
                ]);
                return false;
            }

            // Chemin de destination dans team_logos
            $destinationDir = storage_path('app/public/team_logos');
            $destinationPath = $destinationDir . '/' . $team->id . '.png';

            // Créer le répertoire de destination s'il n'existe pas
            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }

            // Vérifier si le logo existe déjà dans la destination
            if (file_exists($destinationPath) && !$force) {
                $this->line("⏭️ Logo déjà présent pour: {$playerName} (team_id: {$team->id})");
                $this->stats['images_skipped']++;
                Log::info('player_logo_skip_destination_exists', [
                    'sofascore_id' => $sofascoreId,
                    'player_name' => $playerName,
                    'team_id' => $team->id,
                    'destination' => $destinationPath,
                ]);
                // Mettre à jour le champ img si ce n'est pas déjà fait
                if (empty($team->img)) {
                    $team->img = "team_logos/{$team->id}.png";
                    $team->save();
                    $this->line("📝 Champ img mis à jour pour: {$playerName}");
                    Log::info('player_img_field_updated', [
                        'sofascore_id' => $sofascoreId,
                        'player_name' => $playerName,
                        'team_id' => $team->id,
                    ]);
                }
                return true;
            }

            // Copier le fichier depuis le cache vers la destination
            if (copy($cacheLogoPath, $destinationPath)) {
                $this->line("📸 Logo copié depuis le cache: {$playerName} -> team_logos/{$team->id}.png");

                // Mettre à jour le champ img dans la base de données
                $team->img = "team_logos/{$team->id}.png";
                $team->save();
                $this->line("📝 Champ img mis à jour pour: {$playerName}");
                Log::info('player_logo_copied', [
                    'sofascore_id' => $sofascoreId,
                    'player_name' => $playerName,
                    'team_id' => $team->id,
                    'source' => $cacheLogoPath,
                    'destination' => $destinationPath,
                ]);
                $this->stats['images_downloaded']++;

                return true;
            } else {
                $this->warn("⚠️ Échec de la copie du logo pour: {$playerName}");
                $this->stats['images_failed']++;
                Log::warning('player_logo_copy_failed', [
                    'sofascore_id' => $sofascoreId,
                    'player_name' => $playerName,
                    'team_id' => $team->id,
                    'source' => $cacheLogoPath,
                    'destination' => $destinationPath,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de la copie du logo pour {$playerName}: " . $e->getMessage());
            $this->stats['images_failed']++;
            return false;
        }
    }

    /**
     * Créer/mettre à jour les ligues de tournois tennis depuis les marqueurs LEAGUE_DONE
     */
    private function createTournamentLeagues(bool $force, bool $downloadImages): void
    {
        $this->line("\n🏆 Traitement des ligues de tournois tennis...");

        // Récupérer le sport Tennis
        $tennisSport = Sport::where('name', 'Tennis')->orWhere('slug', 'tennis')->first();
        if (!$tennisSport) {
            $this->warn("⚠️ Sport Tennis introuvable, impossible de créer les ligues de tournois");
            return;
        }

        // Scanner les marqueurs LEAGUE_DONE
        $cacheRoot = storage_path('app/sofascore_cache');
        $markers = glob($cacheRoot . '/tennis_LEAGUE_DONE_*');

        if (empty($markers)) {
            $this->line("📌 Aucun marqueur de tournoi trouvé");
            return;
        }

        $this->line("📌 " . count($markers) . " marqueurs de tournois trouvés");

        foreach ($markers as $markerFile) {
            try {
                $markerData = json_decode(file_get_contents($markerFile), true);
                if (!$markerData || !isset($markerData['sofascore_id'], $markerData['name'])) {
                    continue;
                }

                $tournamentId   = $markerData['sofascore_id'];
                $tournamentName = $markerData['name'];
                // Utiliser le slug officiel Sofascore si disponible (marqueur enrichi depuis 2026-06)
                $tournamentSlug = $markerData['slug'] ?? Str::slug($tournamentName);
                $categoryName   = $markerData['category_name'] ?? null;
                $tennisPoints   = $markerData['tennis_points'] ?? null;

                // Vérifier si la ligue existe déjà
                $existingLeague = League::where('sofascore_id', $tournamentId)
                    ->where('sport_id', $tennisSport->id)
                    ->first();

                if ($existingLeague && !$force) {
                    $this->line("   ⏭️ Ligue déjà existante: {$tournamentName} (sofascore_id: {$tournamentId})");
                    $this->stats['tournament_leagues_skipped']++;

                    if ($downloadImages && empty($existingLeague->img)) {
                        $this->downloadLeagueLogo($existingLeague, $force);
                    }

                    // Backfill de la catégorie (ATP/WTA/Challenger/ITF/UTR...) pour les
                    // ligues déjà existantes qui n'en ont pas encore — sans ce backfill,
                    // toute ligue créée avant ce correctif resterait sans catégorie pour
                    // toujours, ce chemin "déjà existante" ne repassant jamais par le
                    // bloc de création/mise à jour ci-dessous en mode normal (sans --force).
                    if (empty($existingLeague->country_id) && $categoryName) {
                        $category = Country::firstOrCreate(
                            ['name' => $categoryName],
                            ['slug' => Str::slug($categoryName)]
                        );
                        $existingLeague->update(['country_id' => $category->id]);
                        $this->line("   🏷️ Catégorie backfillée: {$tournamentName} [{$categoryName}]");
                    }
                    // Idem pour tennis_points (distingue un Masters/Grand Chelem d'un
                    // tournoi ATP/WTA régulier) — utilisé par MatchController::today()
                    // pour trier "Matchs du jour" avec les Masters en tête.
                    if (is_null($existingLeague->tennis_points) && !is_null($tennisPoints)) {
                        $existingLeague->update(['tennis_points' => $tennisPoints]);
                    }
                    continue;
                }

                $leagueData = [
                    'name'     => $tournamentName,
                    'slug'     => $tournamentSlug,
                    'sport_id' => $tennisSport->id,
                ];

                // La table `countries` sert aussi, pour le tennis, à stocker le
                // circuit du tournoi (ATP/WTA/Challenger/ITF Men/ITF Women/UTR...)
                // via `leagues.country_id` — c'est ce que le frontend affiche comme
                // "pays" dans la vue Matchs du jour (MatchController::today() fait
                // un leftJoin sur countries). Cette info était bien extraite du cache
                // Sofascore (category_name) mais jamais reportée dans $leagueData,
                // donc jamais persistée pour les ligues créées par ce pipeline.
                if ($categoryName) {
                    $category = Country::firstOrCreate(
                        ['name' => $categoryName],
                        ['slug' => Str::slug($categoryName)]
                    );
                    $leagueData['country_id'] = $category->id;
                }

                if (!is_null($tennisPoints)) {
                    $leagueData['tennis_points'] = $tennisPoints;
                }

                // Créer ou mettre à jour la ligue
                if ($existingLeague) {
                    // Eloquent court-circuite l'UPDATE (et le timestamp) si
                    // aucun attribut n'est réellement différent — isDirty()
                    // doit donc être vérifié AVANT save() pour ne pas afficher
                    // "mise à jour" quand rien n'a changé en base.
                    $existingLeague->fill($leagueData);
                    $leagueChanged = $existingLeague->isDirty();
                    $existingLeague->save();
                    if ($leagueChanged) {
                        $this->line("   🔄 Ligue mise à jour: {$tournamentName}" . ($categoryName ? " [{$categoryName}]" : ''));
                        $this->stats['tournament_leagues_updated']++;
                    } else {
                        $this->line("   ⏸️ Ligue déjà à jour: {$tournamentName}" . ($categoryName ? " [{$categoryName}]" : ''));
                        $this->stats['tournament_leagues_unchanged']++;
                    }
                    $league = $existingLeague;
                } else {
                    $leagueData['sofascore_id'] = $tournamentId;
                    $league = League::create($leagueData);
                    $this->line("   ✅ Ligue créée: {$tournamentName}" . ($categoryName ? " [{$categoryName}]" : '') . ($tennisPoints ? " ({$tennisPoints}pts)" : ''));
                    $this->stats['tournament_leagues_created']++;
                }

                if ($downloadImages) {
                    $this->downloadLeagueLogo($league, $force);
                }
            } catch (\Exception $e) {
                $this->warn("⚠️ Erreur lors du traitement du marqueur {$markerFile}: {$e->getMessage()}");
                Log::warning('Erreur traitement marqueur tournoi tennis', [
                    'marker' => $markerFile,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        $this->line("✅ Ligues de tournois traitées: {$this->stats['tournament_leagues_created']} créées, {$this->stats['tournament_leagues_updated']} mises à jour, {$this->stats['tournament_leagues_unchanged']} déjà à jour, {$this->stats['tournament_leagues_skipped']} ignorées");
    }

    /**
     * Télécharger/copier le logo d'une ligue via le LeagueLogoService partagé
     * (même mécanisme que football/basketball/etc.) : cherche d'abord dans le
     * cache local (tennis_leagues/league_logos/{id}-light|dark.png), et à
     * défaut tente un téléchargement HTTP direct avec plusieurs stratégies
     * d'en-têtes. L'ancienne implémentation ne lisait QUE le cache
     * (tennis_leagues/logos/{id}.png, jamais peuplé par aucun script) et
     * n'avait donc quasiment aucun logo (33/2744 ligues) alors que football,
     * qui utilise déjà ce service, en a 69%.
     */
    private function downloadLeagueLogo(League $league, bool $force): void
    {
        try {
            $logoService = app(\App\Services\LeagueLogoService::class);
            $logoService->ensureLeagueLogos($league, $force);
        } catch (\Exception $e) {
            $this->warn("      ⚠️ Erreur téléchargement logo ligue: {$e->getMessage()}");
            Log::warning('Erreur téléchargement logo ligue tournoi tennis', [
                'league_id' => $league->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Copier le logo d'un tournoi depuis le cache vers league_logos/
     */
    private function copyTournamentLogoFromCache(League $league, string $cacheLogoPath): void
    {
        try {
            $destinationDir = storage_path('app/public/league_logos');
            $lightLogoPath = $destinationDir . '/' . $league->id . '.png';
            $darkLogoPath = $destinationDir . '/' . $league->id . '-dark.png';

            // Créer le répertoire de destination si nécessaire
            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }

            // Vérifier si le logo existe déjà
            if (!file_exists($lightLogoPath) || filesize($lightLogoPath) === 0) {
                // Copier le logo depuis le cache
                if (copy($cacheLogoPath, $lightLogoPath)) {
                    $this->line("      📸 Logo copié depuis cache: {$league->name} -> league_logos/{$league->id}.png");

                    // Copier aussi comme version dark (même logo pour les tournois)
                    copy($cacheLogoPath, $darkLogoPath);

                    // Mettre à jour le champ img
                    $league->img = "league_logos/{$league->id}.png";
                    $league->save();

                    $this->stats['tournament_logos_downloaded']++;
                    Log::info('Logo tournoi copié depuis cache', [
                        'league_id' => $league->id,
                        'league_name' => $league->name,
                        'sofascore_id' => $league->sofascore_id,
                        'source' => $cacheLogoPath,
                        'destination' => $lightLogoPath,
                    ]);
                } else {
                    $this->warn("      ⚠️ Échec copie logo depuis cache: {$league->name}");
                }
            } else {
                $this->line("      ⏭️ Logo déjà présent: {$league->name}");

                // Mettre à jour le champ img si nécessaire
                if (empty($league->img)) {
                    $league->img = "league_logos/{$league->id}.png";
                    $league->save();
                }
            }
        } catch (\Exception $e) {
            $this->warn("      ⚠️ Erreur copie logo depuis cache: {$e->getMessage()}");
            Log::warning('Erreur copie logo tournoi depuis cache', [
                'league_id' => $league->id,
                'cache_path' => $cacheLogoPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Copier le logo de la ligue Tennis globale (ATP/WTA) depuis le cache.
     * La ligue globale a sofascore_id = 0 (pas de tournoi Sofascore direct),
     * donc on cherche un logo manuel placé dans le cache.
     */
    private function downloadTennisLeagueLogo(League $league, bool $force): void
    {
        $cacheLogoPath = storage_path('app/sofascore_cache/tennis_leagues/logos/global.png');

        if (!file_exists($cacheLogoPath) || filesize($cacheLogoPath) === 0) {
            $this->line("⚠️ Logo global Tennis absent du cache ({$cacheLogoPath}) — ignoré");
            return;
        }

        $this->copyTournamentLogoFromCache($league, $cacheLogoPath);
    }

    /**
     * Récupérer ou créer la ligue Tennis (ATP/WTA)
     */
    private function getTennisLeague()
    {
        try {
            // Rechercher le sport Tennis
            $tennisSport = Sport::where('name', 'Tennis')
                ->orWhere('slug', 'tennis')
                ->first();

            if (!$tennisSport) {
                $this->warn("⚠️ Sport Tennis non trouvé en base, tentative de création...");
                $tennisSport = Sport::create([
                    'name' => 'Tennis',
                    'slug' => 'tennis',
                    'sofascore_id' => 5, // ID Sofascore pour le tennis
                ]);
                $this->line("✅ Sport Tennis créé (ID: {$tennisSport->id})");
            }

            // Rechercher ou créer la ligue Tennis globale
            $league = League::where('sport_id', $tennisSport->id)
                ->where(function ($q) {
                    $q->where('name', 'ATP/WTA Tennis')
                        ->orWhere('name', 'Tennis')
                        ->orWhere('slug', 'tennis-global');
                })
                ->first();

            if (!$league) {
                $this->warn("⚠️ Ligue Tennis non trouvée, création...");
                $league = League::create([
                    'name' => 'ATP/WTA Tennis',
                    'slug' => 'tennis-global',
                    'sport_id' => $tennisSport->id,
                    'sofascore_id' => 0, // Pas d'équivalent direct Sofascore
                ]);
                $this->line("✅ Ligue Tennis créée (ID: {$league->id})");
            }

            return $league;
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de la récupération/création de la ligue Tennis: " . $e->getMessage());
            Log::error('Erreur getTennisLeague', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Classements ATP/WTA + UTR + livetennis depuis players/rankings/rankings_*.json.
     * Traité indépendamment de processBasicPlayerCacheFile pour rafraîchir aussi
     * les joueurs déjà en base (qui court-circuitent le reste sans --force).
     */
    private function processPlayerRankings(): void
    {
        $rankingsDir = $this->cacheDirectory . '/players/rankings';
        if (!is_dir($rankingsDir)) {
            return;
        }

        $files = glob($rankingsDir . '/rankings_*.json');
        $this->info("🏅 Traitement de " . count($files) . " fichier(s) de classement...");

        foreach ($files as $file) {
            try {
                $data = json_decode(file_get_contents($file), true);
                $rankings = $data['rankings'] ?? [];
                if (!$rankings) {
                    continue;
                }

                $sofascoreId = $rankings[0]['team']['id'] ?? null;
                if (!$sofascoreId) {
                    continue;
                }

                $player = Team::where('sofascore_id', $sofascoreId)->first();
                if (!$player) {
                    continue;
                }

                $update = [];
                foreach ($rankings as $r) {
                    $class = $r['rankingClass'] ?? null;
                    $value = $r['ranking'] ?? null;
                    if ($value === null) {
                        continue;
                    }
                    if ($class === 'team') {
                        $update['ranking'] = $value;
                    } elseif ($class === 'utr') {
                        $update['utr_rating'] = $value;
                    } elseif ($class === 'livetennis') {
                        $update['livetennis_ranking'] = $value;
                    }
                }

                if ($update) {
                    $update['ranking_updated_at'] = now();
                    $player->update($update);
                    $this->stats['rankings_updated']++;
                }
            } catch (\Exception $e) {
                Log::warning('Erreur parsing rankings', ['file' => $file, 'error' => $e->getMessage()]);
            }
        }

        $this->line("✅ Classements mis à jour: {$this->stats['rankings_updated']}");
    }

    /**
     * Statistiques de saison par surface depuis players/statistics/player_statistics_*.json
     * (year-statistics Sofascore) — persistées vers tennis_player_season_stats.
     */
    private function processSeasonStats(): void
    {
        $statsDir = $this->cacheDirectory . '/players/statistics';
        if (!is_dir($statsDir)) {
            return;
        }

        $files = glob($statsDir . '/player_statistics_*.json');
        $this->info("📈 Traitement de " . count($files) . " fichier(s) de stats saison...");

        foreach ($files as $file) {
            try {
                if (!preg_match('/player_statistics_(?:prevyear_)?(\d+)\.json$/', basename($file), $m)) {
                    continue;
                }
                $sofascoreId = (int) $m[1];

                $data = json_decode(file_get_contents($file), true);
                if (!empty($data['_negative_cache']) || empty($data['statistics'])) {
                    continue;
                }

                $player = Team::where('sofascore_id', $sofascoreId)->first();
                if (!$player) {
                    continue;
                }

                $year = $data['_fetched_year'] ?? (int) date('Y');

                foreach ($data['statistics'] as $s) {
                    if (empty($s['groundType'])) {
                        continue;
                    }

                    TennisPlayerSeasonStat::updateOrCreate(
                        [
                            'team_id' => $player->id,
                            'ground_type' => $s['groundType'],
                            'season_year' => $year,
                        ],
                        [
                            'aces' => $s['aces'] ?? null,
                            'double_faults' => $s['doubleFaults'] ?? null,
                            'first_serve_points_scored' => $s['firstServePointsScored'] ?? null,
                            'first_serve_points_total' => $s['firstServePointsTotal'] ?? null,
                            'second_serve_points_scored' => $s['secondServePointsScored'] ?? null,
                            'second_serve_points_total' => $s['secondServePointsTotal'] ?? null,
                            'break_points_scored' => $s['breakPointsScored'] ?? null,
                            'break_points_total' => $s['breakPointsTotal'] ?? null,
                            'opponent_break_points_scored' => $s['opponentBreakPointsScored'] ?? null,
                            'opponent_break_points_total' => $s['opponentBreakPointsTotal'] ?? null,
                            'tiebreaks_won' => $s['tiebreaksWon'] ?? null,
                            'tiebreaks_losses' => $s['tiebreakLosses'] ?? null,
                            'wins' => $s['wins'] ?? null,
                            'matches_count' => $s['matches'] ?? null,
                            'tournaments_played' => $s['tournamentsPlayed'] ?? null,
                            'tournaments_won' => $s['tournamentsWon'] ?? null,
                            'winners_total' => $s['winnersTotal'] ?? null,
                            'unforced_errors_total' => $s['unforcedErrorsTotal'] ?? null,
                            'fetched_at' => now(),
                        ]
                    );
                    $this->stats['season_stats_upserted']++;
                }
            } catch (\Exception $e) {
                Log::warning('Erreur parsing season stats', ['file' => $file, 'error' => $e->getMessage()]);
            }
        }

        $this->line("✅ Lignes de stats saison upsertées: {$this->stats['season_stats_upserted']}");
    }

    /**
     * H2H détaillé (confrontations passées) depuis tournaments/h2h/h2h_*.json.
     */
    private function processH2hMatches(): void
    {
        $h2hDir = $this->cacheDirectory . '/tournaments/h2h';
        if (!is_dir($h2hDir)) {
            return;
        }

        $files = glob($h2hDir . '/h2h_*.json');
        $this->info("🤝 Traitement de " . count($files) . " fichier(s) H2H...");

        foreach ($files as $file) {
            try {
                if (!preg_match('/h2h_(\d+)\.json$/', basename($file), $m)) {
                    continue;
                }
                $currentEventId = (int) $m[1];

                $data = json_decode(file_get_contents($file), true);
                $events = $data['events'] ?? [];

                foreach ($events as $e) {
                    $h2hEventId = $e['id'] ?? null;
                    // Exclure le match du jour lui-même (present dans la liste)
                    if (!$h2hEventId || $h2hEventId == $currentEventId) {
                        continue;
                    }
                    if (($e['status']['type'] ?? null) !== 'finished') {
                        continue;
                    }

                    TennisH2hMatch::updateOrCreate(
                        [
                            'current_event_id' => $currentEventId,
                            'h2h_event_id' => $h2hEventId,
                        ],
                        [
                            'tournament_name' => $e['tournament']['name'] ?? null,
                            'ground_type' => $e['tournament']['uniqueTournament']['groundType'] ?? null,
                            'played_at' => !empty($e['startTimestamp']) ? date('Y-m-d', $e['startTimestamp']) : null,
                            'home_team_sofascore_id' => $e['homeTeam']['id'] ?? null,
                            'away_team_sofascore_id' => $e['awayTeam']['id'] ?? null,
                            'home_score' => $e['homeScore'] ?? null,
                            'away_score' => $e['awayScore'] ?? null,
                            'winner_code' => $e['winnerCode'] ?? null,
                            'fetched_at' => now(),
                        ]
                    );
                    $this->stats['h2h_upserted']++;
                }
            } catch (\Exception $e) {
                Log::warning('Erreur parsing H2H', ['file' => $file, 'error' => $e->getMessage()]);
            }
        }

        $this->line("✅ Confrontations H2H upsertées: {$this->stats['h2h_upserted']}");
    }

    /**
     * Cotes bookmaker depuis tournaments/odds/odds_*.json.
     */
    private function processMatchOdds(): void
    {
        $oddsDir = $this->cacheDirectory . '/tournaments/odds';
        if (!is_dir($oddsDir)) {
            return;
        }

        $files = glob($oddsDir . '/odds_*.json');
        $this->info("💰 Traitement de " . count($files) . " fichier(s) de cotes...");

        foreach ($files as $file) {
            try {
                if (!preg_match('/odds_(\d+)\.json$/', basename($file), $m)) {
                    continue;
                }
                $eventId = (int) $m[1];

                $data = json_decode(file_get_contents($file), true);
                $markets = $data['markets'] ?? [];

                foreach ($markets as $market) {
                    foreach (($market['choices'] ?? []) as $choice) {
                        TennisMatchOdds::updateOrCreate(
                            [
                                'event_id' => $eventId,
                                'market_id' => $market['marketId'] ?? null,
                                'choice_name' => $choice['name'] ?? null,
                            ],
                            [
                                'market_name' => $market['marketName'] ?? null,
                                'fractional_value' => $choice['fractionalValue'] ?? null,
                                'decimal_value' => $this->fractionalToDecimal($choice['fractionalValue'] ?? null),
                                'fetched_at' => now(),
                            ]
                        );
                        $this->stats['odds_upserted']++;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erreur parsing odds', ['file' => $file, 'error' => $e->getMessage()]);
            }
        }

        $this->line("✅ Lignes de cotes upsertées: {$this->stats['odds_upserted']}");
    }

    /**
     * Convertit une cote fractionnelle Sofascore ("13/10") en cote décimale (2.3).
     */
    private function fractionalToDecimal(?string $fractional): ?float
    {
        if (!$fractional || !str_contains($fractional, '/')) {
            return null;
        }
        [$num, $den] = array_map('floatval', explode('/', $fractional, 2));
        if (!$den) {
            return null;
        }
        return round(1 + ($num / $den), 3);
    }

    /**
     * Fréquence empirique d'atteindre 15-15/30-30/40-40 dans le 1er set, calculée
     * depuis players/point_by_point/pbp_*.json (matchs passés déjà terminés).
     * Alimente tennis_player_game_tension_stats, agrégé (incrémental) par joueur.
     */
    private function processPointByPointTension(bool $skipArchive = false): void
    {
        $pbpDir = $this->cacheDirectory . '/players/point_by_point';
        if (!is_dir($pbpDir)) {
            return;
        }

        $files = glob($pbpDir . '/pbp_*.json');
        $this->info("🎯 Traitement de " . count($files) . " fichier(s) point-by-point...");

        $processedDir = $pbpDir . '/processed';
        if (!is_dir($processedDir)) {
            mkdir($processedDir, 0755, true);
        }

        // Marqueur d'idempotence séparé de l'archivage physique : en mode
        // --skip-archive (local, avant sync vers prod), les fichiers restent en
        // place pour être envoyés à prod — sans ce marqueur, relancer la
        // commande localement recompterait indéfiniment les mêmes matchs.
        $countedMarkerFile = $pbpDir . '/.counted_event_ids.json';
        $countedEventIds = file_exists($countedMarkerFile)
            ? array_flip(json_decode(file_get_contents($countedMarkerFile), true) ?: [])
            : [];

        // team_id (BDD) => ['matches'=>n, 'games' => n, '15a'=>n, '30a'=>n, '40a'=>n]
        $agg = [];

        foreach ($files as $file) {
            try {
                if (preg_match('/pbp_(\d+)\.json$/', basename($file), $m) && isset($countedEventIds[$m[1]])) {
                    continue;
                }

                $data = json_decode(file_get_contents($file), true);
                $homeId = $data['_home_team_id'] ?? null;
                $awayId = $data['_away_team_id'] ?? null;
                $set1 = collect($data['pointByPoint'] ?? [])->firstWhere('set', 1);

                if (!$set1 || empty($set1['games']) || (!$homeId && !$awayId)) {
                    // Fichier inexploitable : on l'archive quand même pour ne
                    // pas retenter indéfiniment de le parser à chaque run.
                    if (!$skipArchive) {
                        rename($file, $processedDir . '/' . basename($file));
                    }
                    if (isset($m[1])) {
                        $countedEventIds[$m[1]] = true;
                    }
                    continue;
                }

                foreach ([$homeId, $awayId] as $sofascoreId) {
                    if (!$sofascoreId) {
                        continue;
                    }
                    $agg[$sofascoreId] ??= [
                        'matches' => 0, 'games' => 0, '15a' => 0, '30a' => 0, '40a' => 0,
                        '30love' => 0, 'g40_0' => 0, 'g40_15' => 0, 'g40_30' => 0,
                        'service_games' => 0, 'server_loss_to_love' => 0, 'led_15_0' => 0,
                        'lost_first_point_on_serve' => 0,
                    ];
                    $agg[$sofascoreId]['matches']++;
                }

                foreach ($set1['games'] as $game) {
                    $points = $game['points'] ?? [];
                    if (empty($points)) {
                        continue;
                    }
                    $reached15 = false;
                    $reached30 = false;
                    $reached40 = false;
                    $reached30Love = false;

                    foreach ($points as $p) {
                        $h = $p['homePoint'] ?? null;
                        $a = $p['awayPoint'] ?? null;
                        if ($h === '15' && $a === '15') {
                            $reached15 = true;
                        } elseif ($h === '30' && $a === '30') {
                            $reached30 = true;
                        } elseif ($h === '40' && $a === '40') {
                            $reached40 = true;
                        } elseif (($h === '30' && $a === '0') || ($h === '0' && $a === '30')) {
                            $reached30Love = true;
                        }
                    }

                    // Répartition du jeu terminé (40-0/40-15/40-30), déduite du
                    // dernier point loggé avant la victoire — pas besoin du
                    // champ "scoring" : le côté à "40" est forcément le
                    // vainqueur dans ces 3 cas (pas de prolongation deuce/AD).
                    // Qui mène 15-0 après le tout premier point (asymétrique,
                    // un seul des deux camps est incrémenté, pas les deux).
                    $first = $points[0];
                    $firstH = $first['homePoint'] ?? null;
                    $firstA = $first['awayPoint'] ?? null;
                    $leaderId = null;
                    if ($firstH === '15' && $firstA === '0') { $leaderId = $homeId; }
                    elseif ($firstA === '15' && $firstH === '0') { $leaderId = $awayId; }

                    $last = end($points);
                    $lastH = $last['homePoint'] ?? null;
                    $lastA = $last['awayPoint'] ?? null;
                    $bucket = null;
                    $loserIsHome = null;
                    if ($lastH === '40' && $lastA === '0') { $bucket = 'g40_0'; $loserIsHome = false; }
                    elseif ($lastA === '40' && $lastH === '0') { $bucket = 'g40_0'; $loserIsHome = true; }
                    elseif ($lastH === '40' && $lastA === '15') { $bucket = 'g40_15'; $loserIsHome = false; }
                    elseif ($lastA === '40' && $lastH === '15') { $bucket = 'g40_15'; $loserIsHome = true; }
                    elseif ($lastH === '40' && $lastA === '30') { $bucket = 'g40_30'; $loserIsHome = false; }
                    elseif ($lastA === '40' && $lastH === '30') { $bucket = 'g40_30'; $loserIsHome = true; }

                    $serving = $game['score']['serving'] ?? null; // 1=home, 2=away

                    foreach ([$homeId, $awayId] as $sofascoreId) {
                        if (!$sofascoreId) {
                            continue;
                        }
                        $agg[$sofascoreId]['games']++;
                        $agg[$sofascoreId]['15a'] += $reached15 ? 1 : 0;
                        $agg[$sofascoreId]['30a'] += $reached30 ? 1 : 0;
                        $agg[$sofascoreId]['40a'] += $reached40 ? 1 : 0;
                        $agg[$sofascoreId]['30love'] += $reached30Love ? 1 : 0;
                        if ($bucket) {
                            $agg[$sofascoreId][$bucket]++;
                        }
                    }
                    if ($leaderId) {
                        $agg[$leaderId]['led_15_0']++;
                    }

                    // Stats spécifiques au serveur (dénominateur = jeux servis
                    // par CE joueur uniquement, pas symétrique).
                    if ($serving === 1 || $serving === 2) {
                        $serverId = $serving === 1 ? $homeId : $awayId;
                        if ($serverId) {
                            $agg[$serverId]['service_games']++;
                            // Le serveur perd à zéro si le camp à "0" au dernier
                            // point est bien le sien (pas le camp adverse).
                            $serverIsHome = $serving === 1;
                            if ($bucket === 'g40_0' && $loserIsHome === $serverIsHome) {
                                $agg[$serverId]['server_loss_to_love']++;
                            }
                            // Perd le 1er point sur SON service (0-15 pour lui) —
                            // distinct de "leaderId" qui mélange service+retour.
                            if ($leaderId && $leaderId !== $serverId) {
                                $agg[$serverId]['lost_first_point_on_serve']++;
                            }
                        }
                    }
                }

                if (!$skipArchive) {
                    rename($file, $processedDir . '/' . basename($file));
                }
                if (isset($m[1])) {
                    $countedEventIds[$m[1]] = true;
                }
                $this->stats['tension_files_processed']++;
            } catch (\Exception $e) {
                Log::warning('Erreur parsing point-by-point', ['file' => $file, 'error' => $e->getMessage()]);
            }
        }

        if ($skipArchive) {
            file_put_contents($countedMarkerFile, json_encode(array_keys($countedEventIds)));
        }

        foreach ($agg as $sofascoreId => $counts) {
            $player = Team::where('sofascore_id', $sofascoreId)->first();
            if (!$player) {
                continue;
            }

            $existing = TennisPlayerGameTensionStat::firstOrNew(['team_id' => $player->id]);
            $existing->sample_matches = ($existing->sample_matches ?? 0) + $counts['matches'];
            $existing->sample_games_set1 = ($existing->sample_games_set1 ?? 0) + $counts['games'];
            $existing->count_reach_15a = ($existing->count_reach_15a ?? 0) + $counts['15a'];
            $existing->count_reach_30a = ($existing->count_reach_30a ?? 0) + $counts['30a'];
            $existing->count_reach_40a = ($existing->count_reach_40a ?? 0) + $counts['40a'];
            $existing->count_reach_30love = ($existing->count_reach_30love ?? 0) + $counts['30love'];
            $existing->count_game_40_0 = ($existing->count_game_40_0 ?? 0) + $counts['g40_0'];
            $existing->count_game_40_15 = ($existing->count_game_40_15 ?? 0) + $counts['g40_15'];
            $existing->count_game_40_30 = ($existing->count_game_40_30 ?? 0) + $counts['g40_30'];
            $existing->sample_service_games_set1 = ($existing->sample_service_games_set1 ?? 0) + $counts['service_games'];
            $existing->count_server_loss_to_love = ($existing->count_server_loss_to_love ?? 0) + $counts['server_loss_to_love'];
            $existing->count_led_15_0 = ($existing->count_led_15_0 ?? 0) + $counts['led_15_0'];
            $existing->count_lost_first_point_on_serve = ($existing->count_lost_first_point_on_serve ?? 0) + $counts['lost_first_point_on_serve'];
            $existing->updated_from_cache_at = now();
            $existing->save();
            $this->stats['tension_stats_upserted']++;
        }

        $this->line("✅ Fichiers point-by-point traités: {$this->stats['tension_files_processed']} | joueurs mis à jour: {$this->stats['tension_stats_upserted']}");
    }

    /**
     * Afficher les statistiques finales
     */
    // getCacheSyncTimestamp removed — archivage conditionnel via .synced_at supprimé.

    private function displayFinalStats()
    {
        $this->info('');
        $this->info('📊 === STATISTIQUES FINALES ===');
        $this->line("📁 Fichiers de cache trouvés: {$this->stats['cache_files_found']}");
        $this->line("📄 Fichiers de cache traités: {$this->stats['cache_files_processed']}");
        $this->line("👥 Joueurs traités: {$this->stats['players_processed']}");
        $this->line("🗃️ Fichiers archivés: {$this->stats['cache_files_cleaned']}");
        $this->line("✅ Joueurs créés: {$this->stats['players_created']}");
        $this->line("🔄 Joueurs mis à jour: {$this->stats['players_updated']}");
        $this->line("⏸️ Joueurs déjà à jour: {$this->stats['players_unchanged']}");
        $this->line("⏭️ Joueurs ignorés: {$this->stats['players_skipped']}");
        $this->line("🔄 Doublons détectés: {$this->stats['duplicates_detected']}");
        $this->line("📸 Images copiées: {$this->stats['images_downloaded']}");
        $this->line("⏭️ Images ignorées (déjà présentes): {$this->stats['images_skipped']}");
        $this->line("⚠️ Images manquantes dans le cache: {$this->stats['images_missing']}");
        $this->line("❌ Images échouées: {$this->stats['images_failed']}");
        $this->line("🏆 Ligues de tournois créées: {$this->stats['tournament_leagues_created']}");
        $this->line("🔄 Ligues de tournois mises à jour: {$this->stats['tournament_leagues_updated']}");
        $this->line("⏸️ Ligues de tournois déjà à jour: {$this->stats['tournament_leagues_unchanged']}");
        $this->line("⏭️ Ligues de tournois ignorées: {$this->stats['tournament_leagues_skipped']}");
        $this->line("📸 Logos de ligues téléchargés: {$this->stats['tournament_logos_downloaded']}");
        $this->line("❌ Erreurs: {$this->stats['errors']}");
        $this->info('');
        $this->info('⚽ === MATCHS ===');
        $this->line("⚽ Matchs traités: {$this->stats['matches_processed']}");
        $this->line("✅ Matchs créés: {$this->stats['matches_created']}");
        $this->line("🔄 Matchs mis à jour: {$this->stats['matches_updated']}");
        $this->line("⏸️ Matchs déjà à jour (aucun changement): {$this->stats['matches_unchanged']}");
        $this->line("⏭️ Matchs ignorés (passés): {$this->stats['matches_skipped']}");
        $this->line("❌ Erreurs matchs: {$this->stats['matches_errors']}");

        // Compter les fichiers de stats disponibles
        $statsDir = $this->cacheDirectory . '/players/statistics';
        $statsAvailable = 0;
        $statsMissing = 0;
        if (is_dir($statsDir)) {
            foreach (glob($statsDir . '/player_statistics_*.json') as $f) {
                $data = json_decode(file_get_contents($f), true);
                if ($data && empty($data['_negative_cache'])) {
                    $statsAvailable++;
                } else {
                    $statsMissing++;
                }
            }
        }
        $this->line("📈 Fichiers stats disponibles: {$statsAvailable}");
        $this->line("⚠️ Fichiers stats manquants: {$statsMissing}");

        $this->line("");
        $this->line("🎾 === DONNÉES SCORE MATCH SERRÉ ===");
        $this->line("🏅 Classements (ATP/WTA/UTR) mis à jour: {$this->stats['rankings_updated']}");
        $this->line("📈 Lignes stats saison upsertées: {$this->stats['season_stats_upserted']}");
        $this->line("🤝 Confrontations H2H upsertées: {$this->stats['h2h_upserted']}");
        $this->line("💰 Lignes de cotes upsertées: {$this->stats['odds_upserted']}");

        if ($this->stats['errors'] > 0) {
            $this->warn("⚠️ Des erreurs ont été détectées. Consultez les logs pour plus de détails.");
        } else {
            $this->info("🎉 Importation terminée avec succès !");
        }

        ActivityLogger::importFinished('tennis:import-from-cache', 'tennis', [
            'players_created'   => $this->stats['players_created'] ?? 0,
            'players_updated'   => $this->stats['players_updated'] ?? 0,
            'matches_created'   => $this->stats['matches_created'] ?? 0,
            'matches_updated'   => $this->stats['matches_updated'] ?? 0,
            'leagues_created'   => $this->stats['tournament_leagues_created'] ?? 0,
            'leagues_updated'   => $this->stats['tournament_leagues_updated'] ?? 0,
            'stats_available'   => $statsAvailable,
            'stats_missing'     => $statsMissing,
            'errors'            => $this->stats['errors'] ?? 0,
        ]);
        ActivityLogger::flush();
    }
}
