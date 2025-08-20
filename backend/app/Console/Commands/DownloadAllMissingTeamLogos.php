<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TeamLogoService;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;

class DownloadAllMissingTeamLogos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'team:download-all-missing-logos {--limit=100 : Limite du nombre d\'équipes à traiter} {--delay=2 : Délai en secondes entre chaque requête API} {--force : Force le téléchargement même si le logo existe}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Télécharge les logos manquants pour toutes les équipes qui n\'ont pas d\'image enregistrée, avec une limite configurable.';

    /**
     * Execute the console command.
     */
    public function handle(TeamLogoService $logoService)
    {
        $limit = (int) $this->option('limit');
        $delay = (int) $this->option('delay');
        $force = $this->option('force');
        
        // Afficher les paramètres de configuration
        $this->info('🚀 Début du téléchargement des logos manquants...');
        $this->info("📊 Limite configurée: {$limit} équipes maximum");
        $this->info("⏱️  Délai configuré: {$delay} seconde(s) entre chaque requête");
        $this->info("🔄 Mode forcé: " . ($force ? 'Activé' : 'Désactivé'));
        $this->newLine();
        
        // Construire la requête pour les équipes sans logo
        $query = Team::whereNotNull('sofascore_id');
        
        if (!$force) {
            // Ne traiter que les équipes sans image ou avec un chemin d'image invalide
            $query->where(function($q) {
                $q->whereNull('img')
                  ->orWhere('img', '')
                  ->orWhere('img', 'not like', '%team_logos%');
            });
        }
        
        // Appliquer la limite
        $teams = $query->orderBy('id', 'desc')->limit($limit)->get();
        
        if ($teams->isEmpty()) {
            $this->warn('Aucune équipe sans logo trouvée.');
            return 0;
        }
        
        $this->info("🔍 {$teams->count()} équipes trouvées pour le traitement.");
        $this->newLine();
        
        // Demander confirmation si plus de 50 équipes
        if ($teams->count() > 50 && !$this->confirm("Voulez-vous vraiment traiter {$teams->count()} équipes ?")) {
            $this->info('Opération annulée.');
            return 0;
        }
        
        $progressBar = $this->output->createProgressBar($teams->count());
        $progressBar->setFormat('debug');
        $progressBar->start();
        
        $stats = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'already_exists' => 0
        ];
        
        foreach ($teams as $index => $team) {
            // Vérifier si le logo existe déjà (sauf si --force)
            if (!$force && $team->img && Storage::disk('public')->exists($team->img)) {
                $stats['already_exists']++;
                $progressBar->advance();
                continue;
            }
            
            try {
                $result = $logoService->ensureTeamLogo($team);
                
                if ($result) {
                    $stats['success']++;
                    $progressBar->setMessage("✅ {$team->name}", 'status');
                } else {
                    $stats['failed']++;
                    $progressBar->setMessage("❌ {$team->name}", 'status');
                }
                
            } catch (\Exception $e) {
                $stats['failed']++;
                $progressBar->setMessage("💥 {$team->name} - Erreur: {$e->getMessage()}", 'status');
            }
            
            $progressBar->advance();
            
            // Pause configurable pour éviter de surcharger l'API et les erreurs 403
            if ($delay > 0 && $index < $teams->count() - 1) {
                sleep($delay);
            }
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        // Afficher les statistiques finales
        $this->info('📈 Téléchargement terminé!');
        $this->table(
            ['Statut', 'Nombre', 'Pourcentage'],
            [
                ['✅ Succès', $stats['success'], $this->getPercentage($stats['success'], $teams->count())],
                ['❌ Échecs', $stats['failed'], $this->getPercentage($stats['failed'], $teams->count())],
                ['⏭️  Ignorés', $stats['skipped'], $this->getPercentage($stats['skipped'], $teams->count())],
                ['📁 Déjà existants', $stats['already_exists'], $this->getPercentage($stats['already_exists'], $teams->count())],
                ['📊 Total traité', $teams->count(), '100%']
            ]
        );
        
        // Afficher un résumé coloré
        if ($stats['success'] > 0) {
            $this->info("🎉 {$stats['success']} logos téléchargés avec succès!");
        }
        
        if ($stats['failed'] > 0) {
            $this->warn("⚠️  {$stats['failed']} échecs de téléchargement.");
        }
        
        return $stats['failed'] > 0 ? 1 : 0;
    }
    
    /**
     * Calcule le pourcentage pour les statistiques
     * 
     * @param int $value
     * @param int $total
     * @return string
     */
    private function getPercentage(int $value, int $total): string
    {
        if ($total === 0) {
            return '0%';
        }
        
        return round(($value / $total) * 100, 1) . '%';
    }
}