<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ResolveBets extends Command
{
    protected $signature = 'bets:resolve
                            {--bet-ids= : IDs de paris séparés par virgule}
                            {--days=30 : Résoudre les paris des N derniers jours}
                            {--dry-run : Afficher sans modifier la DB}
                            {--ai : Forcer l\'utilisation de l\'IA pour tous les paris}
                            {--no-ai : Désactiver l\'IA (règles uniquement)}';

    protected $description = 'Résout automatiquement les paris pending en récupérant les résultats Sofascore';

    private bool $dryRun;
    private bool $useAI;

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');
        $this->useAI  = !$this->option('no-ai');

        if ($this->dryRun) {
            $this->warn('Mode DRY-RUN : aucune modification en DB.');
        }

        $bets = $this->loadPendingBets();

        if ($bets->isEmpty()) {
            $this->info('Aucun pari pending à résoudre.');
            return 0;
        }

        $this->info(count($bets) . ' pari(s) pending trouvé(s).');

        $resolved = 0;
        $skipped  = 0;
        $aiUsed   = 0;

        foreach ($bets as $bet) {
            $this->line("\n→ Bet #{$bet->id} | {$bet->bet_date} | {$bet->market}");

            // Trouver le résultat du match
            $result = $this->findMatchResult($bet);

            if (!$result) {
                $this->warn("  Résultat non trouvé, skip.");
                $skipped++;
                continue;
            }

            $this->line("  Match: {$result->home_sets}-{$result->away_sets} | Winner: " . ($result->winner_code == 1 ? 'home' : 'away'));

            // Tentative de résolution automatique
            $outcome = $this->resolveAuto($bet, $result);

            // Si indéterminable automatiquement → IA
            if ($outcome === null && $this->useAI && !$this->option('no-ai')) {
                $this->line("  → Résolution IA...");
                $outcome = $this->resolveWithAI($bet, $result);
                if ($outcome) $aiUsed++;
            }

            if ($outcome === null) {
                $this->warn("  Impossible de déterminer le résultat.");
                $skipped++;
                continue;
            }

            $this->line("  ✓ Résultat: {$outcome}");

            if (!$this->dryRun) {
                // La DB utilise 'win' (pas 'won') pour les paris gagnés
                $dbOutcome = $outcome === 'won' ? 'win' : $outcome;
                DB::table('bets')->where('id', $bet->id)->update([
                    'result'     => $dbOutcome,
                    'updated_at' => now(),
                ]);
            }

            $resolved++;
        }

        $this->newLine();
        $this->info("✓ $resolved résolu(s), $skipped skippé(s), $aiUsed via IA.");
        return 0;
    }

    private function loadPendingBets()
    {
        $days  = (int) $this->option('days');
        $since = now()->subDays($days)->toDateString();

        $query = DB::table('bets as b')
            ->join('bet_event as be', 'be.bet_id', '=', 'b.id')
            ->join('events as e', 'e.id', '=', 'be.event_id')
            ->leftJoin('teams as t1', 't1.id', '=', 'e.team1_id')
            ->leftJoin('teams as t2', 't2.id', '=', 'e.team2_id')
            ->where('b.result', 'pending')
            ->where('b.bet_date', '>=', $since)
            ->select([
                'b.id', 'b.bet_date', 'b.global_odds', 'b.sport_id',
                'e.id as event_id', 'e.type', 'e.market', 'e.odd', 'e.event_date',
                'e.sofascore_event_id',
                't1.id as team1_id', 't1.name as team1_name',
                't1.sofascore_id as team1_sofascore_id',
                't2.id as team2_id', 't2.name as team2_name',
                't2.sofascore_id as team2_sofascore_id',
            ]);

        if ($this->option('bet-ids')) {
            $ids = array_map('intval', explode(',', $this->option('bet-ids')));
            $query->whereIn('b.id', $ids);
        } else {
            // En mode automatique : seulement les paris dont le match est terminé depuis 3h+
            $query->where('b.bet_date', '<', now()->subHours(3));
        }

        return $query->get();
    }

    private function findMatchResult(object $bet): ?object
    {
        // 1. Via sofascore_event_id direct sur l'event
        if (!empty($bet->sofascore_event_id)) {
            $r = DB::table('match_results')->where('sofascore_event_id', $bet->sofascore_event_id)->first();
            if ($r) return $r;
        }

        // 2. Via la table matches (cherche par équipes + date)
        $date = Carbon::parse($bet->event_date ?: $bet->bet_date)->toDateString();

        if ($bet->team1_sofascore_id && $bet->team2_sofascore_id) {
            $match = DB::table('matches')
                ->where('match_start_date', $date)
                ->where(function ($q) use ($bet) {
                    $q->where(function ($q2) use ($bet) {
                        $q2->where('team_1_sofascore_id', $bet->team1_sofascore_id)
                           ->where('team_2_sofascore_id', $bet->team2_sofascore_id);
                    })->orWhere(function ($q2) use ($bet) {
                        $q2->where('team_1_sofascore_id', $bet->team2_sofascore_id)
                           ->where('team_2_sofascore_id', $bet->team1_sofascore_id);
                    });
                })
                ->first();

            if ($match) {
                return DB::table('match_results')
                    ->where('sofascore_event_id', $match->event_id)
                    ->first();
            }
        }

        return null;
    }

    /**
     * Résolution automatique par règles.
     * Retourne 'won', 'lost', 'void', ou null si indéterminable.
     */
    private function resolveAuto(object $bet, object $result): ?string
    {
        if ($result->status_type !== 'finished') {
            return $result->status_type === 'cancelled' ? 'void' : null;
        }

        $market  = mb_strtolower($bet->market . ' ' . $bet->type);
        $score   = json_decode($result->score_json, true);
        $home    = $score['homeScore'] ?? [];
        $away    = $score['awayScore'] ?? [];
        $winner  = (int) $result->winner_code; // 1=home, 2=away

        // Déterminer qui est home/away par rapport aux équipes du pari
        $team1IsHome = ($bet->team1_sofascore_id == $result->home_team_sofascore_id);

        // ── Victoire simple ──────────────────────────────────────────────
        // Génère les variantes de nom à chercher (nom complet + nom de famille seul)
        $team1Names = $bet->team1_name
            ? array_unique([mb_strtolower($bet->team1_name), mb_strtolower(last(explode(' ', $bet->team1_name)))])
            : [];
        $team2Names = $bet->team2_name
            ? array_unique([mb_strtolower($bet->team2_name), mb_strtolower(last(explode(' ', $bet->team2_name)))])
            : [];

        foreach (['victoire', 'gagnant', 'winner', 'win', 'bat '] as $kw) {
            if (str_contains($market, $kw)) {
                foreach ($team1Names as $n) {
                    if (str_contains($market, $n)) {
                        $team1Wins = $team1IsHome ? ($winner === 1) : ($winner === 2);
                        return $team1Wins ? 'won' : 'lost';
                    }
                }
                foreach ($team2Names as $n) {
                    if (str_contains($market, $n)) {
                        $team2Wins = $team1IsHome ? ($winner === 2) : ($winner === 1);
                        return $team2Wins ? 'won' : 'lost';
                    }
                }
            }
        }

        // ── Fallback : si un seul nom est dans le market (sans mot-clé victoire) ──
        foreach ($team1Names as $n) {
            if (strlen($n) > 3 && str_contains($market, $n)) {
                $team1Wins = $team1IsHome ? ($winner === 1) : ($winner === 2);
                return $team1Wins ? 'won' : 'lost';
            }
        }
        foreach ($team2Names as $n) {
            if (strlen($n) > 3 && str_contains($market, $n)) {
                $team2Wins = $team1IsHome ? ($winner === 2) : ($winner === 1);
                return $team2Wins ? 'won' : 'lost';
            }
        }

        // ── BTTS (les deux équipes marquent) ────────────────────────────
        if (str_contains($market, 'deux equipes') || str_contains($market, 'btts') || str_contains($market, 'les deux marquent')) {
            $homeGoals = $home['current'] ?? 0;
            $awayGoals = $away['current'] ?? 0;
            $btts = $homeGoals > 0 && $awayGoals > 0;
            if (str_contains($market, 'non') || str_contains($market, 'no')) {
                return $btts ? 'lost' : 'won';
            }
            return $btts ? 'won' : 'lost';
        }

        // ── Over/Under buts/jeux/sets ────────────────────────────────────
        if (preg_match('/(?:plus|over|more|>\s*|more than)\s*([\d.]+)\s*(but|jeux|games|sets?|points?)?/u', $market, $m)
         || preg_match('/([\d.]+)\s*\+/u', $market, $m)) {
            $line   = (float) $m[1];
            $total  = ($home['current'] ?? 0) + ($away['current'] ?? 0);
            // Pour tennis : total des jeux = somme des périodes
            if (isset($home['period1'])) {
                $total = ($home['period1'] ?? 0) + ($away['period1'] ?? 0)
                       + ($home['period2'] ?? 0) + ($away['period2'] ?? 0)
                       + ($home['period3'] ?? 0) + ($away['period3'] ?? 0)
                       + ($home['period4'] ?? 0) + ($away['period4'] ?? 0)
                       + ($home['period5'] ?? 0) + ($away['period5'] ?? 0);
            }
            $isOver = str_contains($market, 'plus') || str_contains($market, 'over') || str_contains($market, '>');
            if ($total == $line) return 'void';
            return ($isOver ? $total > $line : $total < $line) ? 'won' : 'lost';
        }

        if (preg_match('/(?:moins|under|<\s*)([\d.]+)/u', $market, $m)) {
            $line  = (float) $m[1];
            $total = ($home['current'] ?? 0) + ($away['current'] ?? 0);
            if (isset($home['period1'])) {
                $total = ($home['period1'] ?? 0) + ($away['period1'] ?? 0)
                       + ($home['period2'] ?? 0) + ($away['period2'] ?? 0)
                       + ($home['period3'] ?? 0) + ($away['period3'] ?? 0);
            }
            if ($total == $line) return 'void';
            return $total < $line ? 'won' : 'lost';
        }

        // ── Nul (draw) ───────────────────────────────────────────────────
        if (str_contains($market, 'nul') || str_contains($market, 'draw')) {
            $isDraw = ($winner === 3 || ($home['current'] ?? -1) === ($away['current'] ?? -2));
            // Gestion "remboursé si nul" : si nul → void
            if (str_contains($market, 'rembours')) {
                return $isDraw ? 'void' : null; // laisse l'IA trancher la partie principale
            }
            return $isDraw ? 'won' : 'lost';
        }

        // Indéterminable par règles
        return null;
    }

    /**
     * Résolution via Claude API pour les cas complexes.
     */
    private function resolveWithAI(object $bet, object $result): ?string
    {
        $apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            $this->warn('  ANTHROPIC_API_KEY non configuré.');
            return null;
        }

        $score   = json_decode($result->score_json, true);
        $stats   = json_decode($result->stats_json, true);
        $home    = $score['homeScore'] ?? [];
        $away    = $score['awayScore'] ?? [];

        // Résumé des stats ALL pour l'IA
        $statsSummary = '';
        if ($stats) {
            $allPeriod = collect($stats)->firstWhere('period', 'ALL');
            if ($allPeriod) {
                foreach ($allPeriod['groups'] ?? [] as $group) {
                    $statsSummary .= "\n{$group['groupName']}: ";
                    foreach ($group['statisticsItems'] ?? [] as $item) {
                        $statsSummary .= "{$item['name']} ({$item['home']} / {$item['away']}), ";
                    }
                }
            }
        }

        $homeTeamName = $team1IsHome ? ($bet->team1_name ?? '?') : ($bet->team2_name ?? '?');
        $awayTeamName = $team1IsHome ? ($bet->team2_name ?? '?') : ($bet->team1_name ?? '?');
        $winnerName   = match((int)$result->winner_code) {
            1 => $homeTeamName,
            2 => $awayTeamName,
            default => 'Match nul',
        };

        $homeScoreCurrent = $home['current'] ?? '?';
        $awayScoreCurrent = $away['current'] ?? '?';
        $p1h = $home['period1'] ?? '?'; $p1a = $away['period1'] ?? '?';
        $p2h = $home['period2'] ?? '?'; $p2a = $away['period2'] ?? '?';
        $p3h = $home['period3'] ?? '?'; $p3a = $away['period3'] ?? '?';

        $prompt = <<<PROMPT
Tu es un expert en paris sportifs. Analyse ce pari et dis si c'est gagné, perdu ou remboursé.

**Pari:** {$bet->market}
**Type:** {$bet->type}

**Équipes du match:**
- HOME: {$homeTeamName}
- AWAY: {$awayTeamName}

**Résultat du match:**
- Statut: {$result->status_type}
- VAINQUEUR: {$winnerName}
- Score: {$homeTeamName} {$homeScoreCurrent} - {$awayScoreCurrent} {$awayTeamName}
- Détail sets/périodes: {$homeTeamName} {$p1h}/{$p2h}/{$p3h} - {$awayTeamName} {$p1a}/{$p2a}/{$p3a}
{$statsSummary}

Réponds UNIQUEMENT avec un de ces mots: won | lost | void
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 10,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            $text = trim(strtolower($response->json('content.0.text') ?? ''));
            if (in_array($text, ['won', 'lost', 'void'])) {
                return $text;
            }
        } catch (\Exception $e) {
            $this->warn('  Erreur IA: ' . $e->getMessage());
        }

        return null;
    }
}
