<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Team;
use App\Services\TeamLogoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportTennisPlayersFromCache extends Command
{
    /**
     * Service de téléchargement des logos
     */
    private $teamLogoService;

    /**
     * Signature de la commande
     */
    protected $signature = 'tennis:import-players-from-cache
                            {--force : Forcer la mise à jour des joueurs existants}
                            {--limit= : Limiter le nombre de joueurs à traiter}
                            {--download-images : Télécharge les images des joueurs}';

    /**
     * Description de la commande
     */
    protected $description = 'Importer les joueurs de tennis depuis les fichiers de cache vers la base de données';

    /**
     * Répertoire de cache
     */
    protected $cacheDirectory;

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
        'cache_files_cleaned' => 0
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

        // Vérifier que le répertoire de cache existe
        if (!is_dir($this->cacheDirectory)) {
            $this->error("❌ Répertoire de cache introuvable: {$this->cacheDirectory}");
            return 1;
        }

        // Traiter les fichiers de cache
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

            // Mettre à jour avec les détails complets si disponibles
            $this->updatePlayerWithDetailsFromCache($player);

            // Télécharger l'image si demandé
            if ($downloadImages) {
                $this->downloadPlayerImage($sofascoreId, $name);
            }

            // Archiver (déplacer) le fichier de cache traité vers le dossier 'processed'
            // NOTE: n'archiver que en environnement de production. En local/dev on garde les fichiers pour debug.
            if (app()->environment('production')) {
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
                        } else {
                            Log::warning('Échec déplacement du fichier de cache traité', ['file' => $cacheFile, 'dest' => $destPath]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur lors de l\'archivage du fichier de cache', ['file' => $cacheFile, 'error' => $e->getMessage()]);
                }
            } else {
                $this->line("ℹ️ Environnement non-production détecté (" . config('app.env') . ") — archivage ignoré pour: " . basename($cacheFile));
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
                // Mettre à jour le champ img si ce n'est pas déjà fait
                if (empty($team->img)) {
                    $team->img = "team_logos/{$team->id}.png";
                    $team->save();
                    $this->line("📝 Champ img mis à jour pour: {$playerName}");
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

                return true;
            } else {
                $this->warn("⚠️ Échec de la copie du logo pour: {$playerName}");
                return false;
            }
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de la copie du logo pour {$playerName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Afficher les statistiques finales
     */
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
        $this->line("❌ Erreurs: {$this->stats['errors']}");

        if ($this->stats['errors'] > 0) {
            $this->warn("⚠️ Des erreurs ont été détectées. Consultez les logs pour plus de détails.");
        } else {
            $this->info("🎉 Importation terminée avec succès !");
        }
    }
}
