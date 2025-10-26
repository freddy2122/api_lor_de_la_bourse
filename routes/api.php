<?php

use App\Http\Controllers\Api\AccountOpeningController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AccountOpeningRequestController as AdminAccountOpeningController;
use App\Http\Controllers\Api\Admin\ArticleManagementController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\UsersController;
use App\Http\Controllers\Api\MarketDataMockController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\Admin\StatsController;
use App\Http\Controllers\Api\Admin\OrdersController;
use App\Http\Controllers\Api\ScrapingController;
use App\Http\Controllers\Api\CompaniesController;

// --- Routes publiques ---
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
})->middleware('throttle:30,1');
Route::get('/articles', [ArticleController::class, 'index'])->middleware('throttle:60,1');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->middleware('throttle:60,1');

// --- Market Data (mock) ---
Route::get('/market/quotes', [MarketDataMockController::class, 'quotes'])->middleware('throttle:120,1');
Route::get('/market/stream', [MarketDataMockController::class, 'stream'])->middleware('throttle:120,1');

// --- Market Data (proxy -> Alpha Vantage or future BRVM) ---
Route::get('/market/top-movers', [MarketController::class, 'topMovers'])->middleware('throttle:60,1');
Route::get('/market/indices', [MarketController::class, 'indices'])->middleware('throttle:60,1');
Route::get('/market/summary', [MarketController::class, 'summary'])->middleware('throttle:60,1');
Route::get('/market/quotes-list', [MarketController::class, 'quotesList'])->middleware('throttle:60,1');

// --- Sociétés cotées ---
Route::get('/market/companies', [CompaniesController::class, 'index'])->middleware('throttle:60,1');
Route::get('/market/companies/{id}', [CompaniesController::class, 'show'])->middleware('throttle:60,1');

// --- Authentification publique ---
Route::prefix('auth')->middleware('throttle:20,1')->group(function () {
    Route::post('/account-opening-requests', [AccountOpeningController::class, 'store']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/forgot', [AuthController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('/reset', [AuthController::class, 'reset'])->middleware('throttle:5,1');
});

// --- Routes protégées (auth:sanctum) ---
Route::middleware('auth:sanctum')->group(function () {
    // Infos de l’utilisateur connecté
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::put('/user/profile', [ProfileController::class, 'update']);
    Route::post('/user/change-password', [ProfileController::class, 'changePassword']);

    // --- Routes Admin ---
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/account-opening-requests', [AdminAccountOpeningController::class, 'index'])
            ->name('account-opening-requests.index');

        Route::get('/account-opening-requests/{request}', [AdminAccountOpeningController::class, 'show'])
            ->name('account-opening-requests.show');

        Route::post('/account-opening-requests/{accountOpeningRequest}/approve', [AdminAccountOpeningController::class, 'approve']);
        Route::post('/account-opening-requests/{accountOpeningRequest}/reject', [AdminAccountOpeningController::class, 'reject']);

        Route::apiResource('articles', ArticleManagementController::class);
        // Publication / Dépublication
        Route::post('/articles/{article}/publish', [ArticleManagementController::class, 'publish'])->middleware('throttle:10,1');
        Route::post('/articles/{article}/unpublish', [ArticleManagementController::class, 'unpublish'])->middleware('throttle:10,1');

        // Settings - commission rate
        Route::get('/settings/fees', [SettingsController::class, 'getCommission']);
        Route::put('/settings/fees', [SettingsController::class, 'updateCommission']);

        // Users - clients listing
        Route::get('/users', [UsersController::class, 'index']);
        Route::patch('/users/{user}/status', [UsersController::class, 'updateStatus']);

        // Admin stats summary
        Route::get('/stats', [StatsController::class, 'summary']);

        // Admin orders (pending, etc.)
        Route::get('/orders', [OrdersController::class, 'index']);

        // Admin scraping triggers
        Route::post('/scraping/run/instruments', [ScrapingController::class, 'runInstruments']);
        Route::post('/scraping/run/indices', [ScrapingController::class, 'runIndices']);
        Route::post('/scraping/run/quotes', [ScrapingController::class, 'runQuotes']);
        Route::get('/scraping/status', [ScrapingController::class, 'status']);
    });
});
