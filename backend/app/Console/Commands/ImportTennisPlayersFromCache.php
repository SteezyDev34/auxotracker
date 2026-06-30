<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\League;
use App\Models\Sport;
use App\Services\TeamLogoService;
use App\Services\LeagueLogoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportTennisPlayersFromCache extends Command
{
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
        'tournament_leagues_skipped' => 0,
        'tournament_logos_downloaded' => 0,
        'matches_processed' => 0,
        'matches_created' => 0,
        'matches_updated' => 0,
        'matches_skipped' => 0,
        'matches_errors' => 0,
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
            $slug             = $event['slug'] ?? '';
            $customId         = $event['customId'] ?? '';
            $sofascoreLink    = 'https://www.sofascore.com/fr/tennis/match/' . $slug . '/' . $customId . '#id:' . $eventId;

            // Récupérer l'ID interne du sport Tennis (sofascore_id = 5)
            $tennisSport = Sport::where('sofascore_id', 5)->orWhere('slug', 'tennis')->first();
            $sportId     = $tennisSport?->id ?? 5;

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

            $action = $record->wasRecentlyCreated ? 'créé' : 'mis à jour';
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
            } else {
                $this->stats['matches_updated']++;
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
                if ($downloadImages && empty($existingPlayer->img)) {
                    $this->downloadPlayerImage($sofascoreId, $name);
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
                $existingPlayer->update($updateData);
                $existingPlayer->touch();
                $this->stats['players_updated']++;
                $this->line("🔄 Joueur mis à jour: {$name} (ID: {$sofascoreId}) - nickname préservé");
                $player = $existingPlayer;
            } else {
                $player = Team::create($teamData);
                $this->stats['players_created']++;
                $this->line("✅ Joueur créé: {$name} (ID: {$sofascoreId})");
            }

            // Synchroniser la table pivot league_team
            $player->leagues()->syncWithoutDetaching([$this->tennisLeague->id]);
            $this->line("   🔗 Pivot league_team synchronisé (league: {$this->tennisLeague->id}, player: {$player->id})");

            // Mettre à jour avec les détails complets si disponibles
            $this->updatePlayerWithDetailsFromCache($player);

            // Télécharger l'image si demandé
            if ($downloadImages) {
                $this->downloadPlayerImage($sofascoreId, $name);
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
    private function downloadPlayerImage($sofascoreId, $playerName)
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
            if (file_exists($destinationPath)) {
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
                    continue;
                }

                $leagueData = [
                    'name'     => $tournamentName,
                    'slug'     => $tournamentSlug,
                    'sport_id' => $tennisSport->id,
                ];

                // Créer ou mettre à jour la ligue
                if ($existingLeague) {
                    $existingLeague->update($leagueData);
                    $this->line("   🔄 Ligue mise à jour: {$tournamentName}" . ($categoryName ? " [{$categoryName}]" : ''));
                    $this->stats['tournament_leagues_updated']++;
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

        $this->line("✅ Ligues de tournois traitées: {$this->stats['tournament_leagues_created']} créées, {$this->stats['tournament_leagues_updated']} mises à jour, {$this->stats['tournament_leagues_skipped']} ignorées");
    }

    /**
     * Copier le logo d'une ligue depuis le cache vers league_logos/.
     * Phase 2 est cache-only : aucun appel API (le téléchargement depuis Sofascore
     * est fait en Phase 1 via --download-logos sur la machine locale).
     */
    private function downloadLeagueLogo(League $league, bool $force): void
    {
        try {
            $cacheLogoPath = storage_path('app/sofascore_cache/tennis_leagues/logos/' . $league->sofascore_id . '.png');

            if (!file_exists($cacheLogoPath) || filesize($cacheLogoPath) === 0) {
                $this->line("      ⚠️ Logo absent du cache pour: {$league->name} (sofascore_id: {$league->sofascore_id}) — relancer Phase 1 avec --download-logos");
                Log::warning('league_logo_missing_from_cache', [
                    'league_id'    => $league->id,
                    'sofascore_id' => $league->sofascore_id,
                    'cache_path'   => $cacheLogoPath,
                ]);
                return;
            }

            $this->copyTournamentLogoFromCache($league, $cacheLogoPath);
        } catch (\Exception $e) {
            $this->warn("      ⚠️ Erreur copie logo ligue: {$e->getMessage()}");
            Log::warning('Erreur copie logo ligue tournoi tennis', [
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
        $this->line("⏭️ Joueurs ignorés: {$this->stats['players_skipped']}");
        $this->line("🔄 Doublons détectés: {$this->stats['duplicates_detected']}");
        $this->line("📸 Images copiées: {$this->stats['images_downloaded']}");
        $this->line("⏭️ Images ignorées (déjà présentes): {$this->stats['images_skipped']}");
        $this->line("⚠️ Images manquantes dans le cache: {$this->stats['images_missing']}");
        $this->line("❌ Images échouées: {$this->stats['images_failed']}");
        $this->line("🏆 Ligues de tournois créées: {$this->stats['tournament_leagues_created']}");
        $this->line("🔄 Ligues de tournois mises à jour: {$this->stats['tournament_leagues_updated']}");
        $this->line("⏭️ Ligues de tournois ignorées: {$this->stats['tournament_leagues_skipped']}");
        $this->line("📸 Logos de ligues téléchargés: {$this->stats['tournament_logos_downloaded']}");
        $this->line("❌ Erreurs: {$this->stats['errors']}");
        $this->info('');
        $this->info('⚽ === MATCHS ===');
        $this->line("⚽ Matchs traités: {$this->stats['matches_processed']}");
        $this->line("✅ Matchs créés: {$this->stats['matches_created']}");
        $this->line("🔄 Matchs mis à jour: {$this->stats['matches_updated']}");
        $this->line("⏭️ Matchs ignorés (passés): {$this->stats['matches_skipped']}");
        $this->line("❌ Erreurs matchs: {$this->stats['matches_errors']}");

        if ($this->stats['errors'] > 0) {
            $this->warn("⚠️ Des erreurs ont été détectées. Consultez les logs pour plus de détails.");
        } else {
            $this->info("🎉 Importation terminée avec succès !");
        }
    }
}
