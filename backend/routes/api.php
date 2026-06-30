<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\BetImportController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TeamLogoController;
use App\Http\Controllers\SportController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BookmakerController;
use App\Http\Controllers\UserBankrollController;
use App\Http\Controllers\UserBookmakerController;
use App\Http\Controllers\TipsterController;
use App\Http\Controllers\UserSportPreferenceController;
use App\Http\Controllers\SofaScoreController;
use App\Http\Controllers\InteretController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\AdminLeagueController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\TeamSearchNotFoundController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route de test simple (sans authentification)
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API fonctionne correctement'
    ]);
});

// Routes d'authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);

// Routes des paris (protégées par authentification)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bets/stats', [BetController::class, 'stats']);
    Route::get('/bets/detailed-stats', [BetController::class, 'detailedStats']);
    Route::get('/bets/capital-evolution', [BetController::class, 'capitalEvolution']);
    Route::get('/bets/filter-options', [BetController::class, 'filterOptions']);
    Route::apiResource('bets', BetController::class);

    // Routes d'importation de paris
    Route::post('/bets/import/json', [BetImportController::class, 'importFromJson']);
    Route::post('/bets/import/preview', [BetImportController::class, 'previewImport']);
});

// Routes dédiées pour le bot local Auxobot (token vérifié par middleware)
Route::post('/auxobot/bets', [BetController::class, 'storeAuxobot'])->middleware('auxobot');
Route::get('/auxobot/recommended-stake', [UserBankrollController::class, 'recommendedStakeForBot'])->middleware('auxobot');

// Routes des transactions
Route::get('/transactions/stats', [TransactionController::class, 'stats']);
Route::apiResource('transactions', TransactionController::class);

// Routes CRUD pour les événements (sans authentification temporairement)
Route::apiResource('events', EventController::class);

// Routes pour les pays
Route::get('/countries', [CountryController::class, 'index']);
Route::get('/countries/search', [CountryController::class, 'search']);

// Routes pour les sports, ligues et équipes
Route::get('/sports', [SportController::class, 'index']);
Route::get('/sports/{sportId}/countries', [SportController::class, 'getCountriesBySport']);
Route::get('/sports/{sportId}/leagues', [SportController::class, 'getLeagues']);
Route::get('/sports/{sportId}/leagues/search', [SportController::class, 'searchLeaguesBySport']);
Route::get('/sports/{sportId}/teams', [SportController::class, 'getTeamsBySport']);
Route::get('/sports/{sportId}/teams/search', [SportController::class, 'searchTeamsBySport']);
Route::get('/leagues/{leagueId}/teams', [SportController::class, 'getTeams']);

// Routes pour la gestion des logos d'équipes
Route::prefix('teams')->group(function () {
    Route::get('/logos/status', [TeamLogoController::class, 'checkStatus']);
    Route::post('/logos/download-all', [TeamLogoController::class, 'downloadAllMissing']);
    Route::get('/{teamId}/logo/download', [TeamLogoController::class, 'downloadLogo']);
});

// Routes pour les statistiques SofaScore
Route::get('/stats/tennis/sofascore_id/{sofascoreId}', [SofaScoreController::class, 'getPlayerStatistics']);
Route::get('/stats/tennis/player/{teamId}', [SofaScoreController::class, 'getTeamStatistics']);

// Retrouver le lien Sofascore d'un match tennis par date + noms des joueurs
// GET /api/matches/tennis/link?team1=Alcaraz&team2=Djokovic&date=2026-05-07
Route::get('/matches/tennis/link', [MatchController::class, 'findTennisLink']);

// Routes protégées (commentées temporairement pour le dev)
/*
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    // Routes CRUD pour les paris
    Route::apiResource('bets', BetController::class);

    // Routes spécialisées pour les paris
    Route::get('/bets/stats', [BetController::class, 'stats']);
    Route::get('/bets/capital-evolution', [BetController::class, 'capitalEvolution']);
    Route::get('/bets/filter-options', [BetController::class, 'filterOptions']);

    // Routes CRUD pour les événements
    Route::apiResource('events', EventController::class);
});
*/

Route::middleware('auth:sanctum')->post('/user/avatar', [UserController::class, 'updateAvatar']);
Route::middleware('auth:sanctum')->delete('/user/avatar', [UserController::class, 'deleteAvatar']);
Route::middleware('auth:sanctum')->post('/user/settings', [UserController::class, 'updateSettings']);

// Routes pour les préférences sportives de l'utilisateur
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/sports-preferences', [UserSportPreferenceController::class, 'index']);
    Route::post('/user/sports-preferences', [UserSportPreferenceController::class, 'update']);
    Route::get('/user/sports-preferences/favorites', [UserSportPreferenceController::class, 'favorites']);
    Route::post('/user/sports-preferences/{sportId}/toggle-favorite', [UserSportPreferenceController::class, 'toggleFavorite']);
});

// Routes pour les bookmakers
Route::get('/bookmakers', [BookmakerController::class, 'index']);
Route::get('/bookmakers/{id}', [BookmakerController::class, 'show']);

// Routes protégées pour les bankrolls et bookmakers utilisateur
Route::middleware('auth:sanctum')->group(function () {
    // Calcul de mise recommandée (GET query params: tipster, target_percentage, recover_losses, odds, bankroll_id)
    // Doit être défini AVANT l'apiResource 'bankrolls' pour ne pas être intercepté par la route show (GET /bankrolls/{bankroll})
    Route::get('/bankrolls/recommended-stake', [UserBankrollController::class, 'recommendedStake']);

    // Routes pour les bankrolls de l'utilisateur
    Route::apiResource('bankrolls', UserBankrollController::class);



    // Routes pour les associations utilisateur-bookmaker
    Route::apiResource('user-bookmakers', UserBookmakerController::class);



    // Routes pour les tipsters de l'utilisateur
    Route::apiResource('tipsters', TipsterController::class);

    // Routes pour les intérêts (investisseurs)
    Route::get('/interets/auth-test', [InteretController::class, 'authTest']);
    Route::get('/interets/stats', [InteretController::class, 'stats']);
    Route::get('/interets/evolution', [InteretController::class, 'evolution']);
    Route::get('/interets/filter-options', [InteretController::class, 'filterOptions']);
    Route::apiResource('interets', InteretController::class);

    // Routes pour les investissements
    Route::get('/investments/stats', [InvestmentController::class, 'stats']);
    Route::apiResource('investments', InvestmentController::class);


    // Routes pour les intérêts (investisseurs)
    Route::get('/interets/auth-test', [InteretController::class, 'authTest']);
    Route::get('/interets/stats', [InteretController::class, 'stats']);
    Route::get('/interets/evolution', [InteretController::class, 'evolution']);
    Route::get('/interets/filter-options', [InteretController::class, 'filterOptions']);
    Route::apiResource('interets', InteretController::class);

    // Routes pour les investissements
    Route::get('/investments/stats', [InvestmentController::class, 'stats']);
    Route::apiResource('investments', InvestmentController::class);

    // Routes admin pour la gestion des bookmakers (à protéger davantage si nécessaire)
    Route::post('/bookmakers', [BookmakerController::class, 'store']);
    Route::put('/bookmakers/{id}', [BookmakerController::class, 'update']);
    Route::delete('/bookmakers/{id}', [BookmakerController::class, 'destroy']);
});

// Routes d'administration - Accès admin et superadmin uniquement
Route::middleware(['auth:sanctum', 'role:admin,superadmin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::get('/stats', [AdminController::class, 'getSystemStats']);

    // Gestion des ligues (admin)
    Route::get('/leagues', [AdminLeagueController::class, 'index']);
    Route::put('/leagues/priorities', [AdminLeagueController::class, 'updatePriorities']);
    Route::put('/leagues/{id}', [AdminLeagueController::class, 'update']);
    Route::delete('/leagues/{id}', [AdminLeagueController::class, 'destroy']);

    // Routes superadmin uniquement
    Route::middleware('role:superadmin')->group(function () {
        Route::put('/users/{id}/role', [AdminController::class, 'updateUserRole']);
    });
});

// Routes d'administration (réservées aux admins et superadmins)
Route::middleware(['auth:sanctum', 'role:admin,superadmin'])->prefix('admin')->group(function () {
    Route::get('/users', [App\Http\Controllers\AdminController::class, 'getUsers']);
    Route::get('/stats', [App\Http\Controllers\AdminController::class, 'getSystemStats']);

    // Routes réservées aux superadmins uniquement
    Route::middleware(['role:superadmin'])->group(function () {
        Route::put('/users/{id}/role', [App\Http\Controllers\AdminController::class, 'updateUserRole']);
    });
});
// Routes d'administration - Accès admin et superadmin uniquement
Route::middleware(['auth:sanctum', 'role:admin,superadmin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::get('/stats', [AdminController::class, 'getSystemStats']);

    // Routes superadmin uniquement
    Route::middleware('role:superadmin')->group(function () {
        Route::put('/users/{id}/role', [AdminController::class, 'updateUserRole']);
    });
});

// Routes d'administration (réservées aux admins et superadmins)
Route::middleware(['auth:sanctum', 'role:admin,superadmin'])->prefix('admin')->group(function () {
    Route::get('/users', [App\Http\Controllers\AdminController::class, 'getUsers']);
    Route::get('/stats', [App\Http\Controllers\AdminController::class, 'getSystemStats']);

    // Routes réservées aux superadmins uniquement
    Route::middleware(['role:superadmin'])->group(function () {
        Route::put('/users/{id}/role', [App\Http\Controllers\AdminController::class, 'updateUserRole']);
    });
});
// Gestion des équipes non trouvées
// Endpoint temporaire : réception des données fetchées par Chrome (offline cache)
// Le header Access-Control-Allow-Private-Network permet les requêtes depuis sofascore.com → 127.0.0.1
Route::options('/tennis-cache-write', function (\Illuminate\Http\Request $request) {
    return response('', 204)
        ->header('Access-Control-Allow-Origin', $request->header('Origin', '*'))
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type')
        ->header('Access-Control-Allow-Private-Network', 'true')
        ->header('Access-Control-Allow-Credentials', 'false');
});
Route::post('/tennis-cache-write', function (\Illuminate\Http\Request $request) {
    $type = $request->input('type');
    $data = $request->input('data');
    $origin = $request->header('Origin', '*');
    $allowed = ['source_scheduled', 'source_live'];
    if (!in_array($type, $allowed, true)) {
        return response()->json(['error' => 'type non autorisé'], 400)
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Private-Network', 'true');
    }
    $date = date('Y-m-d');
    $path = storage_path("app/sofascore_cache/{$type}_{$date}.json");
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
    return response()->json(['ok' => true, 'path' => $path, 'events' => count($data['events'] ?? [])])
        ->header('Access-Control-Allow-Origin', $origin)
        ->header('Access-Control-Allow-Private-Network', 'true');
});

Route::get('/team-searches/not-found', [TeamSearchNotFoundController::class, 'index']);
Route::post('/team-searches/not-found', [TeamSearchNotFoundController::class, 'store']);
Route::put('/team-searches/not-found/{id}/resolve', [TeamSearchNotFoundController::class, 'resolve']);
Route::delete('/team-searches/not-found/{id}', [TeamSearchNotFoundController::class, 'destroy']);
