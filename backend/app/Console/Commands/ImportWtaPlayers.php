<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;

class ImportWtaPlayers extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'wta:import-players {--force : Forcer l\'import même si la joueuse existe}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Importe les joueuses WTA depuis l\'API Sofascore en tant qu\'équipes';

    /**
     * Exécuter la commande console.
     */
    public function handle()
    {
        $this->info('Début de l\'importation des joueuses WTA...');
        
        try {
            $this->info('🌐 Connexion à l\'API Sofascore...');
            
            // Récupérer les données du classement WTA
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://www.sofascore.com/'
                ])
                ->get('https://www.sofascore.com/api/v1/rankings/type/6');

            $this->info('📡 Réponse API reçue avec le statut: ' . $response->status());
            
            if (!$response->successful()) {
                $this->error('Erreur lors de la récupération des données WTA: ' . $response->status());
                Log::error('Échec de la récupération des données WTA', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return Command::FAILURE;
            }

            $data = $response->json();
            $this->info('📊 Données JSON décodées avec succès');
            
            if (!isset($data['rankings']) || !is_array($data['rankings'])) {
                $this->error('Format de données invalide reçu de l\'API');
                Log::error('Format de données WTA invalide', ['data_keys' => array_keys($data)]);
                return Command::FAILURE;
            }

            $players = $data['rankings'];
            $this->info("📋 " . count($players) . " joueuses WTA trouvées dans le classement");
            $this->info('🔄 Début du traitement des joueuses...');

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
                    $this->line("\n🎾 Traitement de la joueuse #" . ($index + 1));
                    $result = $this->processPlayer($playerData);
                    $stats[$result]++;
                    
                    switch($result) {
                        case 'created':
                            $this->line("   ✅ Nouvelle joueuse créée");
                            break;
                        case 'updated':
                            $this->line("   🔄 Joueuse mise à jour");
                            break;
                        case 'skipped':
                            $this->line("   ⏭️  Joueuse ignorée");
                            break;
                    }
                } catch (\Exception $e) {
                    $this->error("   ❌ Erreur lors du traitement de la joueuse #" . ($index + 1) . ": " . $e->getMessage());
                    $stats['errors']++;
                    Log::error('Erreur détaillée lors du traitement de la joueuse WTA', [
                        'player_index' => $index,
                        'player_data' => $playerData,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Afficher les statistiques détaillées
            $this->info('🏁 Importation terminée!');
            $this->newLine();
            $this->info('📊 === Statistiques d\'importation ===');
            $this->info("✅ Joueuses créées: {$stats['created']}");
            $this->info("🔄 Joueuses mises à jour: {$stats['updated']}");
            $this->info("⏭️  Joueuses ignorées: {$stats['skipped']}");
            $this->info("❌ Erreurs: {$stats['errors']}");
            $this->info("📋 Total traité: " . count($players));
            
            $successRate = count($players) > 0 ? round((($stats['created'] + $stats['updated']) / count($players)) * 100, 2) : 0;
            $this->info("📈 Taux de succès: {$successRate}%");
            
            // Log final
            Log::info('Importation WTA terminée', [
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
            Log::error('Erreur lors de l\'importation WTA', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Traiter une joueuse individuelle
     */
    private function processPlayer($playerData)
    {
        if (!isset($playerData['team']) || !isset($playerData['team']['id'])) {
            $this->line("   ⚠️  Données joueuse manquantes ou invalides");
            Log::warning('Données joueuse WTA manquantes', ['player_data' => $playerData]);
            return 'skipped';
        }

        $teamData = $playerData['team'];
        $sofascoreId = $teamData['id'];
        $name = $teamData['name'] ?? 'Joueuse inconnue';
        $nickname = $teamData['shortName'] ?? null;
        $leagueId = 19777; // Ligue WTA (même ID que ATP pour le tennis)
        
        $this->line("   📝 Joueuse: {$name} (ID: {$sofascoreId})");
        
        // Vérifier si la joueuse existe déjà par nom et league_id
        $this->line("   🔍 Vérification de l'existence de la joueuse...");
        $existingTeam = Team::where(function($query) use ($sofascoreId, $name, $leagueId) {
            $query->where('sofascore_id', $sofascoreId)
                  ->orWhere(function($subQuery) use ($name, $leagueId) {
                      $subQuery->where('name', $name)
                               ->where('league_id', $leagueId);
                  });
        })->first();
        
        if ($existingTeam && !$this->option('force')) {
            $this->line("   ⏭️  Joueuse déjà existante (ID: {$existingTeam->id})");
            Log::info('Joueuse WTA déjà existante', [
                'existing_team_id' => $existingTeam->id,
                'sofascore_id' => $sofascoreId,
                'name' => $name,
                'league_id' => $leagueId
            ]);
            return 'skipped';
        }

        // Créer ou mettre à jour la joueuse
        $this->line("   💾 " . ($existingTeam ? 'Mise à jour' : 'Création') . " de la joueuse en base...");
        $team = Team::updateOrCreate(
            [
                'sofascore_id' => $sofascoreId
            ],
            [
                'name' => $name,
                'nickname' => $nickname,
                'slug' => \Illuminate\Support\Str::slug($name),
                'league_id' => $leagueId
            ]
        );

        Log::info('Joueuse WTA traitée avec succès', [
            'team_id' => $team->id,
            'sofascore_id' => $sofascoreId,
            'name' => $name,
            'league_id' => $leagueId,
            'action' => $existingTeam ? 'updated' : 'created'
        ]);

        // Télécharger l'image de la joueuse
        $this->line("   🖼️  Téléchargement de l'image...");
        $this->downloadPlayerImage($team);

        return $existingTeam ? 'updated' : 'created';
    }

    /**
     * Télécharger l'image d'une joueuse
     */
    private function downloadPlayerImage(Team $team)
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
                
                // Créer le répertoire s'il n'existe pas
                $directory = dirname(storage_path('app/public/' . $imagePath));
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
                
                // Sauvegarder l'image
                Storage::disk('public')->put($imagePath, $response->body());
                
                // Mettre à jour le chemin de l'image dans la base de données
                $team->update(['img' => $imagePath]);
                
                $this->line("     ✅ Image sauvegardée: {$imagePath}");
                
                Log::info("Image téléchargée pour la joueuse {$team->name}", [
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
            Log::warning("Échec du téléchargement de l'image pour la joueuse {$team->name}", [
                'team_id' => $team->id,
                'sofascore_id' => $team->sofascore_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return false;
    }
}