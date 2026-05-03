<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\LoginController;
use App\Http\Controllers\API\LivestockController;
use App\Http\Controllers\API\PenController;
use App\Http\Controllers\API\FeedController;
use App\Http\Controllers\API\PredictionController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\ChatbotController;
use App\Http\Controllers\API\ProgramController;
use App\Http\Controllers\API\LogbookController;
use App\Http\Controllers\API\HppController;
use App\Http\Controllers\API\NotifikasiController;
use App\Http\Controllers\API\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes - TernakPark Backend
|--------------------------------------------------------------------------
|
| Berikut adalah semua endpoint API untuk backend TernakPark.
| Dibagi menjadi route publik (tanpa autentikasi) dan route protected (auth:sanctum).
|
*/

// ==================== PUBLIC ROUTES (tanpa autentikasi) ====================

// Health check dan test endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'timestamp' => now()]);
});

Route::get('/test', function () {
    return response()->json(['success' => true, 'message' => 'Backend API accessible']);
});

// Authentication
Route::post('/login', [LoginController::class, 'login']);

// Dummy route login untuk menghindari redirect (diperlukan oleh Laravel)
Route::get('/login', function () {
    return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
})->name('login');

// Public feeds endpoints (beberapa boleh diakses tanpa auth karena kebutuhan frontend)
Route::get('/feeds/requirements', [FeedController::class, 'requirements']);
Route::get('/feeds/stock/summary', [FeedController::class, 'stockSummary']);
Route::post('/chatbot-public', [ChatbotController::class, 'chat']);

// Dashboard endpoints (tanpa auth untuk development, bisa diubah nanti)
Route::prefix('/dashboard')->group(function () {
    Route::get('/overview', [DashboardController::class, 'overview']);
    Route::get('/pen-analytics', [DashboardController::class, 'penAnalytics']);
    Route::get('/statistics', [DashboardController::class, 'statistics']);
});

// ==================== PUBLIC ROUTES UNTUK BREEDING KAWIN & IB ====================
Route::get('/program/breeding/kawin', [ProgramController::class, 'breedingKawinIb']);
Route::get('/program/breeding/kawin-ib', [ProgramController::class, 'breedingKawinIb']);

// ==================== PUBLIC ROUTES UNTUK RINGKASAN KANDANG ====================
Route::get('/pens/livestock', [PenController::class, 'getLivestockByPen']);

// ==================== PUBLIC ROUTES UNTUK PENGGUNAAN PAKAN ====================
Route::get('/feeds/usage-data', [FeedController::class, 'usageData']);
Route::get('/feeds/procurement-data', [FeedController::class, 'procurementData']);

// ==================== PROTECTED ROUTES (memerlukan token Sanctum) ====================
Route::middleware('auth:sanctum')->group(function () {

    // --- Autentikasi ---
    Route::post('/logout', [LoginController::class, 'logout']);

    // ==================== MANAJEMEN TERNAK (LIVESTOCK) ====================
    Route::apiResource('/livestocks', LivestockController::class);
    Route::post('/livestocks/{livestock}/record-weight', [LivestockController::class, 'recordWeight']);
    Route::get('/livestocks/{livestock}/weight-history', [LivestockController::class, 'weightHistory']);
    Route::post('/livestocks/{livestock}/predict', [LivestockController::class, 'predictGrowth']);
    Route::post('/livestocks/import', [LivestockController::class, 'import']);

    // ==================== MANAJEMEN KANDANG (PEN) ====================
    Route::apiResource('/pens', PenController::class);
    Route::get('/pens/{pen}/analytics', [PenController::class, 'analytics']);
    Route::post('/pens/import', [PenController::class, 'import']);
    // Route /pens/livestock sudah ditempatkan di public, tidak perlu di sini

    // ==================== MANAJEMEN PAKAN (FEED) ====================
    Route::apiResource('/feeds', FeedController::class);
    Route::post('/feeds/record-feeding', [FeedController::class, 'recordFeeding']);
    Route::post('/feeds/update-stock', [FeedController::class, 'updateStock']);
    Route::post('/feeds/import', [FeedController::class, 'import']);
    Route::get('/feeds/analytics', [FeedController::class, 'analytics']);
    // Route usage-data dan procurement-data sudah public, tidak perlu di sini
    Route::post('/feeds/feeding-record', [FeedController::class, 'storeFeedingRecord']);
    Route::post('/feeds/purchase-record', [FeedController::class, 'storeFeedPurchase']);

    // ==================== PREDIKSI ====================
    Route::prefix('/predictions')->group(function () {
        Route::get('/', [PredictionController::class, 'index']);
        Route::get('/history', [PredictionController::class, 'history']);
        Route::get('/correlation', [PredictionController::class, 'correlation']);
        Route::post('/', [PredictionController::class, 'predict']);
    });

    // ==================== PROGRAM (Fattening & Breeding) ====================
    Route::prefix('/program')->group(function () {
        // Fattening
        Route::get('/fattening', [ProgramController::class, 'fattening']);
        Route::get('/fattening-detailed', [ProgramController::class, 'fatteningDetailed']);
        Route::get('/fattening-timbang', [ProgramController::class, 'fatteningTimbang']);
        Route::get('/fattening-adg-fcr', [ProgramController::class, 'fatteningAdgFcr']);

        // Breeding (umum)
        Route::get('/breeding', [ProgramController::class, 'breeding']);
        Route::get('/family', [ProgramController::class, 'getFamily']);

        // Sub-modul Breeding (selain kawin-ib yang sudah public)
        Route::get('/breeding/indukan', [ProgramController::class, 'breedingIndukan']);
        Route::get('/breeding/indukan/detail', [ProgramController::class, 'breedingIndukanDetail']);
        Route::get('/breeding/pejantan', [ProgramController::class, 'breedingPejantan']);
        Route::get('/breeding/anakan', [ProgramController::class, 'breedingAnakan']);
    });

    // ==================== LOGBOOK ====================
    Route::get('/logbook', [LogbookController::class, 'index']);

    // ==================== HPP (HARGA POKOK PRODUKSI) ====================
    Route::get('/hpp', [HppController::class, 'index']);

    // ==================== CHATBOT (dengan autentikasi) ====================
    Route::post('/chatbot', [ChatbotController::class, 'chat']);

    // ==================== NOTIFIKASI ====================
    Route::prefix('/notifications')->group(function () {
        Route::get('/', [NotifikasiController::class, 'index']);
        Route::get('/unread-count', [NotifikasiController::class, 'unreadCount']);
        Route::post('/{id}/mark-as-read', [NotifikasiController::class, 'markAsRead']);
        Route::post('/mark-all-as-read', [NotifikasiController::class, 'markAllAsRead']);
    });

    // ==================== LAPORAN (REPORTS) ====================
    Route::prefix('/reports')->group(function () {
        Route::get('/summary', [ReportController::class, 'summary']);
        Route::get('/performance', [ReportController::class, 'performance']);
        Route::get('/growth', [ReportController::class, 'growth']);
        Route::get('/data', [ReportController::class, 'getData']);
    });
});

// ==================== FALLBACK UNTUK ENDPOINT YANG TIDAK DIKENAL ====================
Route::fallback(function () {
    return response()->json(['success' => false, 'message' => 'Endpoint not found'], 404);
});
