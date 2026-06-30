<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Génère la liste de toutes les URLs Sofascore à fetcher via navigateur.
 *
 * Workflow :
 *   1. php artisan tennis:generate-fetch-list [--force] [--with-images] [--with-stats]
 *      → écrit storage/app/sofascore_cache/urls_to_fetch_script.js
 *
 *   2. Claude in Chrome exécute le script JS → fetch tous les tournois + events du jour
 *      → écrit source_scheduled_{date}.json (events combinés)
 *      → écrit player_details_*.json pour chaque joueur manquant
 *
 *   3. php artisan tennis:import-from-schedule --offline
 *      → lit uniquement le cache, zéro appel réseau
 */
class GenerateTennisFetchList extends Command
{
    protected $signature = 'tennis:generate-fetch-list
                                {--force : Inclure les URLs déjà en cache}
                                {--with-images : Inclure les URLs des images joueurs}
                                {--with-stats : Inclure les URLs des statistiques annuelles}
                                {--date-offset=0 : Décalage en jours (0=aujourd\'hui, 1=demain)}';

    protected $description = 'Génère le script JS Chrome pour fetcher les données Sofascore (contournement 403 PHP)';

    private string $cacheDirectory;

    public function handle(): int
    {
        $force      = $this->option('force');
        $withImages = $this->option('with-images');
        $withStats  = $this->option('with-stats');
        $dateOffset = (int) $this->option('date-offset');
        $date       = date('Y-m-d', strtotime("+{$dateOffset} days"));

        $this->cacheDirectory = storage_path('app/sofascore_cache/tennis_players');

        $scheduledSourcePath = storage_path("app/sofascore_cache/source_scheduled_{$date}.json");
        $liveSourcePath      = storage_path("app/sofascore_cache/source_live_{$date}.json");

        // ── Détails joueurs manquants ──────────────────────────────────────────
        $playerUrls = [];
        $playerBasicFiles = glob($this->cacheDirectory . '/players/player_basic_*.json') ?: [];
        $this->line("📋 Joueurs en cache basique: " . count($playerBasicFiles));

        foreach ($playerBasicFiles as $basicFile) {
            preg_match('/player_basic_(\d+)\.json$/', $basicFile, $m);
            if (empty($m[1])) continue;
            $id = (int) $m[1];

            $detailsFile = $this->cacheDirectory . '/players/player_details_' . $id . '.json';
            $metaFile    = $this->cacheDirectory . '/metadata/' . md5("player_details_{$id}") . '.meta';
            $statsFile   = $this->cacheDirectory . '/players/statistics/player_statistics_' . $id . '.json';
            $imageFile   = $this->cacheDirectory . '/players/logos/' . $id . '.png';

            $basic = json_decode(file_get_contents($basicFile), true) ?? [];
            $name  = $basic['name'] ?? "ID:{$id}";

            // Tombstone actif → skip (sauf --force)
            if (!$force && file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true) ?? [];
                if (!empty($meta['negative_cache']) && (time() - ($meta['timestamp'] ?? 0)) < 86400) {
                    continue;
                }
            }

            if ($force || !file_exists($detailsFile) || filesize($detailsFile) < 10) {
                $playerUrls[] = [
                    'url'           => "https://www.sofascore.com/api/v1/team/{$id}",
                    'save_path'     => $detailsFile,
                    'meta_path'     => $metaFile,
                    'meta_payload'  => ['timestamp' => time(), 'player_id' => $id, 'player_name' => $name],
                    'type'          => 'player_details',
                    'player_id'     => $id,
                    'player_name'   => $name,
                    'response_type' => 'json',
                ];
            }

            if ($withStats) {
                $month     = (int) date('n');
                $year      = (int) date('Y', strtotime("+{$dateOffset} days"));
                $statsYear = ($month <= 2) ? $year - 1 : $year;
                if ($force || !file_exists($statsFile) || filesize($statsFile) < 10) {
                    $playerUrls[] = [
                        'url'           => "https://www.sofascore.com/api/v1/team/{$id}/year-statistics/{$statsYear}",
                        'save_path'     => $statsFile,
                        'type'          => 'player_stats',
                        'player_id'     => $id,
                        'player_name'   => $name,
                        'response_type' => 'json',
                    ];
                }
            }

            if ($withImages) {
                $imageMeta = $this->cacheDirectory . '/metadata/player_image_' . $id . '.meta';
                if (!$force && file_exists($imageMeta)) {
                    $m2 = json_decode(file_get_contents($imageMeta), true) ?? [];
                    if (!empty($m2['negative_cache']) && (time() - ($m2['timestamp'] ?? 0)) < 86400) {
                        continue;
                    }
                }
                if ($force || !file_exists($imageFile) || filesize($imageFile) === 0) {
                    $playerUrls[] = [
                        'url'           => "https://api.sofascore.com/api/v1/team/{$id}/image",
                        'save_path'     => $imageFile,
                        'meta_path'     => $imageMeta,
                        'type'          => 'player_image',
                        'player_id'     => $id,
                        'player_name'   => $name,
                        'response_type' => 'binary',
                    ];
                }
            }
        }

        // ── Génération du script JS Chrome ────────────────────────────────────
        $outputJs = storage_path('app/sofascore_cache/urls_to_fetch_script.js');
        $this->writeJsScript($date, $scheduledSourcePath, $liveSourcePath, $playerUrls, $outputJs, $force);

        $this->line('');
        $this->info("✅ Script généré : {$outputJs}");
        $this->line('');
        $this->line("   Étapes Chrome à faire :");
        $this->line("   1. Fetch tournois du jour ({$date}) + events → source_scheduled_{$date}.json");
        $this->line("   2. Fetch live events → source_live_{$date}.json");
        if (!empty($playerUrls)) {
            $counts = array_count_values(array_column($playerUrls, 'type'));
            foreach ($counts as $type => $count) {
                $this->line("   3. {$type}: {$count} URL(s)");
            }
        }
        $this->line('');
        $this->comment('Puis : php artisan tennis:import-from-schedule --offline --download-images');

        return 0;
    }

    private function writeJsScript(string $date, string $scheduledPath, string $livePath, array $playerUrls, string $outputPath, bool $force): void
    {
        $playerUrlsJson = json_encode($playerUrls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $staticPart = <<<'JS'

// ── Helpers ────────────────────────────────────────────────────────────────

async function apiFetch(url) {
    const resp = await fetch(url, {
        headers: { 'Accept': 'application/json', 'Referer': 'https://www.sofascore.com/' },
        credentials: 'include',
    });
    if (!resp.ok) throw new Error('HTTP ' + resp.status + ' — ' + url);
    return resp.json();
}

// ── Étape 1 : Tournois programmés du jour ──────────────────────────────────

async function fetchScheduledEvents(date, savePath) {
    console.log('[1/3] Récupération des tournois programmés pour ' + date + '...');
    const listData = await apiFetch('https://www.sofascore.com/api/v1/sport/tennis/scheduled-tournaments/' + date + '/page/1');
    const scheduled = listData.scheduled || [];

    // Dédupliquer les IDs de tournois
    const seen = new Set();
    const uniqueIds = [];
    for (const s of scheduled) {
        const id = s.tournament?.uniqueTournament?.id;
        if (id && !seen.has(id)) { seen.add(id); uniqueIds.push(id); }
    }
    console.log('[1/3] ' + uniqueIds.length + ' tournois uniques trouvés');

    // Fetch events par tournoi (batch de 10)
    const allEvents = [];
    const batchSize = 10;
    for (let i = 0; i < uniqueIds.length; i += batchSize) {
        const batch = uniqueIds.slice(i, i + batchSize);
        const results = await Promise.allSettled(batch.map(id =>
            apiFetch('https://www.sofascore.com/api/v1/unique-tournament/' + id + '/scheduled-events/' + date)
        ));
        for (const r of results) {
            if (r.status === 'fulfilled') {
                const events = r.value.events || [];
                allEvents.push(...events);
            }
        }
        console.log('[1/3] Batch tournois ' + Math.min(i + batchSize, uniqueIds.length) + '/' + uniqueIds.length);
    }

    console.log('[1/3] Total events combinés: ' + allEvents.length);
    return { events: allEvents, save_path: savePath };
}

// ── Étape 2 : Events live ──────────────────────────────────────────────────

async function fetchLiveEvents(savePath) {
    console.log('[2/3] Récupération des events live...');
    const data = await apiFetch('https://www.sofascore.com/api/v1/sport/tennis/events/live');
    console.log('[2/3] ' + (data.events?.length || 0) + ' events live');
    return { events: data.events || [], save_path: savePath };
}

// ── Étape 3 : Détails joueurs ──────────────────────────────────────────────

async function fetchPlayerUrls(playerUrls) {
    if (!playerUrls.length) { console.log('[3/3] Aucun joueur à fetcher'); return []; }
    console.log('[3/3] Fetch de ' + playerUrls.length + ' URLs joueurs...');
    const results = [];
    const batchSize = 5;
    for (let i = 0; i < playerUrls.length; i += batchSize) {
        const batch = playerUrls.slice(i, i + batchSize);
        const batchResults = await Promise.allSettled(batch.map(async item => {
            const resp = await fetch(item.url, {
                headers: { 'Accept': item.response_type === 'binary' ? 'image/png,image/*' : 'application/json', 'Referer': 'https://www.sofascore.com/' },
                credentials: 'include',
            });
            if (!resp.ok) return { ok: false, status: resp.status, url: item.url, player_id: item.player_id };
            if (item.response_type === 'binary') {
                const blob = await resp.blob();
                const reader = new FileReader();
                const b64 = await new Promise(r => { reader.onload = () => r(reader.result); reader.readAsDataURL(blob); });
                return { ok: true, type: item.type, player_id: item.player_id, save_path: item.save_path, response_type: 'binary', data_base64: b64 };
            }
            const json = await resp.json();
            return { ok: true, type: item.type, player_id: item.player_id, save_path: item.save_path, meta_path: item.meta_path || null, meta_payload: item.meta_payload || null, response_type: 'json', data: json };
        }));
        for (const r of batchResults) {
            results.push(r.status === 'fulfilled' ? r.value : { ok: false, error: r.reason?.message });
        }
        console.log('[3/3] ' + Math.min(i + batchSize, playerUrls.length) + '/' + playerUrls.length + ' joueurs traités');
    }
    return results;
}

// ── Main ───────────────────────────────────────────────────────────────────

const [scheduledResult, liveResult] = await Promise.all([
    fetchScheduledEvents(DATE, SCHEDULED_PATH),
    fetchLiveEvents(LIVE_PATH),
]);
const playerResults = await fetchPlayerUrls(PLAYER_URLS);

const ok   = playerResults.filter(r => r.ok).length;
const ko   = playerResults.filter(r => !r.ok).length;

console.log('=== RÉSUMÉ ===');
console.log('Events programmés: ' + scheduledResult.events.length);
console.log('Events live: ' + liveResult.events.length);
console.log('Joueurs OK: ' + ok + ', erreurs: ' + ko);

JSON.stringify({
    scheduled_events: scheduledResult.events.length,
    live_events: liveResult.events.length,
    scheduled_save_path: scheduledResult.save_path,
    live_save_path: liveResult.save_path,
    scheduled_data: scheduledResult,
    live_data: liveResult,
    player_results: playerResults,
    errors: playerResults.filter(r => !r.ok),
});
JS;

        $script = "// Script généré par tennis:generate-fetch-list\n"
            . "// Coller dans Claude in Chrome (javascript_tool) pour contourner le 403 Sofascore\n\n"
            . "const DATE = " . json_encode($date) . ";\n"
            . "const SCHEDULED_PATH = " . json_encode($scheduledPath) . ";\n"
            . "const LIVE_PATH = " . json_encode($livePath) . ";\n"
            . "const PLAYER_URLS = {$playerUrlsJson};\n"
            . $staticPart;

        file_put_contents($outputPath, $script);
    }
}
