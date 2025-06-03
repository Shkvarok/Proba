<?php
// routes/api/auth.php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;

Route::prefix('auth')->group(function () {
    // Публічні маршрути автентифікації
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    
    // Скидання паролю
    Route::prefix('password')->group(function () {
        Route::post('/send-reset-code', [PasswordResetController::class, 'sendResetCode']);
        Route::post('/verify-code', [PasswordResetController::class, 'verifyResetCode']);
        Route::post('/reset', [PasswordResetController::class, 'resetPassword']);
    });
    
    // Захищені маршрути
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});