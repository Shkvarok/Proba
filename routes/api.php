<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LevelController;


Route::get('/test', function(){
    return response()->json(['message' => 'testing'], 200);
});


Route::put('categories/{id}/activate', [CategoryController::class, 'activate'])->name('categories.activate');
Route::put('categories/{id}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');

// Додаткові маршрути для категорій
Route::prefix('categories')->group(function () {
    Route::get('active', [CategoryController::class, 'getActive']);
    Route::get('hierarchy', [CategoryController::class, 'getHierarchy']);
    Route::get('slug/{slug}', [CategoryController::class, 'getBySlug']);
    Route::post('positions', [CategoryController::class, 'updatePositions']);
    Route::put('{id}/toggle-active', [CategoryController::class, 'toggleActive']);
});

// Маршрути для категорій
Route::apiResource('categories', CategoryController::class);

// Маршрути для рівнів
Route::apiResource('levels', LevelController::class);

// Публічні маршрути для авторизації
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login'); 
Route::get('/admins/list', [UserController::class, 'admins']);

// Захищені маршрути
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