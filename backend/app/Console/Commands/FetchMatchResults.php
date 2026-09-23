<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FetchMatchResults extends Command
{
    protected $signature = 'results:fetch
                            {--event-ids= : IDs Sofascore séparés par virgule}
                            {--days=7 : Récupérer les matchs des N derniers jours}
                            {--sport= : Filtrer par sport_id}
                            {--force : Re-fetcher même si déjà en cache}';

    protected $description = 'Fetch les résultats des matchs passés depuis Sofascore via Chrome headless';

    public function handle(): int
    {
        if ($this->option('event-ids')) {
            $eventIds = array_map('intval', explode(',', $this->option('event-ids')));
        } else {
            $eventIds = $this->collectEventIds();
        }

        if (empty($eventIds)) {
            $this->info('Aucun match à fetcher.');
            return 0;
        }

        if (!$this->option('force')) {
            $alreadyCached = DB::table('match_results')
                ->whereIn('sofascore_event_id', $eventIds)
                ->whereNotNull('fetched_at')
                ->pluck('sofascore_event_id')
                ->toArray();

            $eventIds = array_values(array_diff($eventIds, $alreadyCached));
            if ($alreadyCached) {
                $this->line(count($alreadyCached) . ' déjà en cache, skippés.');
            }
        }

        if (empty($eventIds)) {
            $this->info('Tous les matchs sont déjà en cache. Utilisez --force pour re-fetcher.');
            return 0;
        }

        $this->info(count($eventIds) . ' match(s) à fetcher via Chrome headless...');

        $scriptPath = base_path('script/fetch_match_results.py');
        $idsStr     = implode(',', $eventIds);
        $cmd        = "python3 " . escapeshellarg($scriptPath) . " --event-ids " . escapeshellarg($idsStr) . " 2>&1";

        passthru($cmd);

        // Lire les fichiers cache et insérer en DB
        $cacheDir = storage_path('app/sofascore_cache/match_results');
        $inserted = 0;
        $updated  = 0;

        foreach ($eventIds as $eventId) {
            $file = $cacheDir . '/' . $eventId . '.json';
            if (!file_exists($file)) {
                $this->warn("  Cache manquant pour event $eventId");
                continue;
            }

            $data = json_decode(file_get_contents($file), true);
            if (!$data) continue;

            $row = [
                'sofascore_event_id'      => $data['sofascore_event_id'],
                'home_team_sofascore_id'  => $data['home_team_sofascore_id'] ?? null,
                'away_team_sofascore_id'  => $data['away_team_sofascore_id'] ?? null,
                'winner_code'             => $data['winner_code'] ?? null,
                'status_type'             => $data['status_type'] ?? 'unknown',
                'home_sets'               => $data['home_sets'] ?? null,
                'away_sets'               => $data['away_sets'] ?? null,
                'score_json'              => json_encode($data['score_json'] ?? []),
                'stats_json'              => json_encode($data['stats_json'] ?? []),
                'point_by_point_json'     => json_encode($data['point_by_point_json'] ?? []),
                'match_timestamp'         => isset($data['match_timestamp'])
                    ? Carbon::createFromTimestamp($data['match_timestamp'])
                    : null,
                'fetched_at'              => now(),
                'updated_at'              => now(),
            ];

            $exists = DB::table('match_results')->where('sofascore_event_id', $eventId)->exists();
            if ($exists) {
                DB::table('match_results')->where('sofascore_event_id', $eventId)->update($row);
                $updated++;
            } else {
                $row['created_at'] = now();
                DB::table('match_results')->insert($row);
                $inserted++;
            }
        }

        $this->info("✓ $inserted insérés, $updated mis à jour en DB.");
        return 0;
    }

    private function collectEventIds(): array
    {
        $days    = (int) $this->option('days');
        $sportId = $this->option('sport');
        $since   = now()->subDays($days)->toDateString();
        $today   = now()->toDateString();

        $query = DB::table('matches')
            ->whereBetween('match_start_date', [$since, $today])
            ->whereNotNull('event_id')
            ->select('event_id');

        if ($sportId) {
            $query->where('sport_id', $sportId);
        }

        return $query->pluck('event_id')->map(fn($id) => (int)$id)->toArray();
    }
}
