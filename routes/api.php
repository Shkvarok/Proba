<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LevelController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\InstructorController;
use App\Http\Controllers\Api\ProfileController;



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


// Публічні маршрути для курсів (тільки читання)
Route::prefix('courses')->group(function () {
    // GET запити (перегляд, пошук) - доступні всім
    Route::get('/', [CourseController::class, 'index']);
    Route::get('/{id}', [CourseController::class, 'show'])->where('id', '[0-9]+');
    Route::get('/search', [CourseController::class, 'search']);
    Route::get('/category/{categoryId}', [CourseController::class, 'getByCategory'])->where('categoryId', '[0-9]+');
    Route::get('/level/{levelId}', [CourseController::class, 'getByLevel'])->where('levelId', '[0-9]+');
    Route::get('/instructor/{instructorId}', [CourseController::class, 'getByInstructor'])->where('instructorId', '[0-9]+');
});

// Маршрути для курсів, захищені роллю admin або super_admin
Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class.':admin,super_admin'])->group(function () {
    Route::prefix('courses')->group(function () {
        // POST, PUT, DELETE запити - доступні тільки адмінам
        Route::post('/', [CourseController::class, 'store']);
        Route::put('/{id}', [CourseController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [CourseController::class, 'destroy'])->where('id', '[0-9]+');
        Route::put('/{id}/publish', [CourseController::class, 'publish'])->where('id', '[0-9]+');
        Route::put('/{id}/unpublish', [CourseController::class, 'unpublish'])->where('id', '[0-9]+');
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

Route::middleware(['auth:sanctum'])->get('/check-permissions', function (Request $request) {
    $user = $request->user();
    $permissions = [];  // Отримайте список дозволів користувача з бази
    
    // Якщо у вас є метод на отримання дозволів
    if (method_exists($user, 'getPermissions')) {
        $permissions = $user->getPermissions();
    }
    
    return response()->json([
        'user_id' => $user->id,
        'role' => $user->role,
        'permissions' => $permissions,
        // Перевіряємо конкретні дозволи, якщо метод існує
        'can_create_course' => method_exists($user, 'hasPermission') ? $user->hasPermission('create_course') : 'method not found',
        'can_update_course' => method_exists($user, 'hasPermission') ? $user->hasPermission('update_course') : 'method not found',
        'can_delete_course' => method_exists($user, 'hasPermission') ? $user->hasPermission('delete_course') : 'method not found'
    ]);
});


// Маршрути для профілю користувача (додаються до групи auth:sanctum)
Route::middleware(['auth:sanctum'])->group(function () {
    // Профіль користувача
    Route::prefix('profile')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\ProfileController::class, 'getProfile']);
        Route::post('/avatar', [App\Http\Controllers\Api\ProfileController::class, 'updateAvatar']);
        Route::delete('/avatar', [App\Http\Controllers\Api\ProfileController::class, 'deleteAvatar']);
    });
});