<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\League;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

class ExtractMarketForAllEvents extends Command
{
    protected $signature = 'bet:extract-market-all {--dry-run} {--show-missing}';
    protected $description = 'Traite tous les events en base et extrait le market + les équipes associées.';

    public function handle()
    {
        // $events = Event::all();
        $events = Event::where(function ($q) {
            $q->whereNull('team1_id')
                ->orWhereNull('team2_id');
        })->get();
        $results = [];
        $showMissing = $this->option('show-missing');
        $dryRun = $this->option('dry-run');
        $total = count($events);
        $updatedCount = 0;
        $notFoundCount = 0;
        $logPrefix = $dryRun ? '[DRY RUN]' : '[UPDATE]';
        $this->info("Début du traitement de $total events...");
        $normalize = function ($str) {
            return strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $str));
        };
        foreach ($events as $event) {
            $text = $event->market;
            $leagueId = $event->league_id;

            // Extraction de la partie équipes
            $pattern = '/(?:[\p{So}\p{Sk}\p{Mn}\p{Me}\p{Cf}\p{P}\p{S}\p{Z}\p{C}]*\s*)?([\p{L}0-9 .\-]+)\s+vs\s+([\p{L}0-9 .\-]+)/u';
            if (preg_match($pattern, $text, $matches)) {
                $team1Name = trim($matches[1]);
                $team2Name = trim($matches[2]);
            } else {
                $team1Name = null;
                $team2Name = null;
            }

            // Recherche des équipes dans la ligue avec priorité > 0
            $teamsQuery = Team::query();
            if ($leagueId) {
                $teamsQuery->whereHas('leagues', function ($q) use ($leagueId) {
                    $q->where('leagues.id', $leagueId);
                });
            } else {
                $leagueIds = League::where('priority', '>', 0)->pluck('id')->toArray();
                $teamsQuery->whereHas('leagues', function ($q) use ($leagueIds) {
                    $q->whereIn('leagues.id', $leagueIds);
                });
            }

            $team1 = null;
            $team2 = null;

            if ($team1Name) {
                $team1Norm = $normalize($team1Name);
                $teams = $teamsQuery->get();
                // 1. Correspondance sur tous les mots du nom recherché dans name ou nickname
                $team1Words = preg_split('/\s+/', $team1Norm);
                foreach ($teams as $team) {
                    foreach ([$team->name, $team->nickname] as $name) {
                        $nameNorm = $normalize($name);
                        $foundAll = true;
                        foreach ($team1Words as $word) {
                            if (empty($word)) continue;
                            if (strpos($nameNorm, $word) === false) {
                                $foundAll = false;
                                break;
                            }
                        }
                        if ($foundAll) {
                            $team1 = $team;
                            break 2;
                        }
                    }
                }
                // Suppression de la correspondance sur les 5 premiers caractères
            }
            if ($team2Name) {
                $team2Norm = $normalize($team2Name);
                $teams = $teamsQuery->get();
                // 1. Correspondance sur tous les mots du nom recherché dans name ou nickname
                $team2Words = preg_split('/\s+/', $team2Norm);
                foreach ($teams as $team) {
                    foreach ([$team->name, $team->nickname] as $name) {
                        $nameNorm = $normalize($name);
                        $foundAll = true;
                        foreach ($team2Words as $word) {
                            if (empty($word)) continue;
                            if (strpos($nameNorm, $word) === false) {
                                $foundAll = false;
                                break;
                            }
                        }
                        if ($foundAll) {
                            $team2 = $team;
                            break 2;
                        }
                    }
                }
                // Suppression de la correspondance sur les 5 premiers caractères
            }

            // Log de la recherche
            /* $this->line("$logPrefix Event #{$event->id} : {$team1Name} vs {$team2Name}");

            if ($team1) {
                $this->line("  → Équipe 1 trouvée : {$team1->name} (ID: {$team1->id})");
            } else {
                $this->line("  → Équipe 1 NON trouvée");
            }
            if ($team2) {
                $this->line("  → Équipe 2 trouvée : {$team2->name} (ID: {$team2->id})");
            } else {
                $this->line("  → Équipe 2 NON trouvée");
            } */

            // Mise à jour des champs uniquement si au moins une équipe trouvée et pas en dry-run
            $wasUpdated = false;
            if (!$dryRun && ($team1 || $team2)) {
                if ($team1) $event->team1_id = $team1->id;
                if ($team2) $event->team2_id = $team2->id;
                // Si les deux équipes sont trouvées et dans la même ligue, renseigner league_id
                if ($team1 && $team2 && $team1->league_id === $team2->league_id) {
                    $event->league_id = $team1->league_id;
                    //$this->line("  → Ligue renseignée : {$team1->league_id}");
                }
                $event->save();
                $wasUpdated = true;
                $updatedCount++;
                //$this->line("  → Event mis à jour");
            }
            if (!$team1 || !$team2) {
                $notFoundCount++;
            }

            $result = [
                'event_id' => $event->id,
                'market' => $text,
                'team1_name' => $team1Name,
                'team2_name' => $team2Name,
                'team1_id' => $team1 ? $team1->id : null,
                'team2_id' => $team2 ? $team2->id : null,
                'updated' => $wasUpdated
            ];
            if ($showMissing) {
                if (!$result['team1_id'] || !$result['team2_id']) {
                    $results[] = $result;
                }
            } else {
                $results[] = $result;
            }
        }
        $this->info("Traitement terminé. Events mis à jour : $updatedCount / $total. Events incomplets : $notFoundCount.");
        $this->info('Extraction terminée pour tous les events :');
        $this->line(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return 0;
    }
}
