<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TeamLogoService;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;

class DownloadTeamLogos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'team:download-logos {team_id? : ID de l\'équipe spécifique à télécharger} {--force : Force le téléchargement même si le logo existe} {--empty-img : Ne traiter que les équipes avec le champ img vide} {--league= : ID de la ligue pour filtrer les équipes} {--all-league : Télécharger les logos de toutes les équipes de la ligue, même si elles ont déjà un logo} {--delay=1 : Délai en secondes entre chaque requête API (défaut: 1)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Télécharge les logos manquants des équipes depuis l\'API Sofascore. Options: --force pour forcer le téléchargement, --empty-img pour ne traiter que les équipes avec img vide, --league pour filtrer par ID de ligue.';

    /**
     * Execute the console command.
     */
    public function handle(TeamLogoService $logoService)
    {
        $teamId = $this->argument('team_id');
        $delay = (int) $this->option('delay');

        // Afficher les paramètres de configuration
        $this->info('🚀 Début du téléchargement des logos d\'équipes...');
        $this->info("⏱️  Délai configuré: {$delay} seconde(s) entre chaque requête");

        if ($teamId) {
            // Télécharger le logo d'une équipe spécifique
            return $this->downloadSingleTeamLogo($logoService, $teamId, $delay);
        }

        // Récupérer les équipes avec un sofascore_id
        $query = Team::whereNotNull('sofascore_id');

        // Filtrer par champ img vide si l'option --empty-img est activée
        if ($this->option('empty-img')) {
            $query->where(function ($q) {
                $q->whereNull('img')
                    ->orWhere('img', '')
                    ->orWhere('img', 'not like', '%team_logos%');
            });
            $this->info('🔍 Filtrage activé: traitement uniquement des équipes avec champ img vide.');
        }

        // Filtrer par ID de ligue si l'option --league est activée
        if ($this->option('league')) {
            $leagueId = $this->option('league');
            $query->where('league_id', $leagueId);
            $this->info("🏆 Filtrage activé: traitement uniquement des équipes de la ligue ID: {$leagueId}");
        }

        $teams = $query->orderBy('id', 'desc')->get();

        if ($teams->isEmpty()) {
            $this->warn('Aucune équipe avec un sofascore_id trouvée.');
            return 0;
        }

        $this->info("Traitement de {$teams->count()} équipes...");

        $progressBar = $this->output->createProgressBar($teams->count());
        $progressBar->start();

        $stats = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        ];


        foreach ($teams as $team) {
            $this->line("Traitement équipe ID: {$team->id} | Nom: {$team->name} | League: {$team->league_id}");
            if ($this->option('all-league')) {
                $this->line("[ALL-LEAGUE] Téléchargement du logo pour l'équipe: {$team->name}");
                $result = $logoService->ensureTeamLogo($team, (bool)$this->option('force'));
                if ($result) {
                    $this->info("✅ Logo téléchargé pour {$team->name} (ID: {$team->id})");
                    if (isset($team->img_source_url) && $team->img_source_url) {
                        $this->line("Lien source du logo : {$team->img_source_url}");
                    }
                    $stats['success']++;
                } else {
                    $this->error("❌ Échec du téléchargement pour {$team->name} (ID: {$team->id})");
                    $stats['failed']++;
                }
            } else {
                if (!$this->option('force') && $team->img && Storage::disk('public')->exists($team->img)) {
                    $this->line("Logo déjà présent pour {$team->name} (ID: {$team->id}), ignoré.");
                    $stats['skipped']++;
                } else {
                    $this->line("Téléchargement du logo pour {$team->name} (ID: {$team->id})...");
                    $result = $logoService->ensureTeamLogo($team, (bool)$this->option('force'));
                    if ($result) {
                        $this->info("✅ Logo téléchargé pour {$team->name} (ID: {$team->id})");
                        if (isset($team->img_source_url) && $team->img_source_url) {
                            $this->line("Lien source du logo : {$team->img_source_url}");
                        }
                        $stats['success']++;
                    } else {
                        $this->error("❌ Échec du téléchargement pour {$team->name} (ID: {$team->id})");
                        $stats['failed']++;
                    }
                }
            }

            $progressBar->advance();

            if ($delay > 0) {
                $this->line("Pause de {$delay} seconde(s) avant la prochaine requête...");
                sleep($delay);
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
                ['Total', $teams->count()]
            ]
        );

        return 0;
    }

    /**
     * Télécharge le logo d'une équipe spécifique
     *
     * @param TeamLogoService $logoService
     * @param int $teamId
     * @return int
     */
    private function downloadSingleTeamLogo(TeamLogoService $logoService, int $teamId, int $delay = 1): int
    {
        $this->info("Téléchargement du logo pour l'équipe ID: {$teamId}");

        try {
            $team = Team::findOrFail($teamId);

            if (!$team->sofascore_id) {
                $this->error("L'équipe '{$team->name}' n'a pas de sofascore_id défini.");
                return 1;
            }

            // Vérifier si le logo existe déjà (sauf si --force)
            if (!$this->option('force') && $team->img && Storage::disk('public')->exists($team->img)) {
                $this->info("Le logo de l'équipe '{$team->name}' existe déjà. Utilisez --force pour forcer le téléchargement.");
                return 0;
            }

            $result = $logoService->ensureTeamLogo($team);

            if ($result) {
                $this->info("✅ Logo téléchargé avec succès pour l'équipe '{$team->name}'");
                $this->info("📁 Chemin: {$result}");
                return 0;
            } else {
                $this->error("❌ Échec du téléchargement du logo pour l'équipe '{$team->name}'");
                return 1;
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("Équipe avec l'ID {$teamId} introuvable.");
            return 1;
        } catch (\Exception $e) {
            $this->error("Erreur lors du téléchargement: {$e->getMessage()}");
            return 1;
        }
    }
}
