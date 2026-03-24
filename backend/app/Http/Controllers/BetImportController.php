<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Sport;
use App\Models\UserBankroll;
use App\Models\Event;
use App\Models\League;
use App\Models\Country;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BetImportController extends Controller
{
    /**
     * Importer des paris depuis un fichier JSON uploadé
     */
    public function importFromJson(Request $request): JsonResponse
    {
        // Validation de la requête
        $validator = Validator::make($request->all(), [
            'json_data' => 'required|string',
            'bankroll_id' => 'nullable|exists:user_bankrolls,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Décoder les données JSON
            $betsData = json_decode($request->json_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'error' => 'Format JSON invalide: ' . json_last_error_msg()
                ], 400);
            }

            if (!is_array($betsData)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Le JSON doit contenir un tableau de paris'
                ], 400);
            }

            // Déterminer la bankroll à utiliser
            $user = auth()->user();
            $bankroll = $this->determineBankroll($user, $request->bankroll_id);

            if (!$bankroll) {
                return response()->json([
                    'success' => false,
                    'error' => 'Aucune bankroll disponible'
                ], 400);
            }

            // Statistiques d'importation
            $stats = [
                'total' => count($betsData),
                'success' => 0,
                'errors' => 0,
                'skipped' => 0,
                'error_details' => []
            ];

            // Importer chaque pari
            foreach ($betsData as $index => $betData) {
                try {
                    if ($this->importBet($betData, $bankroll)) {
                        $stats['success']++;
                    } else {
                        $stats['skipped']++;
                        if (count($stats['error_details']) < 5) { // Limiter les détails d'erreur
                            $stats['error_details'][] = "Pari index {$index}: Données invalides ou incomplètes";
                        }
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error("Erreur importation pari index {$index}: " . $e->getMessage(), [
                        'bet_data' => $betData,
                        'user_id' => $user->id,
                        'bankroll_id' => $bankroll->id
                    ]);

                    if (count($stats['error_details']) < 5) { // Limiter les détails d'erreur
                        $stats['error_details'][] = "Pari index {$index}: " . $e->getMessage();
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Importation terminée',
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur générale importation JSON: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Une erreur est survenue lors de l\'importation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prévisualiser l'importation sans sauvegarder
     */
    public function previewImport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'json_data' => 'required|string',
            'limit' => 'nullable|integer|min:1|max:50' // Limiter la prévisualisation
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $betsData = json_decode($request->json_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'error' => 'Format JSON invalide: ' . json_last_error_msg()
                ], 400);
            }

            if (!is_array($betsData)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Le JSON doit contenir un tableau de paris'
                ], 400);
            }

            $limit = $request->limit ?? 10;
            $preview = array_slice($betsData, 0, $limit);
            $processedPreview = [];

            foreach ($preview as $index => $betData) {
                $processed = $this->processBetDataForPreview($betData, $index);
                $processedPreview[] = $processed;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_bets' => count($betsData),
                    'preview_count' => count($processedPreview),
                    'preview' => $processedPreview
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la prévisualisation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déterminer la bankroll à utiliser pour l'importation
     */
    private function determineBankroll($user, $bankrollId = null): ?UserBankroll
    {
        if ($bankrollId) {
            return UserBankroll::where('id', $bankrollId)
                ->where('user_id', $user->id)
                ->first();
        }

        // Prendre la première bankroll de l'utilisateur
        return UserBankroll::where('user_id', $user->id)->first();
    }

    /**
     * Importer un pari individuel
     */
    private function importBet(array $betData, UserBankroll $bankroll): bool
    {
        // Validation des champs requis
        if (!isset($betData['date']) || !isset($betData['statut']) || !isset($betData['mise']) || !isset($betData['cote'])) {
            return false;
        }

        // Convertir la date
        try {
            $betDate = Carbon::createFromFormat('d/m/Y', $betData['date']);
            // Ajouter l'heure si disponible
            if (isset($betData['hour'])) {
                $timeStr = $betData['hour'];
                if (preg_match('/(\d{1,2}):(\d{2})/', $timeStr, $matches)) {
                    $betDate->setTime((int)$matches[1], (int)$matches[2]);
                }
            }
        } catch (\Exception $e) {
            return false;
        }

        // Convertir le statut
        $result = $this->convertStatus($betData['statut']);

        // Extraire la mise (retirer le symbole €)
        $stake = $this->extractNumericValue($betData['mise']);
        if ($stake <= 0) {
            return false;
        }

        // Extraire la cote
        $odds = $this->extractNumericValue($betData['cote'], ',');
        if ($odds <= 0) {
            return false;
        }

        // Créer le pari et l'événement en transaction
        return DB::transaction(function () use ($betData, $bankroll, $betDate, $result, $stake, $odds) {
            // 1. Créer le pari principal (sans sport_id car ce n'est pas dans fillable du modèle Bet)
            $bet = Bet::create([
                'bet_date' => $betDate,
                'global_odds' => $odds,
                'bet_code' => $this->generateBetCode($betData),
                'result' => $result,
                'stake' => $stake,
                'bankroll_id' => $bankroll->id
            ]);

            // 2. Créer l'événement avec tous les détails
            $event = $this->createEventForBet($bet, $betData);

            // 3. Associer l'événement au pari via la table pivot
            $bet->events()->attach($event->id);

            return true;
        });
    }

    /**
     * Traiter les données d'un pari pour la prévisualisation
     */
    private function processBetDataForPreview(array $betData, int $index): array
    {
        $processed = [
            'index' => $index,
            'original_data' => $betData,
            'processed_data' => [],
            'errors' => [],
            'warnings' => []
        ];

        // Valider et traiter chaque champ
        try {
            // Date
            if (isset($betData['date'])) {
                try {
                    $date = Carbon::createFromFormat('d/m/Y', $betData['date']);
                    $processed['processed_data']['date'] = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    $processed['errors'][] = "Format de date invalide: {$betData['date']}";
                }
            } else {
                $processed['errors'][] = "Date manquante";
            }

            // Statut
            if (isset($betData['statut'])) {
                $processed['processed_data']['result'] = $this->convertStatus($betData['statut']);
            } else {
                $processed['errors'][] = "Statut manquant";
            }

            // Mise
            if (isset($betData['mise'])) {
                $stake = $this->extractNumericValue($betData['mise']);
                if ($stake > 0) {
                    $processed['processed_data']['stake'] = $stake;
                } else {
                    $processed['errors'][] = "Mise invalide: {$betData['mise']}";
                }
            } else {
                $processed['errors'][] = "Mise manquante";
            }

            // Cote
            if (isset($betData['cote'])) {
                $odds = $this->extractNumericValue($betData['cote'], ',');
                if ($odds > 0) {
                    $processed['processed_data']['odds'] = $odds;
                } else {
                    $processed['errors'][] = "Cote invalide: {$betData['cote']}";
                }
            } else {
                $processed['errors'][] = "Cote manquante";
            }

            // Sport
            $processed['processed_data']['sport'] = $this->normalizeSportName($betData['sport'] ?? 'INCONNU');

            // Calculer le gain potentiel
            if (isset($processed['processed_data']['stake']) && isset($processed['processed_data']['odds'])) {
                $potential_win = $processed['processed_data']['stake'] * $processed['processed_data']['odds'];
                $processed['processed_data']['potential_win'] = round($potential_win, 2);
            }
        } catch (\Exception $e) {
            $processed['errors'][] = "Erreur de traitement: " . $e->getMessage();
        }

        $processed['is_valid'] = empty($processed['errors']);

        return $processed;
    }

    /**
     * Convertir le statut du pari
     */
    private function convertStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'GAGNÉ', 'GAGNÉ COTE RÉDUITE' => 'win',
            'PERDU' => 'lost',
            'REMBOURSÉ' => 'void',
            'EN COURS' => 'pending',
            default => 'pending'
        };
    }

    /**
     * Extraire une valeur numérique d'une chaîne
     */
    private function extractNumericValue(string $value, string $decimalSeparator = '.'): float
    {
        // Retirer les symboles monétaires et espaces
        $cleaned = preg_replace('/[€$\s]/', '', $value);

        // Remplacer la virgule par un point si nécessaire
        if ($decimalSeparator === ',') {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return (float) $cleaned;
    }

    /**
     * Récupérer ou créer un sport
     */
    private function getOrCreateSport(string $sportName): Sport
    {
        $normalizedName = $this->normalizeSportName($sportName);
        $slug = strtolower(str_replace(' ', '-', $normalizedName));

        return Sport::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $normalizedName,
                'description' => "Sport: {$normalizedName}",
            ]
        );
    }

    /**
     * Normaliser le nom du sport
     */
    private function normalizeSportName(string $sportName): string
    {
        $normalizedName = strtoupper($sportName);

        // Mapping des sports
        $sportMapping = [
            'FOOT' => 'Football',
            'BASKET' => 'Basketball',
            'TENNIS' => 'Tennis',
            'RUGBY' => 'Rugby',
            'HANDBALL' => 'Handball',
            'VOLLEY' => 'Volleyball',
            'HOCKEY' => 'Hockey',
            'BASEBALL' => 'Baseball',
            'AMERICAN FOOTBALL' => 'American Football',
            'INCONNU' => 'Autre'
        ];

        return $sportMapping[$normalizedName] ?? ucfirst(strtolower($sportName));
    }

    /**
     * Générer un code de pari unique
     */
    private function generateBetCode(array $betData): string
    {
        $components = [];

        if (isset($betData['sport'])) {
            $components[] = strtoupper(substr($betData['sport'], 0, 3));
        }

        if (isset($betData['date'])) {
            $components[] = str_replace('/', '', $betData['date']);
        }

        if (isset($betData['hour'])) {
            $components[] = str_replace(':', '', $betData['hour']);
        }

        return implode('_', $components) . '_' . uniqid();
    }

    /**
     * Créer un événement pour un pari
     */
    private function createEventForBet(Bet $bet, array $betData): Event
    {
        // Créer l'événement avec seulement les informations essentielles
        $event = Event::create([
            'event_date' => $bet->bet_date,
            'market' => $this->extractMarket($betData),
            'odd' => $bet->global_odds
        ]);

        return $event;
    }



    private function extractMarket(array $betData): string
    {
        $description = $betData['description'] ?? '';

        // Le marché est souvent la description complète ou une partie
        /*  if (strlen($description) > 100) {
            return substr($description, 0, 97) . '...';
        } */

        return $description ?: 'Résultat du match';
    }
}
