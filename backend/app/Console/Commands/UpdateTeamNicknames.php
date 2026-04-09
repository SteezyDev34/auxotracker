<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Exception;

class UpdateTeamNicknames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teams:update:nicknames {--dry-run : Ne pas écrire en base, afficher les changements proposés} {--slug= : Filtrer par slug d\'équipe}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parcourt toutes les équipes et ajoute des nicknames trouvés dans backend/api.json';

    public function handle(): int
    {
        ini_set('memory_limit', '-1');

        $path = base_path('api.json');

        $this->info("Lecture du fichier JSON: {$path}");
        Log::info('UpdateTeamNicknames: lecture du fichier JSON', ['path' => $path]);

        if (!file_exists($path)) {
            $this->error("Fichier JSON introuvable: {$path}");
            Log::error('UpdateTeamNicknames: fichier JSON introuvable', ['path' => $path]);
            return 1;
        }

        try {
            $content = file_get_contents($path);
            $data = json_decode($content, true);
        } catch (Exception $e) {
            $this->error('Erreur lecture JSON: ' . $e->getMessage());
            Log::error('UpdateTeamNicknames: exception lecture JSON', ['exception' => $e]);
            return 1;
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Erreur JSON: ' . json_last_error_msg());
            Log::error('UpdateTeamNicknames: erreur JSON', ['error' => json_last_error_msg()]);
            return 1;
        }

        // Construire des tables utilitaires:
        // - keyToValue: clé JSON normalisée (lower) => valeur (ex: 'italy' => 'Italie')
        // - valueToKeys: valeur normalisée => [keys] (conserve compatibilité pour certaines correspondances)
        $keyToValue = [];
        $valueToKeys = [];
        foreach ($data as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            $k = mb_strtolower(trim((string) $key));
            $v = trim($value);
            if ($k === '' || $v === '') {
                continue;
            }
            $keyToValue[$k] = $v;

            $normV = mb_strtolower($v);
            if (!isset($valueToKeys[$normV])) {
                $valueToKeys[$normV] = [];
            }
            $valueToKeys[$normV][] = $k;
        }


        $filterSlug = $this->option('slug');
        $dryRun = (bool) $this->option('dry-run');

        if ($filterSlug) {
            $teams = Team::where('slug', $filterSlug)->get();
            $this->info("Filtre: slug={$filterSlug} — équipes à traiter: " . $teams->count());
            Log::info('UpdateTeamNicknames: filtre slug', ['slug' => $filterSlug, 'count' => $teams->count()]);
        } else {
            $teams = Team::all();
            $this->info('Aucun filtre — traitement de toutes les équipes: ' . $teams->count());
            Log::info('UpdateTeamNicknames: traitement de toutes les équipes', ['count' => $teams->count()]);
        }

        $this->info('Entrées JSON prises en compte: ' . count($keyToValue));
        Log::info('UpdateTeamNicknames: keyToValue count', ['count' => count($keyToValue)]);

        $updated = 0;

        foreach ($teams as $team) {
            try {
                $candidates = [];

                // Priorité: correspondance par `slug`
                $slug = mb_strtolower(trim((string) $team->slug));
                if ($slug !== '' && isset($keyToValue[$slug])) {
                    $candidates[] = $keyToValue[$slug];
                    Log::debug('Candidate by slug', ['team_id' => $team->id, 'slug' => $slug, 'value' => $keyToValue[$slug]]);
                }

                // correspondance par name
                $nameKey = mb_strtolower(trim((string) $team->name));
                if ($nameKey !== '' && isset($keyToValue[$nameKey])) {
                    $candidates[] = $keyToValue[$nameKey];
                    Log::debug('Candidate by name', ['team_id' => $team->id, 'name' => $team->name, 'value' => $keyToValue[$nameKey]]);
                }

                // vérifier les parties du nickname existant
                if ($team->nickname) {
                    $parts = array_map('trim', explode(',', (string) $team->nickname));
                    foreach ($parts as $p) {
                        $pnorm = mb_strtolower($p);
                        if ($pnorm !== '' && isset($keyToValue[$pnorm])) {
                            $candidates[] = $keyToValue[$pnorm];
                            Log::debug('Candidate by existing nickname part', ['team_id' => $team->id, 'part' => $p, 'value' => $keyToValue[$pnorm]]);
                        }
                    }
                }

                // Nettoyage: valeurs uniques non vides
                $candidates = array_values(array_unique(array_filter(array_map('trim', $candidates), function ($v) {
                    return $v !== '';
                })));

                if (empty($candidates)) {
                    Log::debug('Aucun candidat trouvé pour equipe', ['team_id' => $team->id, 'slug' => $team->slug]);
                    continue;
                }

                $existing = [];
                if ($team->nickname) {
                    $existing = array_filter(array_map('trim', explode(',', (string) $team->nickname)), function ($v) {
                        return $v !== '';
                    });
                }

                $newNickParts = array_values(array_unique(array_merge($existing, $candidates)));
                $newNick = implode(', ', $newNickParts);

                if ($newNick !== (string) $team->nickname) {
                    if ($dryRun) {
                        $this->info("[DRY] Changement prévu: {$team->id} -> {$team->name} ({$team->nickname} => {$newNick})");
                        Log::info('UpdateTeamNicknames: dry-run changement prévu', ['team_id' => $team->id, 'old' => $team->nickname, 'new' => $newNick]);
                    } else {
                        $old = $team->nickname;
                        $team->nickname = $newNick;
                        $team->save();
                        $updated++;
                        $this->info("Mis à jour: {$team->id} -> {$team->name} ({$newNick})");
                        Log::info('UpdateTeamNicknames: equipe mise a jour', ['team_id' => $team->id, 'old' => $old, 'new' => $newNick]);
                    }
                } else {
                    Log::debug('Nickname identique, pas de changement', ['team_id' => $team->id]);
                }
            } catch (Exception $e) {
                $this->error("Erreur traitement équipe {$team->id}: " . $e->getMessage());
                Log::error('UpdateTeamNicknames: exception par equipe', ['team_id' => $team->id, 'exception' => $e]);
            }
        }

        $this->info("Terminé. Équipes mises à jour: {$updated}");
        Log::info('UpdateTeamNicknames: terminé', ['updated' => $updated, 'dry_run' => $dryRun]);
        return 0;
    }
}
