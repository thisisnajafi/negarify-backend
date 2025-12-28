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
        Route::post('audio', [\App\Http\Controllers\Api\V1\GenerationController::class, 'generateAudio']);
        
        // Job management routes
        Route::get('jobs', [\App\Http\Controllers\Api\V1\GenerationJobController::class, 'index']);
        Route::get('jobs/{id}', [\App\Http\Controllers\Api\V1\GenerationJobController::class, 'show']);
        Route::post('jobs/{id}/cancel', [\App\Http\Controllers\Api\V1\GenerationJobController::class, 'cancel']);
        Route::post('jobs/{id}/retry', [\App\Http\Controllers\Api\V1\GenerationJobController::class, 'retry']);
    });

    // Gallery routes
    Route::prefix('gallery')->group(function () {
        // Gallery post management
        Route::post('post', [\App\Http\Controllers\Api\V1\GalleryPostController::class, 'store']);
        Route::get('posts/{id}', [\App\Http\Controllers\Api\V1\GalleryPostController::class, 'show']);
        Route::put('posts/{id}', [\App\Http\Controllers\Api\V1\GalleryPostController::class, 'update']);
        Route::delete('posts/{id}', [\App\Http\Controllers\Api\V1\GalleryPostController::class, 'destroy']);
        Route::get('my-posts', [\App\Http\Controllers\Api\V1\GalleryPostController::class, 'myPosts']);
        
        // Feed routes
        Route::get('feed', [\App\Http\Controllers\Api\V1\FeedController::class, 'index']);
        Route::post('feed/copy-prompt', [\App\Http\Controllers\Api\V1\FeedController::class, 'copyPrompt']);
        Route::post('feed/copy-model', [\App\Http\Controllers\Api\V1\FeedController::class, 'copyModel']);
        
        // Social features
        Route::post('{id}/like', [\App\Http\Controllers\Api\V1\LikeController::class, 'store']);
        Route::delete('{id}/like', [\App\Http\Controllers\Api\V1\LikeController::class, 'destroy']);
        Route::post('{id}/comment', [\App\Http\Controllers\Api\V1\CommentController::class, 'store']);
        Route::delete('comments/{id}', [\App\Http\Controllers\Api\V1\CommentController::class, 'destroy']);
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
    
    // Gallery curation
    Route::prefix('gallery')->group(function () {
        Route::post('/{id}/curate', [\App\Http\Controllers\Api\V1\AdminGalleryController::class, 'curate']);
        Route::post('/{id}/uncurate', [\App\Http\Controllers\Api\V1\AdminGalleryController::class, 'uncurate']);
        Route::post('/{id}/feature', [\App\Http\Controllers\Api\V1\AdminGalleryController::class, 'feature']);
        Route::post('/{id}/unfeature', [\App\Http\Controllers\Api\V1\AdminGalleryController::class, 'unfeature']);
        Route::post('/bulk-curate', [\App\Http\Controllers\Api\V1\AdminGalleryController::class, 'bulkCurate']);
        Route::post('/bulk-uncurate', [\App\Http\Controllers\Api\V1\AdminGalleryController::class, 'bulkUncurate']);
        Route::get('/curated', [\App\Http\Controllers\Api\V1\AdminGalleryController::class, 'curated']);
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
