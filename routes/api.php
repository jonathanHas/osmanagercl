<?php

use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\TestScraperController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:web', 'role:admin'])->prefix('test-scraper')->group(function () {
    // Signs in to udea.nl with the configured supplier credentials and returns the
    // raw scraped page, so it must never be reachable without a login.
    Route::get('/debug-search-raw', [TestScraperController::class, 'debugSearchRaw']);
    Route::post('/product-data', [TestScraperController::class, 'proxyProductData']);
    Route::get('/connection-test', [TestScraperController::class, 'testConnection']);
    Route::post('/clear-cache', [TestScraperController::class, 'clearCache']);
    Route::get('/test-api', [TestScraperController::class, 'testApiRoute']);
    Route::get('/test-udea-connection', [TestScraperController::class, 'testUdeaConnection']);
    Route::get('/find-login-url', [TestScraperController::class, 'findLoginUrl']);
    Route::get('/debug-login-page', [TestScraperController::class, 'debugLoginPage']);
});

// Delivery API routes for real-time scanning (using web guard for session authentication)
Route::middleware(['auth:web', 'permission:deliveries.manage'])->prefix('deliveries')->group(function () {
    Route::post('/{delivery}/scan', [DeliveryController::class, 'processScan']);
    Route::get('/{delivery}/stats', [DeliveryController::class, 'getStats']);
    Route::patch('/{delivery}/items/{item}/quantity', [DeliveryController::class, 'adjustQuantity']);
    Route::post('/{delivery}/items', [DeliveryController::class, 'createDeliveryItem']);
});

// Product API routes moved to web.php for session authentication
