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
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\CourseAccessRequestController;
use App\Http\Controllers\Api\StudentCourseController;

// Тестовий маршрут
Route::get('/test', function() {
    return response()->json(['message' => 'testing'], 200);
});

// ========================================
// Утиліти для розробки
// ========================================
Route::get('/run-seeders', function () {
    Artisan::call('db:seed');
    return response()->json(['message' => 'Seeders have been run successfully.']);
})->name('run.seeders');

Route::get('/migrate-fresh', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);
    return response()->json(['message' => 'Database has been refreshed and migrations have been re-run.']);
})->name('migrate.fresh');

// ========================================
// Маршрути авторизації (публічні)
// ========================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// ========================================
// Публічні маршрути (без автентифікації)
// ========================================

// Категорії (тільки читання)
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{category}', [CategoryController::class, 'show']);
    Route::get('/active', [CategoryController::class, 'getActive']);
    Route::get('/hierarchy', [CategoryController::class, 'getHierarchy']);
    Route::get('/slug/{slug}', [CategoryController::class, 'getBySlug']);
});

// Рівні (тільки читання)
Route::prefix('levels')->group(function () {
    Route::get('/', [LevelController::class, 'index']);
    Route::get('/{level}', [LevelController::class, 'show']);
});

// Курси (тільки читання)
Route::prefix('courses')->group(function () {
    Route::get('/', [CourseController::class, 'index']);
    Route::get('/{id}', [CourseController::class, 'show'])->where('id', '[0-9]+');
    Route::get('/search', [CourseController::class, 'search']);
    Route::get('/category/{categoryId}', [CourseController::class, 'getByCategory'])->where('categoryId', '[0-9]+');
    Route::get('/level/{levelId}', [CourseController::class, 'getByLevel'])->where('levelId', '[0-9]+');
    Route::get('/instructor/{instructorId}', [CourseController::class, 'getByInstructor'])->where('instructorId', '[0-9]+');
});

// Уроки (тільки читання)
Route::prefix('courses/{courseId}/lessons')->group(function () {
    Route::get('/', [LessonController::class, 'index']);
    Route::get('/{lessonId}', [LessonController::class, 'show'])->where('lessonId', '[0-9]+');
});

// ========================================
// Захищені маршрути (потрібна автентифікація)
// ========================================
Route::middleware('auth:sanctum')->group(function () {
    // Автентифікація
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Перевірка дозволів
    Route::get('/check-permissions', function (Request $request) {
        $user = $request->user();
        $permissions = [];
        
        if (method_exists($user, 'getPermissions')) {
            $permissions = $user->getPermissions();
        }
        
        return response()->json([
            'user_id' => $user->id,
            'role' => $user->role,
            'permissions' => $permissions,
            'can_create_course' => method_exists($user, 'hasPermission') ? $user->hasPermission('create_course') : 'method not found',
            'can_update_course' => method_exists($user, 'hasPermission') ? $user->hasPermission('update_course') : 'method not found',
            'can_delete_course' => method_exists($user, 'hasPermission') ? $user->hasPermission('delete_course') : 'method not found'
        ]);
    });
    
    // ----------------------------------------
    // Профіль користувача
    // ----------------------------------------
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'getProfile']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
    });
    
    // ----------------------------------------
    // Користувачі
    // ----------------------------------------
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::get('/admins/list', [UserController::class, 'admins']);
        Route::post('/admins', [UserController::class, 'storeAdmin']);
    });
    
    // ----------------------------------------
    // Уроки
    // ----------------------------------------
    Route::prefix('courses/{courseId}/lessons')->group(function () {
        Route::post('/', [LessonController::class, 'store']);
        Route::put('/{lessonId}', [LessonController::class, 'update'])->where('lessonId', '[0-9]+');
        Route::delete('/{lessonId}', [LessonController::class, 'destroy'])->where('lessonId', '[0-9]+');
        Route::post('/order', [LessonController::class, 'updateOrder']);
    });
    
    // ----------------------------------------
    // Студентські курси та доступи
    // ----------------------------------------
    // Особисті курси студента
    Route::get('/my-courses', [StudentCourseController::class, 'myCourses']);
    
    // Перевірка доступу до курсу
    Route::get('/courses/{courseId}/check-access', [StudentCourseController::class, 'checkAccess']);
    
    // Запити на доступ до курсів
    Route::post('/course-access-requests', [CourseAccessRequestController::class, 'requestAccess']);
    Route::get('/course-access-requests/{id}', [CourseAccessRequestController::class, 'show']);
    
    // ========================================
    // Маршрути для адміністраторів
    // ========================================
    Route::middleware([\App\Http\Middleware\CheckRole::class.':admin,super_admin'])->group(function () {
        // ----------------------------------------
        // Категорії (адміністрування)
        // ----------------------------------------
        Route::prefix('categories')->group(function () {
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{category}', [CategoryController::class, 'update']);
            Route::delete('/{category}', [CategoryController::class, 'destroy']);
            Route::post('/positions', [CategoryController::class, 'updatePositions']);
            Route::put('/{id}/toggle-active', [CategoryController::class, 'toggleActive']);
            Route::put('/{id}/activate', [CategoryController::class, 'activate']);
            Route::put('/{id}/deactivate', [CategoryController::class, 'deactivate']);
        });
        
        // ----------------------------------------
        // Рівні (адміністрування)
        // ----------------------------------------
        Route::prefix('levels')->group(function () {
            Route::post('/', [LevelController::class, 'store']);
            Route::put('/{level}', [LevelController::class, 'update']);
            Route::delete('/{level}', [LevelController::class, 'destroy']);
        });
        
        // ----------------------------------------
        // Курси (адміністрування)
        // ----------------------------------------
        Route::prefix('courses')->group(function () {
            Route::post('/', [CourseController::class, 'store']);
            Route::put('/{id}', [CourseController::class, 'update'])->where('id', '[0-9]+');
            Route::delete('/{id}', [CourseController::class, 'destroy'])->where('id', '[0-9]+');
            Route::put('/{id}/publish', [CourseController::class, 'publish'])->where('id', '[0-9]+');
            Route::put('/{id}/unpublish', [CourseController::class, 'unpublish'])->where('id', '[0-9]+');
        });
        
        // ----------------------------------------
        // Запити на доступ (адміністрування)
        // ----------------------------------------
        Route::get('/my-access-requests', [CourseAccessRequestController::class, 'index']);
        Route::put('/course-access-requests/{id}/approve', [CourseAccessRequestController::class, 'approve']);
        Route::put('/course-access-requests/{id}/reject', [CourseAccessRequestController::class, 'reject']);
        
        // ----------------------------------------
        // Доступи студентів (адміністрування)
        // ----------------------------------------
        Route::get('/courses/{courseId}/students', [StudentCourseController::class, 'courseStudents']);
        Route::post('/student-courses', [StudentCourseController::class, 'grantAccess']);
        Route::put('/student-courses/{id}/revoke', [StudentCourseController::class, 'revokeAccess']);
    });
});