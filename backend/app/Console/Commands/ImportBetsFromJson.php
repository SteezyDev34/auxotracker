<?php

namespace App\Console\Commands;

use App\Models\Bet;
use App\Models\Sport;
use App\Models\UserBankroll;
use App\Models\Event;
use App\Models\League;
use App\Models\Country;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportBetsFromJson extends Command
{
    protected $signature = 'bets:import-json {file} {--user-id=} {--bankroll-id=} {--dry-run}';
    protected $description = 'Importer les paris depuis un fichier JSON';

    public function handle()
    {
        $filePath = $this->argument('file');
        $userId = $this->option('user-id');
        $bankrollId = $this->option('bankroll-id');
        $dryRun = $this->option('dry-run');

        // Vérifier l'existence du fichier
        if (!file_exists($filePath)) {
            $this->error("Le fichier {$filePath} n'existe pas.");
            return 1;
        }

        // Lire le fichier JSON
        $jsonContent = file_get_contents($filePath);
        $betsData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Erreur lors du décodage du JSON: ' . json_last_error_msg());
            return 1;
        }

        if (!is_array($betsData)) {
            $this->error('Le fichier JSON doit contenir un tableau de paris.');
            return 1;
        }

        // Déterminer la bankroll à utiliser
        $bankroll = $this->determineBankroll($userId, $bankrollId);
        if (!$bankroll) {
            return 1;
        }

        $this->info("Importation vers la bankroll: {$bankroll->name} (ID: {$bankroll->id})");
        $this->info("Utilisateur: {$bankroll->user->name} (ID: {$bankroll->user_id})");

        if ($dryRun) {
            $this->warn('Mode DRY-RUN activé - Aucune donnée ne sera sauvegardée');
        }

        // Statistiques d'importation
        $stats = [
            'total' => count($betsData),
            'success' => 0,
            'errors' => 0,
            'skipped' => 0
        ];

        $this->info("Début de l'importation de {$stats['total']} paris...");

        // Barre de progression
        $progressBar = $this->output->createProgressBar($stats['total']);
        $progressBar->start();

        foreach ($betsData as $index => $betData) {
            try {
                if ($this->importBet($betData, $bankroll, $dryRun)) {
                    $stats['success']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error("Erreur importation pari index {$index}: " . $e->getMessage(), [
                    'bet_data' => $betData,
                    'trace' => $e->getTraceAsString()
                ]);

                if ($this->getOutput()->isVerbose()) {
                    $this->error("\nErreur pari index {$index}: " . $e->getMessage());
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Afficher les statistiques
        $this->displayStats($stats, $dryRun);

        return 0;
    }

    private function determineBankroll($userId, $bankrollId)
    {
        if ($bankrollId) {
            $bankroll = UserBankroll::with('user')->find($bankrollId);
            if (!$bankroll) {
                $this->error("Bankroll avec l'ID {$bankrollId} non trouvée.");
                return null;
            }
            return $bankroll;
        }

        if ($userId) {
            $bankroll = UserBankroll::with('user')->where('user_id', $userId)->first();
            if (!$bankroll) {
                $this->error("Aucune bankroll trouvée pour l'utilisateur ID {$userId}.");
                return null;
            }
            return $bankroll;
        }

        // Demander à l'utilisateur de choisir
        $bankrolls = UserBankroll::with('user')->get();
        if ($bankrolls->isEmpty()) {
            $this->error('Aucune bankroll trouvée dans le système.');
            return null;
        }

        $this->info('Bankrolls disponibles:');
        foreach ($bankrolls as $br) {
            $this->line("ID: {$br->id} - {$br->name} (Utilisateur: {$br->user->name})");
        }

        $selectedId = $this->ask('Entrez l\'ID de la bankroll à utiliser');
        $bankroll = $bankrolls->find($selectedId);

        if (!$bankroll) {
            $this->error("Bankroll avec l'ID {$selectedId} non trouvée.");
            return null;
        }

        return $bankroll;
    }

    private function importBet(array $betData, UserBankroll $bankroll, bool $dryRun): bool
    {
        // Champs nullable : on récupère les valeurs ou null
        $dateStr = $betData['date'] ?? null;
        $statutStr = $betData['statut'] ?? null;
        $miseStr = $betData['mise'] ?? null;
        $coteStr = $betData['cote'] ?? null;
        $hourStr = $betData['hour'] ?? null;

        // Convertir la date si présente
        $betDate = null;
        if ($dateStr) {
            try {
                $betDate = Carbon::createFromFormat('d/m/Y', $dateStr);
                if ($hourStr) {
                    if (preg_match('/(\d{1,2}):(\d{2})/', $hourStr, $matches)) {
                        $betDate->setTime((int)$matches[1], (int)$matches[2]);
                    }
                }
            } catch (\Exception $e) {
                $betDate = null;
            }
        }

        // Convertir le statut si présent
        $result = $statutStr ? $this->convertStatus($statutStr) : null;

        // Extraire la mise
        $stake = $miseStr ? $this->extractNumericValue($miseStr) : null;
        if ($stake !== null && $stake <= 0) {
            $stake = null;
        }

        // Extraire la cote
        $odds = $coteStr ? $this->extractNumericValue($coteStr, ',') : null;
        if ($odds !== null && $odds <= 0) {
            $odds = null;
        }

        if ($dryRun) {
            return true; // Simulation réussie
        }

        // Créer le pari et l'événement en transaction
        return DB::transaction(function () use ($betData, $bankroll, $betDate, $result, $stake, $odds) {
            // Déterminer le sport_id
            $sportId = $this->getSportId($betData);

            // 1. Créer le pari principal avec sport_id
            $bet = Bet::create([
                'bet_date' => $betDate,
                'global_odds' => $odds,
                'bet_code' => $this->generateBetCode($betData),
                'result' => $result,
                'stake' => $stake,
                'bankroll_id' => $bankroll->id,
                'sport_id' => $sportId
            ]);

            // 2. Créer l'événement avec tous les détails
            $event = $this->createEventForBet($bet, $betData);

            // 3. Associer l'événement au pari via la table pivot
            $bet->events()->attach($event->id);

            return true;
        });
    }

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

    private function getSportId(array $betData): int
    {
        // Mapping des sports basé sur votre table
        $sportMapping = [
            // Football
            'FOOT' => 3,
            'FOOTBALL' => 3,
            'SOCCER' => 3,
            // Tennis
            'TENNIS' => 2,
            'TEN' => 2,
            // Basketball
            'BASKET' => 4,
            'BASKETBALL' => 4,
            'BAS' => 4,
            // Rugby
            'RUGBY' => 5,
            // Handball
            'HANDBALL' => 8,
            // Ice Hockey
            'HOCKEY' => 9,
            'ICE HOCKEY' => 9,
            // Baseball
            'BASEBALL' => 10,
            // Table tennis
            'TABLE TENNIS' => 11,
            'PING PONG' => 11,
            // American football
            'AMERICAN FOOTBALL' => 12,
            'FOOT US' => 12,
            'NFL' => 12,
            // Volleyball
            'VOLLEYBALL' => 13,
            'VOLLEY' => 13,
            // E-sports
            'ESPORTS' => 14,
            'ESPORT' => 14,
            'E-SPORTS' => 14,
            // Cricket
            'CRICKET' => 15,
            // Darts
            'DARTS' => 16,
            // Futsal
            'FUTSAL' => 17,
            // Badminton
            'BADMINTON' => 18,
            // Waterpolo
            'WATERPOLO' => 19,
            'WATER POLO' => 19,
            // Snooker
            'SNOOKER' => 20,
            // Aussie rules
            'AUSSIE RULES' => 21,
            'AFL' => 21,
            // UFC
            'UFC' => 22,
            'MMA' => 22,
            // Surf
            'SURF' => 23,
            'SURFING' => 23,
            // Ski Alpin
            'SKI ALPIN' => 24,
            'ALPINE SKIING' => 24,
            // Ski
            'SKI' => 25,
            'SKIING' => 25,
        ];

        // Essayer de détecter le sport depuis différents champs
        $sportSources = [
            $betData['sport'] ?? '',
            $betData['tournoi'] ?? '',
            $betData['description'] ?? ''
        ];

        foreach ($sportSources as $source) {
            $normalizedSource = strtoupper(trim($source));

            // Recherche exacte
            if (isset($sportMapping[$normalizedSource])) {
                return $sportMapping[$normalizedSource];
            }

            // Recherche partielle
            foreach ($sportMapping as $keyword => $sportId) {
                if (stripos($normalizedSource, $keyword) !== false) {
                    return $sportId;
                }
            }
        }

        // Sport par défaut : "Autre sport" (ID: 1)
        return 1;
    }

    private function generateBetCode(array $betData): string
    {
        $components = [];

        if (isset($betData['sport'])) {
            $sport = $this->cleanText($betData['sport']);
            $components[] = strtoupper(substr($sport, 0, 3));
        }

        if (isset($betData['date'])) {
            $components[] = str_replace('/', '', $betData['date']);
        }

        if (isset($betData['hour'])) {
            $components[] = str_replace(':', '', $betData['hour']);
        }

        return implode('_', $components) . '_' . uniqid();
    }

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

    private function getOrCreateCountry(array $betData): Country
    {
        // Essayer de détecter le pays depuis le tournoi
        $tournoi = $betData['tournoi'] ?? '';

        // Mapping des pays basé sur les tournois
        $countryMapping = [
            'France' => ['Ligue 1', 'Ligue 2', 'France'],
            'Espagne' => ['La Liga', 'Liga', 'Espagne'],
            'Angleterre' => ['Premier League', 'Championship', 'Angleterre'],
            'Italie' => ['Serie A', 'Serie B', 'Italie'],
            'Allemagne' => ['Bundesliga', 'Allemagne'],
            'USA' => ['NBA', 'NFL', 'MLB', 'USA'],
            'International' => ['Champions League', 'Europa League', 'UEFA']
        ];

        foreach ($countryMapping as $countryName => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($tournoi, $keyword) !== false) {
                    return Country::firstOrCreate(
                        ['name' => $countryName],
                        [
                            'iso_code' => $this->getCountryIsoCode($countryName),
                            'flag' => $this->getCountryFlag($countryName)
                        ]
                    );
                }
            }
        }

        // Pays par défaut
        return Country::firstOrCreate(
            ['name' => 'International'],
            ['iso_code' => 'INT', 'flag' => '�']
        );
    }

    private function getOrCreateLeague(array $betData, Sport $sport, Country $country): League
    {
        $leagueName = $betData['tournoi'] ?? 'Championnat Général';

        return League::firstOrCreate(
            ['name' => $leagueName, 'sport_id' => $sport->id],
            [
                'country_id' => $country->id,
                'season' => '2025',
                'slug' => strtolower(str_replace([' ', '-'], '-', $leagueName))
            ]
        );
    }

    private function parseTeamsFromDescription(string $description, Country $country): array
    {
        // Essayer de parser les équipes depuis la description
        // Format typique: "Team1 - Team2" ou "Team1 vs Team2"
        $patterns = [
            '/(.+?)\s+-\s+(.+?)(?:\s+\(|$)/',  // "Team1 - Team2 (League)"
            '/(.+?)\s+vs\s+(.+?)(?:\s+\(|$)/', // "Team1 vs Team2 (League)"
            '/(.+?)\s+\/\s+(.+?)(?:\s+\(|$)/', // "Team1 / Team2 (League)"
        ];

        $team1Name = 'Équipe A';
        $team2Name = 'Équipe B';

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                $team1Name = trim($matches[1]);
                $team2Name = trim($matches[2]);
                break;
            }
        }

        // Créer ou récupérer les équipes
        $team1 = Team::firstOrCreate(
            ['name' => $team1Name, 'country_id' => $country->id],
            ['slug' => strtolower(str_replace([' ', "'", '.'], ['-', '', ''], $team1Name))]
        );

        $team2 = Team::firstOrCreate(
            ['name' => $team2Name, 'country_id' => $country->id],
            ['slug' => strtolower(str_replace([' ', "'", '.'], ['-', '', ''], $team2Name))]
        );

        return ['team1' => $team1, 'team2' => $team2];
    }

    private function extractBetType(array $betData): string
    {
        $description = $betData['description'] ?? '';

        // Essayer de détecter le type de pari
        if (stripos($description, '1N2') !== false || stripos($description, 'résultat') !== false) {
            return '1N2';
        } elseif (stripos($description, 'handicap') !== false) {
            return 'Handicap';
        } elseif (stripos($description, 'over') !== false || stripos($description, 'under') !== false) {
            return 'Total Goals';
        } elseif (stripos($description, 'corner') !== false) {
            return 'Corners';
        } elseif (stripos($description, 'both teams') !== false || stripos($description, 'btts') !== false) {
            return 'Both Teams to Score';
        }

        return 'Match Result'; // Type par défaut
    }

    private function extractMarket(array $betData): string
    {
        $description = $betData['description'] ?? '';

        // Nettoyer les caractères d'encodage invalides
        $description = $this->cleanText($description);

        // Retourner la description complète sans limitation de longueur
        return $description ?: 'Résultat du match';
    }

    private function cleanText(string $text): string
    {
        // Essayer de détecter et convertir l'encodage correctement
        $detectedEncoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($detectedEncoding && $detectedEncoding !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $detectedEncoding);
        }

        // Remplacer les séquences d'encodage problématiques courantes
        $text = str_replace([
            'Ã©',
            'Ã¨',
            'Ã ',
            'Ã§',
            'Ã´',
            'Ã¢',
            'Ã®',
            'Ã»',
            'Ã¼',
            'Ã«',
            'Ã¯',
            'Ã±',
            'â€™',
            'â€œ',
            'â€',
            'â€¦',
            'â€"',
            'â€"',
            'Â€',
            'Â ',
            'Ã ',
            'remboursÃ©'
        ], [
            'é',
            'è',
            'à',
            'ç',
            'ô',
            'â',
            'î',
            'û',
            'ü',
            'ë',
            'ï',
            'ñ',
            "'",
            '"',
            '"',
            '...',
            '-',
            '-',
            'EUR',
            '',
            'à',
            'remboursé'
        ], $text);

        // Supprimer les caractères de contrôle (hors accents et lettres latines)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Nettoyer les espaces multiples et trimmer
        $text = preg_replace('/\s+/', ' ', trim($text));

        // NE PAS supprimer les caractères accentués
        return $text;
    }



    private function displayStats(array $stats, bool $dryRun): void
    {
        $this->newLine();
        $this->info('=== STATISTIQUES D\'IMPORTATION ===');
        $this->line("Total: {$stats['total']}");
        $this->line("Succès: {$stats['success']}");
        $this->line("Erreurs: {$stats['errors']}");
        $this->line("Ignorés: {$stats['skipped']}");

        if ($dryRun) {
            $this->warn('Mode DRY-RUN: Aucune donnée n\'a été sauvegardée');
        } else {
            $this->info("Importation terminée avec succès!");
        }

        if ($stats['errors'] > 0) {
            $this->warn("Des erreurs sont survenues. Consultez les logs pour plus de détails.");
        }
    }
}
