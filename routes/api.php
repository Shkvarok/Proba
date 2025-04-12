<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LevelController;

Route::get('/test', function() {
    return response()->json(['message' => 'testing'], 200);
});

// Публічні маршрути для авторизації
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Публічні маршрути для категорій (тільки читання)
Route::prefix('categories')->group(function () {
    // GET запити (перегляд, пошук) - доступні всім
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{category}', [CategoryController::class, 'show']);
    Route::get('/active', [CategoryController::class, 'getActive']);
    Route::get('/hierarchy', [CategoryController::class, 'getHierarchy']);
    Route::get('/slug/{slug}', [CategoryController::class, 'getBySlug']);
});

// Публічні маршрути для рівнів (тільки читання)
Route::prefix('levels')->group(function () {
    // GET запити (перегляд) - доступні всім
    Route::get('/', [LevelController::class, 'index']);
    Route::get('/{level}', [LevelController::class, 'show']);
});

// Маршрути, захищені роллю admin або super_admin
Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class.':admin,super_admin'])->group(function () {
    // Маршрути для категорій (тільки запис)
    Route::prefix('categories')->group(function () {
        // POST, PUT, DELETE запити - доступні тільки адмінам
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
        Route::post('/positions', [CategoryController::class, 'updatePositions']);
        Route::put('/{id}/toggle-active', [CategoryController::class, 'toggleActive']);
        Route::put('/{id}/activate', [CategoryController::class, 'activate']);
        Route::put('/{id}/deactivate', [CategoryController::class, 'deactivate']);
    });

    // Маршрути для рівнів (тільки запис)
    Route::prefix('levels')->group(function () {
        // POST, PUT, DELETE запити - доступні тільки адмінам
        Route::post('/', [LevelController::class, 'store']);
        Route::put('/{level}', [LevelController::class, 'update']);
        Route::delete('/{level}', [LevelController::class, 'destroy']);
    });
});

// Решта захищених маршрутів
Route::middleware('auth:sanctum')->group(function () {
    // Авторизація
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Користувачі
    Route::prefix('users')->group(function () {
        // Список усіх адміністраторів
        Route::get('/admins/list', [UserController::class, 'admins']);
        
        // Створення адміністратора
        Route::post('/admins', [UserController::class, 'storeAdmin']);
        
        // Список усіх користувачів
        Route::get('/', [UserController::class, 'index']);
        
        // Отримання інформації про конкретного користувача
        Route::get('/{id}', [UserController::class, 'show']);
        
        // Оновлення інформації про користувача
        Route::put('/{id}', [UserController::class, 'update']);
        
        // Видалення користувача
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });
});

Route::get('/run-seeders', function () {
    Artisan::call('db:seed');
    return response()->json(['message' => 'Seeders have been run successfully.']);
})->name('run.seeders');

Route::get('/migrate-fresh', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);
    return response()->json(['message' => 'Database has been refreshed and migrations have been re-run.']);
})->name('migrate.fresh');