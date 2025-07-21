<?php

use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\TestScraperController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Debug routes (temporarily without auth for testing)
Route::prefix('test-scraper')->group(function () {
    Route::get('/debug-search-raw', [TestScraperController::class, 'debugSearchRaw']);
});

Route::middleware('auth')->prefix('test-scraper')->group(function () {
    Route::post('/product-data', [TestScraperController::class, 'proxyProductData']);
    Route::get('/connection-test', [TestScraperController::class, 'testConnection']);
    Route::post('/clear-cache', [TestScraperController::class, 'clearCache']);
    Route::post('/queue-scraping', [TestScraperController::class, 'queueScraping']);
    Route::get('/test-api', [TestScraperController::class, 'testApiRoute']);
    Route::get('/test-udea-connection', [TestScraperController::class, 'testUdeaConnection']);
    Route::get('/find-login-url', [TestScraperController::class, 'findLoginUrl']);
    Route::get('/debug-search', [TestScraperController::class, 'debugSearch']);
    Route::get('/debug-login-page', [TestScraperController::class, 'debugLoginPage']);
});

// Delivery API routes for real-time scanning
Route::middleware('auth')->prefix('deliveries')->group(function () {
    Route::post('/{delivery}/scan', [DeliveryController::class, 'processScan']);
    Route::get('/{delivery}/stats', [DeliveryController::class, 'getStats']);
    Route::patch('/{delivery}/items/{item}/quantity', [DeliveryController::class, 'adjustQuantity']);
});
