<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Country;
use App\Models\League;
use App\Models\Sport;

class ImportHandballLeagues extends Command
{
    /**
     * Nom et signature de la commande
     */
    protected $signature = 'handball:import-leagues {--force : Forcer l\'importation même si des ligues existent déjà}';

    /**
     * Description de la commande
     */
    protected $description = 'Importer les pays et leurs ligues de handball depuis l\'API Sofascore';

    /**
     * URL de base de l'API Sofascore
     */
    private const SOFASCORE_BASE_URL = 'https://www.sofascore.com/api/v1';

    /**
     * Exécuter la commande
     */
    public function handle()
    {
        $this->info('🤾 Début de l\'importation des ligues de handball...');
        
        try {
            // Récupérer les catégories (pays) de handball
            $this->line('📡 Récupération des catégories de handball...');
            $categoriesResponse = Http::get(self::SOFASCORE_BASE_URL . '/sport/handball/categories');
            
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
            
            $this->info('✅ ' . count($categories) . ' catégories trouvées');
            
            // Récupérer ou créer le sport handball
            $sport = Sport::firstOrCreate(
                ['name' => 'Handball'],
                ['name' => 'Handball']
            );
            
            $totalLeagues = 0;
            $createdLeagues = 0;
            $updatedLeagues = 0;
            $skippedLeagues = 0;
            
            // Traiter chaque catégorie (pays)
            foreach ($categories as $category) {
                $categoryName = $category['name'] ?? '';
                $categoryId = $category['id'] ?? null;
                
                if (empty($categoryName) || empty($categoryId)) {
                    $this->warn('⚠️ Catégorie invalide ignorée: ' . json_encode($category));
                    continue;
                }
                
                $this->line("🌍 Traitement de: {$categoryName}");
                
                // Trouver ou créer le pays
                $country = $this->findOrCreateCountry($categoryName);
                
                if (!$country) {
                    $this->warn("⚠️ Pays non reconnu ignoré: {$categoryName}");
                    continue;
                }
                
                // Récupérer les ligues pour cette catégorie
                $leaguesResponse = Http::get(self::SOFASCORE_BASE_URL . "/category/{$categoryId}/unique-tournaments");
                
                if (!$leaguesResponse->successful()) {
                    $this->warn("⚠️ Erreur lors de la récupération des ligues pour {$categoryName}: " . $leaguesResponse->status());
                    continue;
                }
                
                $leaguesData = $leaguesResponse->json();
                
                // Extraire les ligues des groupes
                $uniqueTournaments = [];
                if (isset($leaguesData['groups']) && is_array($leaguesData['groups'])) {
                    foreach ($leaguesData['groups'] as $group) {
                        if (isset($group['uniqueTournaments']) && is_array($group['uniqueTournaments'])) {
                            $uniqueTournaments = array_merge($uniqueTournaments, $group['uniqueTournaments']);
                        }
                    }
                }
                
                $leagueCount = count($uniqueTournaments);
                $this->line("   📊 " . $leagueCount . " ligues trouvées");
                
                if ($leagueCount === 0) {
                    continue;
                }
                
                // Traiter chaque ligue
                foreach ($uniqueTournaments as $tournament) {
                    $leagueName = $tournament['name'] ?? '';
                    $leagueSlug = $tournament['slug'] ?? '';
                    $leagueId = $tournament['id'] ?? null;
                    
                    if (empty($leagueName) || empty($leagueId)) {
                        $this->warn('⚠️ Ligue invalide ignorée: ' . json_encode($tournament));
                        continue;
                    }
                    
                    // Vérifier si la ligue existe déjà
                    $existingLeague = League::where('sofascore_id', $leagueId)
                        ->where('sport_id', $sport->id)
                        ->first();
                    
                    if ($existingLeague) {
                        // Mettre à jour la ligue existante
                        $existingLeague->update([
                            'name' => $leagueName,
                            'slug' => $leagueSlug,
                            'country_id' => $country->id,
                        ]);
                        $updatedLeagues++;
                        $this->line("   ✏️ Mise à jour: {$leagueName}");
                    } else {
                        // Créer une nouvelle ligue
                        League::create([
                            'name' => $leagueName,
                            'slug' => $leagueSlug,
                            'country_id' => $country->id,
                            'sport_id' => $sport->id,
                            'sofascore_id' => $leagueId,
                        ]);
                        $createdLeagues++;
                        $this->line("   ➕ Créée: {$leagueName}");
                    }
                    
                    $totalLeagues++;
                }
                
                $this->line("");
            }
            
            // Afficher le résumé
            $this->info('🎯 Importation terminée!');
            $this->table(
                ['Métrique', 'Valeur'],
                [
                    ['Ligues traitées', $totalLeagues],
                    ['Ligues créées', $createdLeagues],
                    ['Ligues mises à jour', $updatedLeagues],
                    ['Ligues ignorées', $skippedLeagues],
                ]
            );
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de l\'importation: ' . $e->getMessage());
            Log::error('Erreur importation ligues handball', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * Trouver ou créer un pays basé sur le nom de la catégorie
     */
    private function findOrCreateCountry(string $categoryName): ?Country
    {
        // Chercher le pays par nom (insensible à la casse)
        $country = Country::whereRaw('LOWER(name) = ?', [strtolower($categoryName)])->first();
        
        if (!$country) {
            $this->line("");
            $this->error("❌ Pays non trouvé en base de données:");
            $this->line("   - Nom: {$categoryName}");
            $this->line("   - Catégorie: {$categoryName}");
            $this->line("");
            $this->error("🛑 Arrêt du script. Veuillez ajouter ce pays en base de données avant de continuer.");
            
            Log::error('Script arrêté - Pays non trouvé en base de données', [
                'category_name' => $categoryName
            ]);
            
            exit(1);
        }
        
        return $country;
    }
}