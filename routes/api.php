<?php

use App\Http\Controllers\Api\AuthController;
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

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('request-otp', [\App\Http\Controllers\Api\V1\AuthController::class, 'requestOtp']);
        Route::post('verify-otp', [\App\Http\Controllers\Api\V1\AuthController::class, 'verifyOtp']);
        Route::post('resend-otp', [\App\Http\Controllers\Api\V1\AuthController::class, 'resendOtp']);
    });
    
    // Public token bundle listing (no auth required)
    Route::get('tokens/bundles', [\App\Http\Controllers\Api\V1\TokenBundleController::class, 'index']);
    
    // Public currency rate endpoint (no auth required)
    Route::get('currency/rate', [\App\Http\Controllers\Api\V1\CurrencyController::class, 'getRate']);
});

// Protected routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::post('auth/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
    
    // User profile routes
    Route::get('user', [\App\Http\Controllers\Api\V1\UserController::class, 'show']);
    Route::put('user', [\App\Http\Controllers\Api\V1\UserController::class, 'update']);
    Route::post('user/avatar', [\App\Http\Controllers\Api\V1\UserController::class, 'uploadAvatar']);

    // Token routes (legacy - to be migrated to V1)
    Route::prefix('tokens')->group(function () {
        Route::post('consume', [\App\Http\Controllers\Api\TokenController::class, 'consume']);
        Route::post('add', [\App\Http\Controllers\Api\TokenController::class, 'add']);
    });
    
    // Token transaction routes (V1)
    Route::prefix('tokens')->group(function () {
        Route::get('history', [\App\Http\Controllers\Api\V1\TokenTransactionController::class, 'index']);
        Route::get('balance', [\App\Http\Controllers\Api\V1\TokenTransactionController::class, 'balance']);
    });

    // Generation routes
    Route::prefix('generate')->group(function () {
        Route::post('image', [\App\Http\Controllers\Api\V1\GenerationController::class, 'generateImage']);
        Route::post('video', [\App\Http\Controllers\Api\V1\GenerationController::class, 'generateVideo']);
    });

    // Gallery routes
    Route::prefix('gallery')->group(function () {
        Route::post('post', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Gallery post endpoint - to be implemented',
            ]);
        });
        
        Route::get('feed', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Gallery feed endpoint - to be implemented',
            ]);
        });
        
        Route::post('{post_id}/like', function (Request $request, $postId) {
            return response()->json([
                'success' => true,
                'message' => 'Like post endpoint - to be implemented',
                'post_id' => $postId,
            ]);
        });
        
        Route::post('{post_id}/comment', function (Request $request, $postId) {
            return response()->json([
                'success' => true,
                'message' => 'Comment post endpoint - to be implemented',
                'post_id' => $postId,
            ]);
        });
    });
});

// Admin routes
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Token bundle management
    Route::prefix('tokens/bundles')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\V1\TokenBundleController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V1\TokenBundleController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\V1\TokenBundleController::class, 'destroy']);
        Route::post('/{id}/activate', [\App\Http\Controllers\Api\V1\TokenBundleController::class, 'activate']);
        Route::post('/{id}/deactivate', [\App\Http\Controllers\Api\V1\TokenBundleController::class, 'deactivate']);
    });
    
    Route::prefix('sales')->group(function () {
        Route::get('summary', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Sales summary endpoint - to be implemented',
            ]);
        });
    });
    
    Route::prefix('models')->group(function () {
        Route::get('usage', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Models usage endpoint - to be implemented',
            ]);
        });
    });
    
    Route::prefix('tokens')->group(function () {
        Route::get('summary', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Tokens summary endpoint - to be implemented',
            ]);
        });
    });
    
    Route::prefix('users')->group(function () {
        Route::get('summary', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Users summary endpoint - to be implemented',
            ]);
        });
    });
    
    Route::prefix('cost-profit')->group(function () {
        Route::get('summary', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Cost profit summary endpoint - to be implemented',
            ]);
        });
    });
    
    Route::get('system-health', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'System health endpoint - to be implemented',
        ]);
    });
});
