<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Country;
use App\Models\League;
use App\Models\Sport;

class ImportIceHockeyLeagues extends Command
{
    /**
     * Nom et signature de la commande
     */
    protected $signature = 'ice-hockey:import-leagues {--force : Forcer l\'importation même si des ligues existent déjà}';

    /**
     * Description de la commande
     */
    protected $description = 'Importer les pays et leurs ligues de hockey sur glace depuis l\'API Sofascore';

    /**
     * URL de base de l'API Sofascore
     */
    private const SOFASCORE_BASE_URL = 'https://www.sofascore.com/api/v1';

    /**
     * Exécuter la commande
     */
    public function handle()
    {
        $this->info('🏒 Début de l\'importation des ligues de hockey sur glace...');
        
        try {
            // Récupérer tous les pays/catégories depuis l'API
            $this->info('🌍 Récupération des pays et catégories...');
            $countries = $this->fetchCountries();
            
            if (empty($countries)) {
                $this->error('❌ Aucune catégorie récupérée depuis l\'API');
                return Command::FAILURE;
            }
            
            // Récupérer l'ID Sofascore du sport depuis la première catégorie
            $iceHockeySofascoreId = $countries[0]['sport']['id'] ?? null;
            if (!$iceHockeySofascoreId) {
                $this->error('❌ ID Sofascore du sport Ice Hockey non trouvé dans l\'API');
                return Command::FAILURE;
            }
            
            // Récupérer le sport Ice Hockey par son sofascore_id
            $iceHockeySport = Sport::where('sofascore_id', $iceHockeySofascoreId)->first();
            if (!$iceHockeySport) {
                $this->error("❌ Sport Ice Hockey non trouvé (sofascore_id: {$iceHockeySofascoreId})");
                return Command::FAILURE;
            }
            
            $this->info("🏒 Sport trouvé: {$iceHockeySport->name} (ID: {$iceHockeySport->id}, Sofascore ID: {$iceHockeySport->sofascore_id})");
            
            $this->info("📋 " . count($countries) . " pays trouvés");
            
            $stats = [
                'countries_processed' => 0,
                'leagues_created' => 0,
                'leagues_updated' => 0,
                'leagues_skipped' => 0,
                'errors' => 0
            ];
            
            // Étape 2: Pour chaque pays, récupérer ses ligues
            $progressBar = $this->output->createProgressBar(count($countries));
            $progressBar->start();
            
            foreach ($countries as $countryData) {
                try {
                    $this->line("\n🏴 Traitement du pays: {$countryData['name']} ({$countryData['alpha2']})");
                    
                    // Vérifier si le pays existe en base
                    $country = $this->findOrCreateCountry($countryData);
                    
                    if (!$country) {
                        $this->line("   ⚠️  Pays non trouvé en base: {$countryData['name']}");
                        $stats['errors']++;
                        continue;
                    }
                    
                    $this->line("   ✅ Pays trouvé: {$country->name} (ID: {$country->id})");
                    
                    // Récupérer les ligues pour ce pays
                    $leagues = $this->fetchLeaguesForCountry($countryData['id']);
                    
                    if (empty($leagues)) {
                        $this->line("   📭 Aucune ligue trouvée pour {$countryData['name']}");
                        $stats['countries_processed']++;
                        continue;
                    }
                    
                    $this->line("   🏆 " . count($leagues) . " ligues trouvées");
                    
                    // Traiter chaque ligue
                    foreach ($leagues as $leagueData) {
                        $result = $this->processLeague($leagueData, $country, $iceHockeySport);
                        if ($result === 'created') {
                            $stats['leagues_created']++;
                        } elseif ($result === 'updated') {
                            $stats['leagues_updated']++;
                        } else {
                            $stats['leagues_skipped']++;
                        }
                    }
                    
                    $stats['countries_processed']++;
                    
                } catch (\Exception $e) {
                    $this->error("   ❌ Erreur lors du traitement du pays {$countryData['name']}: {$e->getMessage()}");
                    $stats['errors']++;
                    Log::error('Erreur lors du traitement du pays', [
                        'country' => $countryData,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                
                $progressBar->advance();
            }
            
            $progressBar->finish();
            
            // Affichage des statistiques finales
            $this->line("\n\n📊 Résumé de l'importation:");
            $this->line("   🌍 Pays traités: {$stats['countries_processed']}");
            $this->line("   ✅ Ligues créées: {$stats['leagues_created']}");
            $this->line("   🔄 Ligues mises à jour: {$stats['leagues_updated']}");
            $this->line("   ⏭️  Ligues ignorées: {$stats['leagues_skipped']}");
            $this->line("   ❌ Erreurs: {$stats['errors']}");
            
            $total = $stats['leagues_created'] + $stats['leagues_updated'] + $stats['leagues_skipped'];
            $this->line("   📈 Total ligues traitées: {$total}");
            
            $this->info("\n🎉 Importation des ligues de hockey sur glace terminée!");
            
            Log::info('Importation des ligues de hockey sur glace terminée', [
                'countries_processed' => $stats['countries_processed'],
                'leagues_created' => $stats['leagues_created'],
                'leagues_updated' => $stats['leagues_updated'],
                'leagues_skipped' => $stats['leagues_skipped'],
                'errors' => $stats['errors']
            ]);

        ActivityLogger::importFinished('icehockey:import-leagues', 'icehockey', [
            'leagues_created' => $this->stats['leagues_created'] ?? 0,
            'leagues_updated' => $this->stats['leagues_updated'] ?? 0,
            'errors' => $this->stats['errors'] ?? 0,
        ]);
        ActivityLogger::flush();
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de l\'importation: ' . $e->getMessage());
            Log::error('Erreur lors de l\'importation des ligues de hockey sur glace', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * Récupérer les pays depuis l'API Sofascore
     */
    private function fetchCountries(): array
    {
        try {
            $response = Http::get(self::SOFASCORE_BASE_URL . '/sport/ice-hockey/categories');
            
            if (!$response->successful()) {
                $this->error("❌ Erreur lors de la récupération des pays: {$response->status()}");
                return [];
            }
            
            $data = $response->json();
            return $data['categories'] ?? [];
            
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de la récupération des pays: {$e->getMessage()}");
            Log::error('Erreur lors de la récupération des pays', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Récupérer les ligues pour un pays donné
     */
    private function fetchLeaguesForCountry(int $countryId): array
    {
        try {
            $response = Http::get(self::SOFASCORE_BASE_URL . "/category/{$countryId}/tournaments");
            
            if (!$response->successful()) {
                $this->line("     ❌ Erreur lors de la récupération des ligues: {$response->status()}");
                return [];
            }
            
            $data = $response->json();
            
            // Extraire les ligues de la structure groups[].uniqueTournaments[]
            $allLeagues = [];
            foreach ($data['groups'] as $group) {
                if (isset($group['uniqueTournaments']) && is_array($group['uniqueTournaments'])) {
                    $allLeagues = array_merge($allLeagues, $group['uniqueTournaments']);
                }
            }
            
            return $allLeagues;
            
        } catch (\Exception $e) {
            $this->line("     ❌ Erreur lors de la récupération des ligues: {$e->getMessage()}");
            Log::error('Erreur lors de la récupération des ligues', [
                'country_id' => $countryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Traiter une ligue individuelle
     */
    private function processLeague(array $leagueData, Country $country, Sport $sport): string
    {
        try {
            $leagueName = $leagueData['name'];
            $sofascoreId = $leagueData['id'];
            
            // Chercher si la ligue existe déjà
            $existingLeague = League::where('sofascore_id', $sofascoreId)->first();
            
            if ($existingLeague) {
                // Mettre à jour la ligue existante si nécessaire
                $updated = false;
                
                if ($existingLeague->name !== $leagueName) {
                    $existingLeague->name = $leagueName;
                    $updated = true;
                }
                
                if ($existingLeague->country_id !== $country->id) {
                    $existingLeague->country_id = $country->id;
                    $updated = true;
                }
                
                if ($existingLeague->sport_id !== $sport->id) {
                    $existingLeague->sport_id = $sport->id;
                    $updated = true;
                }
                
                if ($updated) {
                    $existingLeague->save();
                    $this->line("     🔄 Ligue mise à jour: {$leagueName}");
                    return 'updated';
                } else {
                    $this->line("     ⏭️  Ligue déjà à jour: {$leagueName}");
                    return 'skipped';
                }
            } else {
                // Créer une nouvelle ligue
                League::create([
                    'name' => $leagueName,
                    'country_id' => $country->id,
                    'sport_id' => $sport->id,
                    'sofascore_id' => $sofascoreId,
                ]);
                
                $this->line("     ✅ Nouvelle ligue créée: {$leagueName}");
                return 'created';
            }
            
        } catch (\Exception $e) {
            $this->line("     ❌ Erreur lors du traitement de la ligue: {$e->getMessage()}");
            Log::error('Erreur lors du traitement de la ligue', [
                'league_data' => $leagueData,
                'country_id' => $country->id,
                'sport_id' => $sport->id,
                'error' => $e->getMessage()
            ]);
            return 'skipped';
        }
    }
    
    /**
      * Trouver ou créer un pays basé sur les données de la catégorie
      */
     private function findOrCreateCountry(array $countryData): ?Country
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
                     'country_data' => $countryData
                 ]);
                 
                 exit(1);
             }
             
             $this->line("     ✅ Pays trouvé: {$country->name} (ID: {$country->id})");
             return $country;
             
         } catch (\Exception $e) {
             $this->error("     ❌ Erreur lors de la recherche du pays: {$e->getMessage()}");
             Log::error('Erreur lors de la recherche du pays', [
                 'country_data' => $countryData,
                 'error' => $e->getMessage(),
                 'trace' => $e->getTraceAsString()
             ]);
             return null;
         }
     }
}