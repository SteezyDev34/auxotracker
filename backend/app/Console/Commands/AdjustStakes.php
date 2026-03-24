<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserBankroll;
use App\Models\Bet;
use App\Models\Sport;

class AdjustStakes extends Command
{
    protected $signature = 'bankroll:adjust-stakes
                            {bankroll_id : ID de la bankroll à ajuster}
                            {--sport-id= : ID du sport à traiter (optionnel, traite tous les sports si non spécifié)}
                            {--strategy=recovery : Stratégie à utiliser (recovery|simple)}
                            {--dry-run : Simuler sans sauvegarder}';

    protected $description = 'Ajuste les stakes selon différentes stratégies. --strategy=recovery (0.1% + récupération) ou --strategy=simple (0.5% fixe).';

    public function handle()
    {
        $bankrollId = $this->argument('bankroll_id');
        $sportId = $this->option('sport-id');
        $strategy = $this->option('strategy');
        $isDryRun = $this->option('dry-run');

        // Valider la stratégie
        if (!in_array($strategy, ['recovery', 'simple'])) {
            $this->error("Stratégie invalide: {$strategy}. Utilisez 'recovery' ou 'simple'.");
            return 1;
        }

        $bankroll = UserBankroll::find($bankrollId);
        if (!$bankroll) {
            $this->error("Bankroll avec l'ID {$bankrollId} non trouvée.");
            return 1;
        }

        $this->info("Traitement de la bankroll: {$bankroll->bankroll_name}");
        $this->info("Capital initial: {$bankroll->bankroll_start_amount} €");
        $this->info("Stratégie sélectionnée: " . ($strategy === 'recovery' ? 'Récupération (0.1%)' : 'Simple (0.5%)'));

        // Vérifier si le sport existe avant de construire la requête
        if ($sportId) {
            $sport = Sport::find($sportId);
            if (!$sport) {
                $this->warn("Sport avec l'ID {$sportId} non trouvé, traitement de tous les sports.");
                $sportId = null;
            } else {
                $this->info("Filtre par sport: {$sport->name} (ID: {$sportId})");
            }
        } else {
            $this->info("Traitement de tous les sports");
        }

        // Construire la requête des paris
        $query = Bet::where('bankroll_id', $bankrollId);

        if ($sportId) {
            $query->where('sport_id', $sportId);
        }

        if ($isDryRun) {
            $this->warn("Mode simulation activé");
        }

        $bets = $query->orderBy('bet_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($bets->isEmpty()) {
            $message = $sportId ? 'Aucun pari trouvé pour ce sport.' : 'Aucun pari trouvé.';
            $this->warn($message);
            return 0;
        }

        $this->info("Nombre de paris: {$bets->count()}");

        if ($strategy === 'recovery') {
            $this->applyRecoveryStrategy($bets, $bankroll, $isDryRun, $sportId);
        } else {
            $this->applySimpleStrategy($bets, $bankroll, $isDryRun, $sportId);
        }

        $this->info('Terminé !');
        return 0;
    }

    private function applyRecoveryStrategy($bets, $bankroll, $isDryRun, $sportId = null)
    {
        $currentCapital = $bankroll->bankroll_start_amount;
        $lastLostPayout = 0;
        $baseTargetPercentage = 0.01; // 0.1%
        $modifications = 0;

        $this->info("\n=== Stratégie de Récupération Simple ===");
        $this->info("Objectif: 0.1% du capital par pari");
        $this->info("Récupération: (mise × cote) + 0.1%");

        if ($sportId) {
            $this->info("Filtre appliqué sur le sport ID: {$sportId}");
        }
        $this->info("");

        foreach ($bets as $bet) {
            $originalStake = $bet->stake;
            $c = $bet->global_odds;
            $r = $c - 1;

            $baseTargetGain = $currentCapital * $baseTargetPercentage;

            if ($lastLostPayout > 0) {
                $targetGain = $baseTargetGain + $lastLostPayout;
                $mode = 'RÉCUPÉRATION';
            } else {
                $targetGain = $baseTargetGain;
                $mode = 'BASE';
            }

            $newStake = ($r > 0) ? $targetGain / $r : $targetGain;

            $recoveryInfo = '';
            if ($lastLostPayout > 0) {
                $recoveryInfo = sprintf(" | À récupérer: %.2f€", $lastLostPayout);
            }

            $gainInfo = '';
            if ($bet->result === 'win') {
                $potentialNetGain = $newStake * $r;
                $gainInfo = sprintf(" | Gain net: %.2f€", $potentialNetGain);
            }

            // Calculer le bénéfice potentiel
            $potentialGrossPayout = $newStake * $c;
            $potentialBenefit = $potentialGrossPayout - $newStake;

            $this->line(sprintf(
                "Pari #%d - %s | %s | Capital: %.2f€ | Mode: %s%s | Cote: %.2f | Stake: %.2f€ → %.2f€ | Gain potentiel: %.2f€ | Bénéfice: %.2f€ | Résultat: %s%s",
                $bet->id,
                $bet->bet_date->format('Y-m-d'),
                $bet->bet_code ?? 'N/A',
                $currentCapital,
                $mode,
                $recoveryInfo,
                $c,
                $originalStake,
                $newStake,
                $potentialGrossPayout,
                $potentialBenefit,
                $bet->result ?? 'pending',
                $gainInfo
            ));

            if ($bet->result === 'win') {
                $actualGain = $newStake * $r;
                $currentCapital += $actualGain;

                if ($lastLostPayout > 0) {
                    $this->line("    → Perte récupérée !");
                    $lastLostPayout = 0;
                }
            } elseif ($bet->result === 'lost') {
                $currentCapital -= $newStake;
                $lastLostPayout = $newStake * $c;
                $this->line(sprintf("    → Perte à récupérer: %.2f€", $lastLostPayout));
            }

            if (!$isDryRun && abs($originalStake - $newStake) > 0.01) {
                $bet->update(['stake' => round($newStake, 2)]);
                $modifications++;
            }
        }

        if (!$isDryRun) {
            $this->info("\n{$modifications} paris modifiés.");
        }
    }

    private function applySimpleStrategy($bets, $bankroll, $isDryRun, $sportId = null)
    {
        $currentCapital = $bankroll->bankroll_start_amount;
        $targetPercentage = 0.01; // 0.5%
        $modifications = 0;

        $this->info("\n=== Stratégie Simple ===");
        $this->info("Objectif: 0.5% du capital par pari");
        $this->info("Pas de récupération des pertes");

        if ($sportId) {
            $this->info("Filtre appliqué sur le sport ID: {$sportId}");
        }
        $this->info("");

        foreach ($bets as $bet) {
            $originalStake = $bet->stake;
            $c = $bet->global_odds;
            $r = $c - 1; // Gain net par euro misé

            // Calculer la mise pour obtenir 0.5% du capital actuel
            $targetGain = $currentCapital * $targetPercentage;
            $newStake = ($r > 0) ? $targetGain / $r : $targetGain;

            $gainInfo = '';
            if ($bet->result === 'win') {
                $actualGain = $newStake * $r;
                $gainInfo = sprintf(" | Gain net: %.2f€", $actualGain);
            } elseif ($bet->result === 'lost') {
                $gainInfo = sprintf(" | Perte: -%.2f€", $newStake);
            }

            // Calculer le bénéfice potentiel
            $potentialGrossPayout = $newStake * $c;
            $potentialBenefit = $potentialGrossPayout - $newStake;

            $this->line(sprintf(
                "Pari #%d - %s | %s | Capital: %.2f€ | Objectif: %.2f€ (0.5%%) | Cote: %.2f | Stake: %.2f€ → %.2f€ | Gain potentiel: %.2f€ | Bénéfice: %.2f€ | Résultat: %s%s",
                $bet->id,
                $bet->bet_date->format('Y-m-d'),
                $bet->bet_code ?? 'N/A',
                $currentCapital,
                $targetGain,
                $c,
                $originalStake,
                $newStake,
                $potentialGrossPayout,
                $potentialBenefit,
                $bet->result ?? 'pending',
                $gainInfo
            ));

            // Mettre à jour le capital pour le pari suivant
            if ($bet->result === 'win') {
                $actualGain = $newStake * $r;
                $currentCapital += $actualGain;
            } elseif ($bet->result === 'lost') {
                $currentCapital -= $newStake;
            }
            // Pour 'void', le capital reste inchangé

            if (!$isDryRun && abs($originalStake - $newStake) > 0.01) {
                $bet->update(['stake' => round($newStake, 2)]);
                $modifications++;
            }
        }

        if (!$isDryRun) {
            $this->info("\n{$modifications} paris modifiés.");
        }
    }
}
