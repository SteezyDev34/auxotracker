<?php

namespace App\Services;

use App\Models\Bet;
use App\Models\Tipster;
use App\Models\UserBankroll;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class StakeRecommendationService
{
    /**
     * Calcule la mise recommandée en se basant sur le capital, un tipster et l'historique
     *
     * @param User $user
     * @param array $params
     * @return array
     */
    public static function recommend(User $user, array $params = []): array
    {
        // Optionnel: ciblage d'une bankroll spécifique
        $bankrollId = $params['bankroll_id'] ?? null;

        $userBankrollsQuery = UserBankroll::where('user_id', $user->id);
        if ($bankrollId) {
            $userBankrollsQuery->where('id', $bankrollId);
        }
        $userBankrolls = $userBankrollsQuery->get();

        $bankrollIds = $userBankrolls->pluck('id')->toArray();

        // Capital initial = somme des bankroll_start_amount
        $initialCapital = (float) $userBankrolls->sum('bankroll_start_amount');

        // Calculer le P/L total depuis les paris (même logique que capitalEvolution)
        $bets = Bet::whereIn('bankroll_id', $bankrollIds)->get(['stake', 'global_odds', 'result', 'tipster_id', 'id']);
        $totalProfitLoss = 0.0;
        foreach ($bets as $bet) {
            if ($bet->result === 'win') {
                $totalProfitLoss += ((float)$bet->stake * (float)$bet->global_odds) - (float)$bet->stake;
            } elseif ($bet->result === 'lost') {
                $totalProfitLoss -= (float)$bet->stake;
            } elseif ($bet->result === 'void') {
                $totalProfitLoss += 0.0;
            }
        }

        $bankroll = $initialCapital + $totalProfitLoss;

        // Pourcentage cible (en %)
        $target_percentage = isset($params['target_percentage']) ? floatval($params['target_percentage']) : 1.0;
        $target_gain = round($bankroll * ($target_percentage / 100.0), 2);

        $recover_losses = isset($params['recover_losses']) && ($params['recover_losses'] === 1 || $params['recover_losses'] === '1' || $params['recover_losses'] === true || $params['recover_losses'] === 'true');

        $gain_manque = 0.0;
        $last_lost_bet = null;

        // Si on souhaite récupérer les pertes et qu'un tipster est fourni
        if ($recover_losses && !empty($params['tipster'])) {
            $tipParam = $params['tipster'];
            $tipsterModel = null;

            if (is_numeric($tipParam)) {
                $tipsterModel = Tipster::find((int)$tipParam);
            } else {
                $term = mb_strtolower(trim($tipParam));
                $tipsterModel = Tipster::whereRaw('LOWER(name) = ?', [$term])->first();
            }

            $tipsterId = $tipsterModel ? $tipsterModel->id : null;
            if ($tipsterId) {
                $lastLostBet = Bet::whereIn('bankroll_id', $bankrollIds)
                    ->where('tipster_id', $tipsterId)
                    ->where('result', 'lost')
                    ->orderBy('bet_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastLostBet) {
                    $last_lost_bet = $lastLostBet;
                    // Comme dans la version d'origine : stake * odds (payout)
                    $gain_manque = (float)$lastLostBet->stake * ((float)$lastLostBet->global_odds ?: 2.0);
                }
            }
        }

        // Cote fournie en param (sinon défaut 2.0)
        $odds = isset($params['odds']) ? floatval($params['odds']) : 2.0;
        if ($odds <= 1.0) {
            $odds = 2.0;
        }

        // Calcul du gain voulu et de la mise recommandée
        $gain_voulu = $target_gain + $gain_manque;
        $stake = ($odds - 1.0) > 0.0 ? round($gain_voulu / ($odds - 1.0), 2) : round($gain_voulu, 2);

        $message = $recover_losses ? "pour gagner {$target_percentage}% + pertes récentes" : "pour gagner {$target_percentage}%";

        return [
            'recommended_stake' => $stake,
            'bankroll' => round($bankroll, 2),
            'target_gain' => round($target_gain, 2),
            'lost_sum' => round($gain_manque, 2),
            'gain_voulu' => round($gain_voulu, 2),
            'odds' => $odds,
            'last_lost_bet_id' => $last_lost_bet ? $last_lost_bet->id : null,
            'message' => $message
        ];
    }
}
