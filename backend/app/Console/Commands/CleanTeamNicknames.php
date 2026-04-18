<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Team;

class CleanTeamNicknames extends Command
{
    protected $signature = 'teams:clean-nicknames {--fix : Appliquer les modifications (sinon dry-run)} {--report= : Chemin pour enregistrer le rapport JSON}';

    protected $description = 'Nettoie et déduit les doublons dans le champ nickname de la table teams.';

    public function handle(): int
    {
        $fix = $this->option('fix');
        $reportPath = $this->option('report');

        $this->info('Recherche des doublons dans `teams.nickname`...');

        $teams = Team::all();
        $changes = [];

        foreach ($teams as $team) {
            $original = $team->nickname ?? '';
            $normalized = $team->normalizedNicknames();
            if (trim($original) !== trim($normalized)) {
                $changes[] = [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'original' => $original,
                    'normalized' => $normalized,
                ];

                $this->line("Team {$team->id} - '{$team->name}': needs normalization");
                $this->line("   original: {$original}");
                $this->line("   normalized: {$normalized}");

                if ($fix) {
                    $team->nickname = $normalized;
                    $team->save();
                    $this->line('   -> applied');
                }
            }
        }

        $this->info('Terminé. ' . count($changes) . ' changement(s) détecté(s).');

        if ($reportPath) {
            try {
                file_put_contents($reportPath, json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->info("Rapport écrit sur {$reportPath}");
            } catch (\Exception $e) {
                $this->error('Impossible d écrire le rapport: ' . $e->getMessage());
            }
        }

        if (!$fix && count($changes) > 0) {
            $this->warn('Dry-run : utilisez --fix pour appliquer les modifications.');
        }

        return Command::SUCCESS;
    }
}
