<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImportTeams extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'teams:import {debut=1} {fin=500000} {--force : Forcer l\'importation même si l\'équipe existe déjà}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Importer les équipes depuis l\'API Sofascore avec une plage d\'IDs';

    /**
     * Statistiques d'importation
     */
    private $stats = [
        'teams_processed' => 0,
        'teams_created' => 0,
        'teams_updated' => 0,
        'teams_skipped' => 0,
        'duplicates_detected' => 0,
        'errors' => 0,
        'api_errors' => 0,
        'league_not_found' => 0
    ];

    /**
     * Exécuter la commande console.
     */
    public function handle()
    {
        $debut = (int) $this->argument('debut');
        $fin = (int) $this->argument('fin');
        $force = $this->option('force');

        $this->line("🚀 Début de l'importation des équipes");
        $this->line("📊 Plage d'IDs: {$debut} à {$fin}");
        $this->line("🔄 Mode force: " . ($force ? 'Activé' : 'Désactivé'));
        $this->line("");

        $total = $fin - $debut + 1;
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('Démarrage...');
        $progressBar->start();

        for ($teamId = $debut; $teamId <= $fin; $teamId++) {
            try {
                $progressBar->setMessage("Traitement équipe ID: {$teamId}");
                
                $this->processTeam($teamId, $force);
                $this->stats['teams_processed']++;
                
                $progressBar->advance();
                
                // Pause pour éviter de surcharger l'API
                usleep(100000); // 100ms
                
            } catch (\Exception $e) {
                $this->stats['errors']++;
                Log::error('Erreur lors du traitement de l\'équipe', [
                    'team_id' => $teamId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->line("\n");
        $this->displayStats();
    }

    /**
     * Traiter une équipe individuelle
     */
    private function processTeam($teamId, $force)
    {
        try {
            Log::info("🔍 Début du traitement de l'équipe", ['sofascore_id' => $teamId]);
            
            // Vérifier d'abord si l'équipe existe déjà en base pour éviter les appels API inutiles
            $existingTeam = Team::where('sofascore_id', $teamId)->first();
            
            if ($existingTeam && !$force) {
                Log::info("⏭️ Équipe déjà existante, ignorée", [
                    'sofascore_id' => $teamId,
                    'team_name' => $existingTeam->name,
                    'team_id' => $existingTeam->id
                ]);
                $this->stats['teams_skipped']++;
                return;
            }
            
            // Récupérer les données de l'équipe depuis l'API
            Log::info("🌐 Récupération des données depuis l'API Sofascore", ['sofascore_id' => $teamId]);
            $teamData = $this->fetchTeamData($teamId);
            
            if (!$teamData) {
                Log::warning("❌ Aucune donnée récupérée pour l'équipe", ['sofascore_id' => $teamId]);
                return;
            }

            // Extraire les informations nécessaires
            $name = $teamData['team']['name'] ?? null;
            $slug = $teamData['team']['slug'] ?? null;
            $shortName = $teamData['team']['shortName'] ?? null;
            $uniqueTournamentId = $teamData['team']['tournament']['uniqueTournament']['id'] ?? null;

            Log::info("📋 Données extraites de l'équipe", [
                'sofascore_id' => $teamId,
                'name' => $name,
                'slug' => $slug,
                'short_name' => $shortName,
                'tournament_id' => $uniqueTournamentId
            ]);

            if (!$name || !$slug || !$uniqueTournamentId) {
                Log::warning("⚠️ Données incomplètes pour l'équipe", [
                    'sofascore_id' => $teamId,
                    'missing_fields' => [
                        'name' => !$name,
                        'slug' => !$slug,
                        'tournament_id' => !$uniqueTournamentId
                    ]
                ]);
                $this->stats['teams_skipped']++;
                return;
            }

            // Vérifier si la ligue existe en base de données
            Log::info("🔍 Recherche de la ligue associée", [
                'tournament_sofascore_id' => $uniqueTournamentId
            ]);
            
            $league = League::where('sofascore_id', $uniqueTournamentId)->first();
            
            if (!$league) {
                Log::warning("🏆 Ligue non trouvée en base de données", [
                    'sofascore_id' => $teamId,
                    'team_name' => $name,
                    'tournament_sofascore_id' => $uniqueTournamentId
                ]);
                $this->stats['league_not_found']++;
                return;
            }
            
            Log::info("✅ Ligue trouvée", [
                'league_id' => $league->id,
                'league_name' => $league->name,
                'tournament_sofascore_id' => $uniqueTournamentId
            ]);

            // Vérification supplémentaire des doublons par nom et slug dans la même ligue
            $duplicateByName = Team::where('name', $name)
                                  ->where('league_id', $league->id)
                                  ->where('sofascore_id', '!=', $teamId)
                                  ->first();
                                  
            $duplicateBySlug = Team::where('slug', $slug)
                                  ->where('league_id', $league->id)
                                  ->where('sofascore_id', '!=', $teamId)
                                  ->first();
            
            if ($duplicateByName || $duplicateBySlug) {
                 $this->stats['duplicates_detected']++;
                 Log::warning("🔄 Doublon potentiel détecté", [
                     'sofascore_id' => $teamId,
                     'team_name' => $name,
                     'duplicate_by_name' => $duplicateByName ? [
                         'id' => $duplicateByName->id,
                         'sofascore_id' => $duplicateByName->sofascore_id,
                         'name' => $duplicateByName->name
                     ] : null,
                     'duplicate_by_slug' => $duplicateBySlug ? [
                         'id' => $duplicateBySlug->id,
                         'sofascore_id' => $duplicateBySlug->sofascore_id,
                         'slug' => $duplicateBySlug->slug
                     ] : null
                 ]);
             }

            // Créer ou mettre à jour l'équipe
            $teamAttributes = [
                'name' => $name,
                'slug' => $slug,
                'nickname' => $shortName,
                'sofascore_id' => $teamId,
                'league_id' => $league->id
            ];

            if ($existingTeam) {
                Log::info("🔄 Mise à jour de l'équipe existante", [
                    'team_id' => $existingTeam->id,
                    'sofascore_id' => $teamId,
                    'old_name' => $existingTeam->name,
                    'new_name' => $name,
                    'league_id' => $league->id
                ]);
                
                $existingTeam->update($teamAttributes);
                $this->stats['teams_updated']++;
                
                Log::info("✅ Équipe mise à jour avec succès", [
                    'team_id' => $existingTeam->id,
                    'sofascore_id' => $teamId,
                    'name' => $name
                ]);
            } else {
                Log::info("➕ Création d'une nouvelle équipe", [
                    'sofascore_id' => $teamId,
                    'name' => $name,
                    'league_id' => $league->id,
                    'league_name' => $league->name
                ]);
                
                $newTeam = Team::create($teamAttributes);
                $this->stats['teams_created']++;
                
                Log::info("✅ Équipe créée avec succès", [
                    'team_id' => $newTeam->id,
                    'sofascore_id' => $teamId,
                    'name' => $name
                ]);
            }

        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('❌ Erreur lors du traitement de l\'équipe', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Récupérer les données d'une équipe depuis l'API Sofascore
     */
    private function fetchTeamData($teamId)
    {
        try {
            $url = "https://www.sofascore.com/api/v1/team/{$teamId}";
            Log::debug("🌐 Appel API Sofascore", [
                'url' => $url,
                'team_id' => $teamId
            ]);
            
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
                'Referer' => 'https://www.sofascore.com/'
            ])->timeout(10)->get($url);

            Log::debug("📡 Réponse API reçue", [
                'team_id' => $teamId,
                'status_code' => $response->status(),
                'response_size' => strlen($response->body())
            ]);

            if (!$response->successful()) {
                if ($response->status() === 404) {
                    Log::debug("🔍 Équipe non trouvée (404)", [
                        'team_id' => $teamId,
                        'url' => $url
                    ]);
                    return null;
                }
                
                $this->stats['api_errors']++;
                Log::warning('⚠️ Erreur API lors de la récupération de l\'équipe', [
                    'team_id' => $teamId,
                    'status' => $response->status(),
                    'url' => $url,
                    'body' => substr($response->body(), 0, 500) // Limiter la taille du log
                ]);
                return null;
            }

            $data = $response->json();
            
            if (!isset($data['team'])) {
                $this->stats['api_errors']++;
                Log::warning("⚠️ Structure de données API invalide", [
                    'team_id' => $teamId,
                    'available_keys' => array_keys($data ?? []),
                    'url' => $url
                ]);
                return null;
            }

            Log::debug("✅ Données d'équipe récupérées avec succès", [
                'team_id' => $teamId,
                'team_name' => $data['team']['name'] ?? 'N/A',
                'team_slug' => $data['team']['slug'] ?? 'N/A',
                'tournament_id' => $data['team']['tournament']['uniqueTournament']['id'] ?? 'N/A'
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->stats['api_errors']++;
            Log::error('❌ Exception lors de la récupération des données d\'équipe', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Afficher les statistiques d'importation
     */
    private function displayStats()
    {
        $this->line("🏁 Importation terminée!\n");
        $this->line("📊 === Statistiques d'importation ===");
        $this->line("🔢 Équipes traitées: {$this->stats['teams_processed']}");
        $this->line("✅ Équipes créées: {$this->stats['teams_created']}");
        $this->line("🔄 Équipes mises à jour: {$this->stats['teams_updated']}");
        $this->line("⏭️  Équipes ignorées: {$this->stats['teams_skipped']}");
        $this->line("🔄 Doublons détectés: {$this->stats['duplicates_detected']}");
        $this->line("🏆 Ligues non trouvées: {$this->stats['league_not_found']}");
        $this->line("🌐 Erreurs API: {$this->stats['api_errors']}");
        $this->line("❌ Autres erreurs: {$this->stats['errors']}");
        
        $totalTeams = $this->stats['teams_created'] + $this->stats['teams_updated'];
        $this->line("📋 Total équipes ajoutées/modifiées: {$totalTeams}");
        
        if ($this->stats['teams_processed'] > 0) {
            $successRate = round((($totalTeams) / $this->stats['teams_processed']) * 100, 2);
            $this->line("📈 Taux de succès: {$successRate}%");
        }
        
        // Affichage des détails supplémentaires
        $this->line("\n📋 === Détails supplémentaires ===");
        if ($this->stats['duplicates_detected'] > 0) {
            $this->line("⚠️  {$this->stats['duplicates_detected']} doublons potentiels détectés (vérifiez les logs pour plus de détails)");
        }
        if ($this->stats['league_not_found'] > 0) {
            $this->line("⚠️  {$this->stats['league_not_found']} équipes ignorées car leur ligue n'existe pas en base");
        }
        if ($this->stats['api_errors'] > 0) {
            $this->line("⚠️  {$this->stats['api_errors']} erreurs lors des appels API Sofascore");
        }

        Log::info('Importation d\'équipes terminée', $this->stats);
    }
}