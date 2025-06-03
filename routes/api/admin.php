<?php
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LevelController;
use App\Http\Controllers\Api\PaymentController;

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->prefix('admin')->group(function () {
    // Управління користувачами
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/admins/list', [UserController::class, 'admins']);
        Route::post('/admins', [UserController::class, 'storeAdmin']);
        Route::post('/teachers', [UserController::class, 'storeTeacher']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::put('/{id}/change-role', [UserController::class, 'changeRole']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });
    
    // Управління категоріями
    Route::prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
        Route::post('/positions', [CategoryController::class, 'updatePositions']);
        Route::put('/{id}/toggle-active', [CategoryController::class, 'toggleActive']);
        Route::put('/{id}/activate', [CategoryController::class, 'activate']);
        Route::put('/{id}/deactivate', [CategoryController::class, 'deactivate']);
    });
    
    // Управління рівнями
    Route::prefix('levels')->group(function () {
        Route::post('/', [LevelController::class, 'store']);
        Route::put('/{level}', [LevelController::class, 'update']);
        Route::delete('/{level}', [LevelController::class, 'destroy']);
    });
    
    // Модерація відгуків
    Route::prefix('moderation')->group(function () {
        Route::get('/reviews/pending', [App\Http\Controllers\Api\ReviewController::class, 'getPendingReviews']);
        Route::get('/comments/pending', [App\Http\Controllers\Api\ReviewController::class, 'getPendingComments']);
        
        Route::put('/reviews/{reviewId}/approve', [App\Http\Controllers\Api\ReviewController::class, 'approveReview']);
        Route::put('/comments/{commentId}/approve', [App\Http\Controllers\Api\ReviewController::class, 'approveComment']);
        Route::delete('/reviews/{reviewId}/reject', [App\Http\Controllers\Api\ReviewController::class, 'rejectReview']);
        Route::delete('/comments/{commentId}/reject', [App\Http\Controllers\Api\ReviewController::class, 'rejectComment']);
    });
    
    // Статистика
    Route::get('/payment-stats', [PaymentController::class, 'getPaymentStats']);
});