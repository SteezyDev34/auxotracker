<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Team;

class SyncLeagueTeamPivot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'league-team:sync {--rebuild : Supprime et reconstruit la table pivot}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise la table pivot league_team à partir de teams.league_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔁 Synchronisation de la table pivot league_team');

        if ($this->option('rebuild')) {
            $this->warn('⚠️  Option --rebuild activée : la table pivot sera vidée avant reconstruction.');
            DB::table('league_team')->truncate();
        }

        $countInserted = 0;

        Team::whereNotNull('league_id')->chunk(200, function ($teams) use (&$countInserted) {
            $inserts = [];
            $now = now();
            foreach ($teams as $team) {
                $inserts[] = [
                    'league_id' => $team->league_id,
                    'team_id' => $team->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($inserts)) {
                // Use insertOrIgnore to avoid duplicate key errors
                $inserted = DB::table('league_team')->insertOrIgnore($inserts);
                $countInserted += is_int($inserted) ? $inserted : count($inserts);
            }
        });

        $this->info("✅ Synchronisation terminée. Entrées approximatives insérées: {$countInserted}");
        return Command::SUCCESS;
    }
}
