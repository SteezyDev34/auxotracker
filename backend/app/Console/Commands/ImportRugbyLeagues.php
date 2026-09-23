<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Country;
use App\Models\League;
use App\Models\Sport;

class ImportRugbyLeagues extends Command
{
    /**
     * Nom et signature de la commande
     */
    protected $signature = 'rugby:import-leagues {--force : Forcer l\'importation même si des ligues existent déjà}';

    /**
     * Description de la commande
     */
    protected $description = 'Importer les pays et leurs ligues de rugby depuis l\'API Sofascore';

    /**
     * URL de base de l'API Sofascore
     */
    private const SOFASCORE_BASE_URL = 'https://www.sofascore.com/api/v1';

    /**
     * Exécuter la commande
     */
    public function handle()
    {
        $this->info('🏉 Début de l\'importation des ligues de rugby...');
        
        try {
            // Récupérer les catégories (pays) de rugby
            $this->line('📡 Récupération des catégories de rugby...');
            $categoriesResponse = Http::get(self::SOFASCORE_BASE_URL . '/sport/rugby/categories');
            
            if (!$categoriesResponse->successful()) {
                $this->error('❌ Erreur lors de la récupération des catégories: ' . $categoriesResponse->status());
                return 1;
            }
            
            $categoriesData = $categoriesResponse->json();
            $categories = $categoriesData['categories'] ?? [];
            
            if (empty($categories)) {
                $this->error('❌ Aucune catégorie récupérée depuis l\'API');
                return 1;
            }
            
            // Récupérer l'ID Sofascore du sport depuis la première catégorie
            $rugbySofascoreId = $categories[0]['sport']['id'] ?? null;
            if (!$rugbySofascoreId) {
                $this->error('❌ ID Sofascore du sport Rugby non trouvé dans l\'API');
                return 1;
            }
            
            // Récupérer le sport Rugby par son sofascore_id
            $sport = Sport::where('sofascore_id', $rugbySofascoreId)->first();
            if (!$sport) {
                $this->error("❌ Sport Rugby non trouvé (sofascore_id: {$rugbySofascoreId})");
                return 1;
            }
            
            $this->info("✅ Sport trouvé: {$sport->name} (ID: {$sport->id})");
            
            $this->info('📊 ' . count($categories) . ' catégories trouvées');
            
            $totalProcessed = 0;
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalSkipped = 0;
            
            // Barre de progression
            $progressBar = $this->output->createProgressBar(count($categories));
            $progressBar->start();
            
            foreach ($categories as $index => $categoryData) {
                $progressBar->advance();
                
                $this->line("");
                $alpha2 = $categoryData['alpha2'] ?? 'N/A';
                $this->line("🏴 Traitement du pays: {$categoryData['name']} ({$alpha2})");
                
                // Trouver le pays correspondant en base
                $country = $this->findOrCreateCountry($categoryData);
                if (!$country) {
                    continue;
                }
                
                $this->line("   ✅ Pays trouvé: {$country->name} (ID: {$country->id})");
                
                // Récupérer les ligues pour ce pays
                $this->line("     🔍 Récupération des ligues pour le pays ID: {$categoryData['id']}");
                $leaguesResponse = Http::get(self::SOFASCORE_BASE_URL . "/category/{$categoryData['id']}/unique-tournaments");
                
                if (!$leaguesResponse->successful()) {
                    $this->line("     ❌ Erreur lors de la récupération des ligues: {$leaguesResponse->status()}");
                    continue;
                }
                
                $this->line("     📡 Réponse ligues reçue avec le statut: {$leaguesResponse->status()}");
                
                $leaguesData = $leaguesResponse->json();
                
                // Extraire les ligues depuis la structure groups[].uniqueTournaments[]
                $uniqueTournaments = [];
                if (isset($leaguesData['groups']) && is_array($leaguesData['groups'])) {
                    foreach ($leaguesData['groups'] as $group) {
                        if (isset($group['uniqueTournaments']) && is_array($group['uniqueTournaments'])) {
                            $uniqueTournaments = array_merge($uniqueTournaments, $group['uniqueTournaments']);
                        }
                    }
                }
                
                $this->line('   🏆 ' . count($uniqueTournaments) . ' ligues trouvées');
                
                foreach ($uniqueTournaments as $tournamentData) {
                    $result = $this->processLeague($tournamentData, $country, $sport);
                    $totalProcessed++;
                    
                    switch ($result) {
                        case 'created':
                            $totalCreated++;
                            break;
                        case 'updated':
                            $totalUpdated++;
                            break;
                        case 'skipped':
                            $totalSkipped++;
                            break;
                    }
                }
            }
            
            $progressBar->finish();
            $this->line("");
            $this->line("");
            
            // Résumé final
            $this->info('🎉 Importation terminée!');
            $this->table(
                ['Statistique', 'Nombre'],
                [
                    ['Ligues traitées', $totalProcessed],
                    ['Ligues créées', $totalCreated],
                    ['Ligues mises à jour', $totalUpdated],
                    ['Ligues ignorées', $totalSkipped],
                ]
            );
            
            Log::info('Importation des ligues de rugby terminée', [
                'total_processed' => $totalProcessed,
                'total_created' => $totalCreated,
                'total_updated' => $totalUpdated,
                'total_skipped' => $totalSkipped
            ]);

        ActivityLogger::importFinished('rugby:import-leagues', 'rugby', [
            'leagues_created' => $this->stats['leagues_created'] ?? 0,
            'leagues_updated' => $this->stats['leagues_updated'] ?? 0,
            'errors' => $this->stats['errors'] ?? 0,
        ]);
        ActivityLogger::flush();
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de l\'importation: ' . $e->getMessage());
            Log::error('Erreur lors de l\'importation des ligues de rugby', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Traiter une ligue individuelle
     */
    private function processLeague($tournamentData, $country, $sport)
    {
        try {
            $name = $tournamentData['name'];
            $slug = $tournamentData['slug'];
            $sofascoreId = (string) $tournamentData['id'];
            
            $this->line("       🏆 Ligue: {$name} (ID: {$sofascoreId})");
            
            // Vérifier si la ligue existe déjà
            $existingLeague = League::where('sofascore_id', $sofascoreId)
                ->where('sport_id', $sport->id)
                ->first();
            
            if ($existingLeague && !$this->option('force')) {
                $this->line("         ⏭️  Ligue déjà existante, ignorée");
                Log::info('Ligue de rugby ignorée (déjà existante)', [
                    'existing_league_id' => $existingLeague->id,
                    'sofascore_id' => $sofascoreId,
                    'name' => $name,
                    'country_id' => $country->id,
                    'sport_id' => $sport->id
                ]);
                return 'skipped';
            }
            
            // Créer ou mettre à jour la ligue
            $this->line("         💾 " . ($existingLeague ? 'Mise à jour' : 'Création') . " de la ligue...");
            $league = League::updateOrCreate(
                [
                    'sofascore_id' => $sofascoreId,
                    'sport_id' => $sport->id
                ],
                [
                    'name' => $name,
                    'slug' => $slug,
                    'country_id' => $country->id
                ]
            );
            
            $action = $existingLeague ? 'updated' : 'created';
            $this->line("         ✅ Ligue {$action} avec succès (ID: {$league->id})");
            
            Log::info('Ligue de rugby traitée avec succès', [
                'league_id' => $league->id,
                'sofascore_id' => $sofascoreId,
                'name' => $name,
                'country_id' => $country->id,
                'sport_id' => $sport->id,
                'action' => $action
            ]);
            
            return $action;
            
        } catch (\Exception $e) {
            $this->line("         ❌ Erreur lors du traitement de la ligue: {$e->getMessage()}");
            Log::error('Erreur lors du traitement d\'une ligue de rugby', [
                'tournament_data' => $tournamentData,
                'country_id' => $country->id,
                'sport_id' => $sport->id,
                'error' => $e->getMessage()
            ]);
            return 'error';
        }
    }

    /**
     * Trouver ou créer un pays en base de données
     */
    private function findOrCreateCountry($countryData)
    {
        try {
            $this->line("     🔍 Recherche du pays: {$countryData['name']}");
            
            $country = null;
            
            // Chercher d'abord par code (alpha2) si disponible
            if (isset($countryData['alpha2']) && !empty($countryData['alpha2'])) {
                $country = Country::where('code', $countryData['alpha2'])->first();
            }
            
            // Si pas trouvé et pas d'alpha2, chercher par nom
            if (!$country && isset($countryData['name'])) {
                $country = Country::where('name', $countryData['name'])->first();
            }
            
            // Si toujours pas trouvé, chercher par slug
            if (!$country && isset($countryData['slug'])) {
                $country = Country::where('slug', $countryData['slug'])->first();
            }
            
            if (!$country) {
                $this->line("");
                $this->error("❌ Pays non trouvé en base de données:");
                $this->line("   - Nom: {$countryData['name']}");
                $this->line("   - Alpha2: " . ($countryData['alpha2'] ?? 'N/A'));
                $this->line("   - Slug: " . ($countryData['slug'] ?? 'N/A'));
                $this->line("   - ID Sofascore: {$countryData['id']}");
                $this->line("");
                $this->error("🛑 Arrêt du script. Veuillez ajouter ce pays en base de données avant de continuer.");
                
                Log::error('Script arrêté - Pays non trouvé en base de données', [
                    'country_name' => $countryData['name'],
                    'alpha2' => $countryData['alpha2'] ?? null,
                    'slug' => $countryData['slug'] ?? null,
                    'sofascore_id' => $countryData['id']
                ]);
                
                exit(1);
            }
            
            return $country;
            
        } catch (\Exception $e) {
            $this->line("     ❌ Erreur lors de la recherche du pays: {$e->getMessage()}");
            Log::error('Erreur lors de la recherche du pays', [
                'country_data' => $countryData,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}