<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImportAtpPlayers extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'atp:import-players {--force : Forcer l\'import même si le joueur existe}';

    /**
     * Description de la commande console.
     *
     * @var string
     */
    protected $description = 'Importe les joueurs ATP depuis l\'API Sofascore en tant qu\'équipes';

    /**
     * Exécute la commande console.
     */
    public function handle()
    {
        $this->info('Début de l\'importation des joueurs ATP...');

        try {
            $this->info('🌐 Connexion à l\'API Sofascore...');

            // Récupérer les données du classement ATP
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://www.sofascore.com/'
                ])
                ->get('https://www.sofascore.com/api/v1/rankings/type/5');

            $this->info('📡 Réponse API reçue avec le statut: ' . $response->status());

            if (!$response->successful()) {
                $this->error('Erreur lors de la récupération des données ATP: ' . $response->status());
                Log::error('Échec de la récupération des données ATP', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return Command::FAILURE;
            }

            $data = $response->json();
            $this->info('📊 Données JSON décodées avec succès');

            if (!isset($data['rankings']) || !is_array($data['rankings'])) {
                $this->error('Format de données invalide reçu de l\'API');
                Log::error('Format de données ATP invalide', ['data_keys' => array_keys($data)]);
                return Command::FAILURE;
            }

            $players = $data['rankings'];
            $this->info("📋 " . count($players) . " joueurs ATP trouvés dans le classement");
            $this->info('🔄 Début du traitement des joueurs...');

            $progressBar = $this->output->createProgressBar(count($players));
            $progressBar->start();

            $stats = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0
            ];

            foreach ($players as $index => $playerData) {
                try {
                    $this->line("\n🎾 Traitement du joueur #" . ($index + 1));
                    $result = $this->processPlayer($playerData);
                    $stats[$result]++;

                    switch ($result) {
                        case 'created':
                            $this->line("   ✅ Nouveau joueur créé");
                            break;
                        case 'updated':
                            $this->line("   🔄 Joueur mis à jour");
                            break;
                        case 'skipped':
                            $this->line("   ⏭️  Joueur ignoré");
                            break;
                    }
                } catch (\Exception $e) {
                    $this->error("   ❌ Erreur lors du traitement du joueur #" . ($index + 1) . ": " . $e->getMessage());
                    $stats['errors']++;
                    Log::error('Erreur détaillée lors du traitement du joueur ATP', [
                        'player_index' => $index,
                        'player_data' => $playerData,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }

                $progressBar->advance();

                // Pause pour éviter de surcharger l'API
                usleep(200000); // 0.2 seconde
            }

            $progressBar->finish();
            $this->newLine(2);

            // Afficher les statistiques détaillées
            $this->info('🏁 Importation terminée!');
            $this->newLine();
            $this->info('📊 === Statistiques d\'importation ===');
            $this->info("✅ Joueurs créés: {$stats['created']}");
            $this->info("🔄 Joueurs mis à jour: {$stats['updated']}");
            $this->info("⏭️  Joueurs ignorés: {$stats['skipped']}");
            $this->info("❌ Erreurs: {$stats['errors']}");
            $this->info("📋 Total traité: " . count($players));

            $successRate = count($players) > 0 ? round((($stats['created'] + $stats['updated']) / count($players)) * 100, 2) : 0;
            $this->info("📈 Taux de succès: {$successRate}%");

            // Log final
            Log::info('Importation ATP terminée', [
                'total_players' => count($players),
                'created' => $stats['created'],
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
                'errors' => $stats['errors'],
                'success_rate' => $successRate,
                'force_mode' => $this->option('force')
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur générale: ' . $e->getMessage());
            Log::error('Erreur lors de l\'importation ATP', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    /**
     * Traite un joueur individuel
     * 
     * @param array $playerData
     * @return string
     */
    private function processPlayer(array $playerData): string
    {
        if (!isset($playerData['team']) || !isset($playerData['team']['id'])) {
            $this->line("   ⚠️  Données joueur manquantes ou invalides");
            Log::warning('Données joueur ATP manquantes', ['player_data' => $playerData]);
            return 'skipped';
        }

        $teamData = $playerData['team'];
        $sofascoreId = $teamData['id'];
        $name = $teamData['name'] ?? 'Joueur inconnu';
        $nickname = $teamData['shortName'] ?? null;
        $leagueId = 19777; // Ligue ATP (ID fixe)

        $this->line("   📝 Joueur: {$name} (ID: {$sofascoreId})");

        // Vérifier si le joueur existe déjà par sofascore_id ou par nom dans la ligue ATP
        $this->line("   🔍 Vérification de l'existence du joueur...");
        $existingTeam = Team::where('sofascore_id', $sofascoreId)->first();

        if (!$existingTeam) {
            $existingTeam = Team::where('name', $name)
                ->whereHas('leagues', function ($q) use ($leagueId) {
                    $q->where('leagues.id', $leagueId);
                })->first();
        }

        if ($existingTeam && !$this->option('force')) {
            $this->line("   ⏭️  Joueur déjà existant (ID: {$existingTeam->id})");
            Log::info('Joueur ATP déjà existant', [
                'existing_team_id' => $existingTeam->id,
                'sofascore_id' => $sofascoreId,
                'name' => $name,
                'league_id' => $leagueId
            ]);
            return 'skipped';
        }

        // Créer ou mettre à jour le joueur
        $this->line("   💾 " . ($existingTeam ? 'Mise à jour' : 'Création') . " du joueur en base...");
        $team = Team::updateOrCreate(
            [
                'sofascore_id' => $sofascoreId,
            ],
            [
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'league_id' => $leagueId
            ]
        );

        if (!empty($nickname)) {
            $team->addNickname($nickname);
        }

        // S'assurer que la table pivot reflète l'appartenance à la ligue ATP
        try {
            $team->leagues()->syncWithoutDetaching([$leagueId]);
        } catch (\Exception $e) {
            Log::warning('Erreur mise à jour pivot league_team (ATP import)', ['team_id' => $team->id ?? null, 'league_id' => $leagueId, 'error' => $e->getMessage()]);
        }

        Log::info('Joueur ATP traité avec succès', [
            'team_id' => $team->id,
            'sofascore_id' => $sofascoreId,
            'name' => $name,
            'league_id' => $leagueId,
            'action' => $existingTeam ? 'updated' : 'created'
        ]);

        // Télécharger l'image du joueur
        $this->line("   🖼️  Téléchargement de l'image...");
        $this->downloadPlayerImage($team);

        return $existingTeam ? 'updated' : 'created';
    }

    /**
     * Télécharge l'image d'un joueur
     * 
     * @param Team $team
     * @return bool
     */
    private function downloadPlayerImage(Team $team): bool
    {
        try {
            $imageUrl = "https://api.sofascore.com/api/v1/team/{$team->sofascore_id}/image";
            $this->line("     🌐 Téléchargement depuis: {$imageUrl}");

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'image/*',
                    'Referer' => 'https://www.sofascore.com/'
                ])
                ->get($imageUrl);

            $this->line("     📡 Réponse image: " . $response->status());

            if ($response->successful()) {
                $imagePath = "team_logos/{$team->id}.png";

                // Créer le dossier s'il n'existe pas
                Storage::disk('public')->makeDirectory('team_logos');

                // Sauvegarder l'image
                Storage::disk('public')->put($imagePath, $response->body());

                // Mettre à jour le chemin de l'image dans la base de données
                $team->update(['img' => $imagePath]);

                $this->line("     ✅ Image sauvegardée: {$imagePath}");

                Log::info("Image téléchargée pour le joueur {$team->name}", [
                    'team_id' => $team->id,
                    'sofascore_id' => $team->sofascore_id,
                    'path' => $imagePath,
                    'image_url' => $imageUrl
                ]);

                return true;
            } else {
                $this->line("     ❌ Échec du téléchargement (Status: {$response->status()})");
                Log::warning("Échec du téléchargement d'image - Status HTTP", [
                    'team_id' => $team->id,
                    'sofascore_id' => $team->sofascore_id,
                    'status' => $response->status(),
                    'image_url' => $imageUrl
                ]);
            }
        } catch (\Exception $e) {
            $this->line("     ❌ Erreur lors du téléchargement: {$e->getMessage()}");
            Log::warning("Échec du téléchargement de l'image pour le joueur {$team->name}", [
                'team_id' => $team->id,
                'sofascore_id' => $team->sofascore_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return false;
    }
}
