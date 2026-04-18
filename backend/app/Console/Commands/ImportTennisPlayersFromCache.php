<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
                            {--download-logos : Télécharge les logos des ligues de tournois}';

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
        'tournament_logos_downloaded' => 0
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

        $force = $this->option('force');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $downloadImages = $this->option('download-images');

        $this->line("📋 Options:");
        $this->line("   - Forcer la mise à jour: " . ($force ? 'Oui' : 'Non'));
        $this->line("   - Limite: " . ($limit ? $limit . ' joueurs' : 'Aucune'));
        $this->line("   - Télécharger les images: " . ($downloadImages ? 'Oui' : 'Non'));

        // Récupérer ou créer la ligue Tennis
        $this->tennisLeague = $this->getTennisLeague();
        if (!$this->tennisLeague) {
            $this->error("❌ Impossible de récupérer ou créer la ligue Tennis");
            return 1;
        }
        $this->line("🎾 Ligue Tennis: {$this->tennisLeague->name} (ID: {$this->tennisLeague->id})");

        // Télécharger le logo de la ligue Tennis si demandé
        if ($downloadImages) {
            $this->downloadTennisLeagueLogo($this->tennisLeague, $force);
        }

        // Vérifier que le répertoire de cache existe
        if (!is_dir($this->cacheDirectory)) {
            $this->error("❌ Répertoire de cache introuvable: {$this->cacheDirectory}");
            return 1;
        }

        // Créer/mettre à jour les ligues de tournois EN PREMIER (Munich, Roland-Garros, etc.)
        // Permet d'avoir les ligues même si le traitement des joueurs est interrompu
        $this->createTournamentLeagues($force, $downloadImages);

        // Traiter les fichiers de cache des joueurs
        $this->processBasicPlayerCacheFiles($force, $limit, $downloadImages);

        // Afficher les statistiques finales
        $this->displayFinalStats();

        return 0;
    }

    /**
     * Traiter les fichiers de cache des données de base des joueurs
     */
    private function processBasicPlayerCacheFiles($force, $limit, $downloadImages)
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

            $this->processBasicPlayerCacheFile($cacheFile, $force, $downloadImages);
        }
    }

    /**
     * Traiter un fichier de cache de données de base d'un joueur
     */
    private function processBasicPlayerCacheFile($cacheFile, $force, $downloadImages)
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

            // Créer ou mettre à jour le joueur
            if ($existingPlayer) {
                // Exclure le nickname lors de la mise à jour d'un joueur existant
                $updateData = $basicData;
                unset($updateData['nickname']);
                $existingPlayer->update($updateData);
                $existingPlayer->touch(); // Garantit la mise à jour d'updated_at
                $this->stats['players_updated']++;
                $this->line("🔄 Joueur mis à jour: {$name} (ID: {$sofascoreId}) - nickname préservé");
                $player = $existingPlayer;
            } else {
                $player = Team::create($basicData);
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
            // Comportement : archiver systématiquement (suppression de la condition sur .synced_at).
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
     * Mettre à jour un joueur avec ses détails complets
     */
    private function updatePlayerWithDetails($player, $playerDetails)
    {
        try {
            $updates = [];

            // Date de naissance
            if (isset($playerDetails['birthDateTimestamp'])) {
                $birthDate = date('Y-m-d', $playerDetails['birthDateTimestamp']);
                $updates['birth_date'] = $birthDate;
            }

            // Taille
            if (isset($playerDetails['height'])) {
                $updates['height'] = $playerDetails['height'];
            }

            // Poids
            if (isset($playerDetails['weight'])) {
                $updates['weight'] = $playerDetails['weight'];
            }

            // Main dominante
            if (isset($playerDetails['plays'])) {
                $updates['plays'] = $playerDetails['plays'];
            }

            // Lieu de naissance
            if (isset($playerDetails['birthPlace']['country']['name'])) {
                $updates['birth_place'] = $playerDetails['birthPlace']['country']['name'];
                if (isset($playerDetails['birthPlace']['city'])) {
                    $updates['birth_place'] = $playerDetails['birthPlace']['city'] . ', ' . $updates['birth_place'];
                }
            }

            // Lieu de résidence
            if (isset($playerDetails['residence']['country']['name'])) {
                $updates['residence'] = $playerDetails['residence']['country']['name'];
                if (isset($playerDetails['residence']['city'])) {
                    $updates['residence'] = $playerDetails['residence']['city'] . ', ' . $updates['residence'];
                }
            }

            // Mettre à jour le joueur si on a des données
            if (!empty($updates)) {
                $player->update($updates);
                $player->touch(); // Garantit la mise à jour d'updated_at



                $updateInfo = [];
                foreach ($updates as $field => $value) {
                    $updateInfo[] = "{$field}: {$value}";
                }

                $this->line("   📝 Détails mis à jour: " . implode(', ', $updateInfo));
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour des détails du joueur', [
                'player_id' => $player->id,
                'error' => $e->getMessage()
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
                // Lire le contenu du marqueur
                $markerData = json_decode(file_get_contents($markerFile), true);
                if (!$markerData || !isset($markerData['sofascore_id'], $markerData['name'])) {
                    continue;
                }

                $tournamentId = $markerData['sofascore_id'];
                $tournamentName = $markerData['name'];
                $tournamentSlug = Str::slug($tournamentName);

                // Vérifier si la ligue existe déjà
                $existingLeague = League::where('sofascore_id', $tournamentId)
                    ->where('sport_id', $tennisSport->id)
                    ->first();

                if ($existingLeague && !$force) {
                    $this->line("   ⏭️ Ligue déjà existante: {$tournamentName} (ID: {$existingLeague->id})");
                    $this->stats['tournament_leagues_skipped']++;

                    // Télécharger le logo si manquant
                    if ($downloadImages && empty($existingLeague->img)) {
                        $this->downloadLeagueLogo($existingLeague, $force);
                    }
                    continue;
                }

                // Créer ou mettre à jour la ligue
                if ($existingLeague) {
                    $existingLeague->update([
                        'name' => $tournamentName,
                        'slug' => $tournamentSlug,
                    ]);
                    $this->line("   🔄 Ligue mise à jour: {$tournamentName} (ID: {$existingLeague->id})");
                    $this->stats['tournament_leagues_updated']++;
                    $league = $existingLeague;
                } else {
                    $league = League::create([
                        'name' => $tournamentName,
                        'slug' => $tournamentSlug,
                        'sport_id' => $tennisSport->id,
                        'sofascore_id' => $tournamentId,
                    ]);
                    $this->line("   ✅ Ligue créée: {$tournamentName} (ID: {$league->id})");
                    $this->stats['tournament_leagues_created']++;
                }

                // Télécharger le logo de la ligue
                if ($downloadImages) {
                    $this->downloadLeagueLogo($league, $force);
                }
            } catch (\Exception $e) {
                $this->warn("⚠️ Erreur lors du traitement du marqueur {$markerFile}: {$e->getMessage()}");
                Log::warning('Erreur traitement marqueur tournoi tennis', [
                    'marker' => $markerFile,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->line("✅ Ligues de tournois traitées: {$this->stats['tournament_leagues_created']} créées, {$this->stats['tournament_leagues_updated']} mises à jour, {$this->stats['tournament_leagues_skipped']} ignorées");
    }

    /**
     * Télécharger le logo d'une ligue : vérifie le cache d'abord, sinon fallback vers API
     */
    private function downloadLeagueLogo(League $league, bool $force): void
    {
        try {
            // Vérifier d'abord si le logo existe dans le cache (Phase 1: API → cache)
            $cacheLogoPath = storage_path('app/sofascore_cache/tennis_leagues/logos/' . $league->sofascore_id . '.png');

            if (file_exists($cacheLogoPath) && filesize($cacheLogoPath) > 0) {
                // Copier depuis le cache vers league_logos/
                $this->copyTournamentLogoFromCache($league, $cacheLogoPath);
                return;
            }

            // Fallback : télécharger depuis l'API si pas dans le cache
            $this->line("      ⚠️ Logo non trouvé dans le cache, téléchargement depuis API: {$league->name}");
            $logoService = app(LeagueLogoService::class);
            $result = $logoService->ensureLeagueLogos($league, $force);

            if ($result && !empty($result['img_updated'])) {
                $this->line("      📸 Logo téléchargé depuis API: {$league->name}");
                $this->stats['tournament_logos_downloaded']++;
                Log::info('Logo ligue Tennis téléchargé depuis API', [
                    'league_id' => $league->id,
                    'league_name' => $league->name,
                    'result' => $result,
                ]);
            } elseif ($result) {
                $this->line("      ⏭️ Logo déjà présent: {$league->name}");
            } else {
                $this->line("      ⚠️ Impossible de télécharger le logo: {$league->name}");
            }
        } catch (\Exception $e) {
            $this->warn("      ⚠️ Erreur téléchargement logo: {$e->getMessage()}");
            Log::warning('Erreur téléchargement logo ligue tournoi tennis', [
                'league_id' => $league->id,
                'error' => $e->getMessage(),
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
     * Télécharger le logo de la ligue Tennis globale (ATP/WTA) via LeagueLogoService
     */
    private function downloadTennisLeagueLogo(League $league, bool $force): void
    {
        try {
            $this->line("📸 Téléchargement du logo de la ligue {$league->name}...");
            $logoService = app(LeagueLogoService::class);
            $result = $logoService->ensureLeagueLogos($league, $force);

            if ($result && !empty($result['img_updated'])) {
                $this->info("✅ Logo de ligue téléchargé: {$league->name}");
                Log::info('Logo ligue Tennis téléchargé', [
                    'league_id' => $league->id,
                    'league_name' => $league->name,
                    'result' => $result,
                ]);
            } elseif ($result) {
                $this->line("⏭️ Logo de ligue déjà présent: {$league->name}");
            } else {
                $this->warn("⚠️ Impossible de télécharger le logo de la ligue {$league->name}");
            }
        } catch (\Exception $e) {
            $this->warn("⚠️ Erreur lors du téléchargement du logo de ligue: {$e->getMessage()}");
            Log::warning('Erreur téléchargement logo ligue Tennis', [
                'league_id' => $league->id,
                'error' => $e->getMessage(),
            ]);
        }
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

        if ($this->stats['errors'] > 0) {
            $this->warn("⚠️ Des erreurs ont été détectées. Consultez les logs pour plus de détails.");
        } else {
            $this->info("🎉 Importation terminée avec succès !");
        }
    }
}
