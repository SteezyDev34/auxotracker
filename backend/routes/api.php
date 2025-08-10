<?php

use App\Http\Controllers\BetController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TeamLogoController;
use App\Http\Controllers\SportController;
use App\Http\Controllers\CountryController;
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

// Routes d'authentification (sans middleware pour le dev)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// Routes des paris (sans authentification temporairement)
Route::get('/bets/stats', [BetController::class, 'stats']);
Route::get('/bets/detailed-stats', [BetController::class, 'detailedStats']);
Route::get('/bets/capital-evolution', [BetController::class, 'capitalEvolution']);
Route::get('/bets/filter-options', [BetController::class, 'filterOptions']);
Route::apiResource('bets', BetController::class);

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
