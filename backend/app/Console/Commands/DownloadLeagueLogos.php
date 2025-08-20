<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LeagueLogoService;
use App\Models\League;
use Illuminate\Support\Facades\Storage;

class DownloadLeagueLogos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'league:download-logos {league_id? : ID de la ligue spécifique à télécharger} {--force : Force le téléchargement même si les logos existent} {--empty-img : Ne traiter que les ligues avec le champ img vide} {--delay=0 : Délai en secondes entre chaque requête API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Télécharge les logos manquants des ligues depuis l\'API Sofascore (light et dark). Options: --force pour forcer le téléchargement, --empty-img pour ne traiter que les ligues avec img vide.';

    /**
     * Execute the console command.
     */
    public function handle(LeagueLogoService $logoService)
    {
        $leagueId = $this->argument('league_id');
        $delay = (int) $this->option('delay');
        
        $this->line("🚀 Début du téléchargement des logos de ligues");
        $this->line("⏱️  Délai entre requêtes: {$delay} seconde(s)");
        $this->line("");
        
        if ($leagueId) {
            // Télécharger les logos d'une ligue spécifique
            return $this->downloadSingleLeagueLogos($logoService, $leagueId);
        }
        
        // Récupérer les ligues selon les critères
        $query = League::whereNotNull('sofascore_id');
        
        if ($this->option('empty-img')) {
            $this->info('🔍 Filtrage des ligues avec le champ img vide uniquement...');
            $query->where(function($q) {
                $q->whereNull('img')
                  ->orWhere('img', '')
                  ->orWhere('img', 'NOT LIKE', '%league_logos%');
            });
        }
        
        $leagues = $query->get();
        
        if ($leagues->isEmpty()) {
            $this->warn('Aucune ligue avec un sofascore_id trouvée.');
            return 0;
        }
        
        $this->info("Traitement de {$leagues->count()} ligues...");
        
        $progressBar = $this->output->createProgressBar($leagues->count());
        $progressBar->start();
        
        $stats = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'img_updated' => 0,
            'light_only' => 0,
            'dark_only' => 0,
            'both' => 0
        ];
        
        foreach ($leagues as $league) {
            // Vérifier si les logos existent déjà (fichiers physiques ou champ img renseigné)
            $lightExists = Storage::disk('public')->exists("league_logos/{$league->id}.png");
            $darkExists = Storage::disk('public')->exists("league_logos/{$league->id}-dark.png");
            $imgFieldExists = !empty($league->img) && Storage::disk('public')->exists($league->img);
            
            if (!$this->option('force') && ($lightExists || $darkExists || $imgFieldExists)) {
                $stats['skipped']++;
                // Mettre à jour le champ img si nécessaire même pour les logos ignorés
                $result = $logoService->ensureLeagueLogos($league);
                if ($result && isset($result['img_updated']) && $result['img_updated']) {
                    $stats['img_updated']++;
                }
            } else {
                $result = $logoService->ensureLeagueLogos($league);
                
                if ($result) {
                    $stats['success']++;
                    
                    // Compter les mises à jour du champ img
                    if (isset($result['img_updated']) && $result['img_updated']) {
                        $stats['img_updated']++;
                    }
                    
                    if (isset($result['light']) && isset($result['dark'])) {
                        $stats['both']++;
                    } elseif (isset($result['light'])) {
                        $stats['light_only']++;
                    } elseif (isset($result['dark'])) {
                        $stats['dark_only']++;
                    }
                } else {
                    $stats['failed']++;
                }
            }
            
            $progressBar->advance();
            
            // Pause pour éviter de surcharger l'API
            if ($delay > 0) {
                sleep($delay);
            } else {
                usleep(500000); // 0.5 seconde par défaut
            }
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        // Afficher les statistiques
        $this->info('Téléchargement terminé!');
        $this->table(
            ['Statut', 'Nombre'],
            [
                ['Succès', $stats['success']],
                ['Échecs', $stats['failed']],
                ['Ignorés', $stats['skipped']],
                ['Champ img mis à jour', $stats['img_updated']],
                ['Light seulement', $stats['light_only']],
                ['Dark seulement', $stats['dark_only']],
                ['Light + Dark', $stats['both']],
                ['Total', $leagues->count()]
            ]
        );
        
        return 0;
    }

    /**
     * Télécharge les logos d'une ligue spécifique
     * 
     * @param LeagueLogoService $logoService
     * @param int $leagueId
     * @return int
     */
    private function downloadSingleLeagueLogos(LeagueLogoService $logoService, int $leagueId): int
    {
        $this->info("Téléchargement des logos pour la ligue ID: {$leagueId}");
        
        try {
            $league = League::findOrFail($leagueId);
            
            if (!$league->sofascore_id) {
                $this->error("La ligue '{$league->name}' n'a pas de sofascore_id défini.");
                return 1;
            }
            
            // Vérifier si les logos existent déjà (fichiers physiques ou champ img renseigné)
            $lightExists = Storage::disk('public')->exists("league_logos/{$league->id}.png");
            $darkExists = Storage::disk('public')->exists("league_logos/{$league->id}-dark.png");
            $imgFieldExists = !empty($league->img) && Storage::disk('public')->exists($league->img);
            
            if (!$this->option('force') && ($lightExists || $darkExists || $imgFieldExists)) {
                $this->info("Les logos de la ligue '{$league->name}' existent déjà. Utilisez --force pour forcer le téléchargement.");
                // Mettre à jour le champ img si nécessaire même si on ne télécharge pas
                $result = $logoService->ensureLeagueLogos($league);
                if ($result && isset($result['img_updated']) && $result['img_updated']) {
                    $this->info("📝 Champ img mis à jour pour la ligue '{$league->name}'");
                }
                return 0;
            }
            
            $result = $logoService->ensureLeagueLogos($league);
            
            if ($result) {
                $this->info("✅ Logos téléchargés avec succès pour la ligue '{$league->name}'");
                
                if (isset($result['img_updated']) && $result['img_updated']) {
                    $this->info("📝 Champ img mis à jour");
                }
                
                if (isset($result['light']) && isset($result['dark'])) {
                    $this->info("📁 Logos light et dark téléchargés");
                } elseif (isset($result['light'])) {
                    $this->info("📁 Logo light téléchargé");
                } elseif (isset($result['dark'])) {
                    $this->info("📁 Logo dark téléchargé");
                }
                
                return 0;
            } else {
                $this->error("❌ Échec du téléchargement des logos pour la ligue '{$league->name}'");
                return 1;
            }
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("Ligue avec l'ID {$leagueId} introuvable.");
            return 1;
        } catch (\Exception $e) {
            $this->error("Erreur lors du téléchargement: {$e->getMessage()}");
            return 1;
        }
    }
}