<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\QrBatchController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\AnalyticsController;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    
    
    // Rute auth yang butuh token
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
Route::post('/auth/register', [AuthController::class, 'register']);
/*
|--------------------------------------------------------------------------
| PUBLIC (SCAN QR)
|--------------------------------------------------------------------------
| Ini ditaruh di luar middleware auth karena user biasa (pembeli)
| yang akan melakukan scan tanpa perlu login.
*/
Route::post('scan/{code}', [ScanController::class, 'scan']); 
Route::get('/verify/{code}', [App\Http\Controllers\Api\VerificationController::class, 'verify']);
// Catatan: Biasanya scan itu method GET agar mudah diakses via browser/kamera, 
// tapi jika app Anda kirim data lokasi via POST, gunakan POST. 
// Di file ScanController Anda tertulis logic-nya, sesuaikan method-nya.
// Jika ScanController::scan hanya membaca, GET lebih tepat.

/*
|--------------------------------------------------------------------------
| PROTECTED (MERCHANT DASHBOARD)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {



    // Merchant Profile
    Route::get('/merchant/profile', [App\Http\Controllers\Api\MerchantController::class, 'show']);
    Route::post('/merchant/profile', [App\Http\Controllers\Api\MerchantController::class, 'update']);

    
    // Product Management
    Route::apiResource('products', ProductController::class);

    // QR Batch Management
    Route::get('qr-batches', [QrBatchController::class, 'index']);
    Route::post('qr-batches', [QrBatchController::class, 'store']);
    Route::get('qr-batches/{id}', [QrBatchController::class, 'show']); // Pakai {id} agar konsisten dengan controller
    Route::post('qr-batches/{id}/generate', [QrBatchController::class, 'generate']);
    Route::get('/qr-batches/{id}/download-zip', [App\Http\Controllers\Api\QrBatchController::class, 'downloadZip']);

    // QR Code List (Melihat detail tiap QR hasil generate)
    Route::get('qr-codes', [QrCodeController::class, 'index']);
    Route::get('qr-codes/{id}', [QrCodeController::class, 'show']);

    // Analytics / Dashboard
    Route::get('analytics/summary', [AnalyticsController::class, 'summary']);
    Route::get('analytics/product/{id}', [AnalyticsController::class, 'product']);
});