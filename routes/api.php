<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LevelController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\LessonController;


// Тестовий маршрут
Route::get('/test', function() {
    return response()->json(['message' => 'testing'], 200);
});

Route::get('/test-routes', function() {
    $routes = [];
    
    foreach (Route::getRoutes() as $route) {
        if (strpos($route->uri, 'api/users') !== false) {
            $routes[] = [
                'uri' => $route->uri,
                'methods' => $route->methods,
                'action' => $route->getActionName()
            ];
        }
    }
    
    return response()->json([
        'routes' => $routes
    ]);
});
// Додайте перед маршрутами для адміністраторів 
Route::middleware('auth:sanctum')->get('/debug-auth', function (Request $request) {
    $user = $request->user()->load('role');
    return response()->json([
        'user_id' => $user->id,
        'email' => $user->email,
        'role_id' => $user->role_id,
        'role' => $user->role ? $user->role->name : null,
        'is_admin' => $user->isAdmin(),
        'is_super_admin' => $user->hasRole('super_admin')
    ]);
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

    // Маршрути для скидання паролю
    Route::post('/password/send-reset-code', [PasswordResetController::class, 'sendResetCode']);
    Route::post('/password/verify-code', [PasswordResetController::class, 'verifyResetCode']);
    Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);

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
        // Список усіх курсів
        Route::get('/', [CourseController::class, 'index']);

        //Інформація про модуль за його ID
        Route::get('/modules/{moduleId}', [ModuleController::class, 'show'])->where('moduleId', '[0-9]+');
        
        // Інформація про уроки модуля
        Route::get('/modules/{moduleId}/lessons', [LessonController::class, 'index'])->where('moduleId', '[0-9]+');
       
        // Пошук курсів
        Route::get('/search', [CourseController::class, 'search']);

        // Курси за категорією
        Route::get('/category/{categoryId}', [CourseController::class, 'getByCategory'])->where('categoryId', '[0-9]+');

        // Курси за рівнем
        Route::get('/level/{levelId}', [CourseController::class, 'getByLevel'])->where('levelId', '[0-9]+');

        // Курси за інструктором
        Route::get('/instructor/{instructorId}', [CourseController::class, 'getByInstructor'])->where('instructorId', '[0-9]+');

        // Публічний доступ до модулів і уроків (структура курсу)
        Route::get('/{courseId}/modules', [ModuleController::class, 'index'])->where('courseId', '[0-9]+');

        Route::get('/lessons/{lessonId}', [LessonController::class, 'show'])->where('lessonId', '[0-9]+');

        // Останній — показ конкретного курсу
        Route::get('/{id}', [CourseController::class, 'show'])->where('id', '[0-9]+');
    });

    // ========================================
    // Захищені маршрути (потрібна автентифікація)
    // ========================================
    Route::middleware('auth:sanctum')->group(function () {
        // Автентифікація
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        
        // ----------------------------------------
        // Профіль користувача
        // ----------------------------------------
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'getProfile']);
            Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
            Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
        });

        // ----------------------------------------
        // Для викладачів та адміністраторів
        // ----------------------------------------

        Route::middleware(['auth:sanctum',\App\Http\Middleware\CheckRole::class . ':teacher,admin,super_admin'])->group(function (){
        // Курси (для викладачів і адміністраторів)
        Route::prefix('teacher')->group(function () {
            // Управління власними курсами
            Route::get('/courses', [CourseController::class, 'getMyCourses']);
            Route::post('/courses', [CourseController::class, 'store']);
            Route::put('/courses/{id}', [CourseController::class, 'update'])->where('id', '[0-9]+');
            Route::delete('/courses/{id}', [CourseController::class, 'destroy'])->where('id', '[0-9]+');
            Route::put('/courses/{id}/publish', [CourseController::class, 'publish'])->where('id', '[0-9]+');
            Route::put('/courses/{id}/unpublish', [CourseController::class, 'unpublish'])->where('id', '[0-9]+');
            
            // Управління модулями
            Route::get('/modules', [ModuleController::class, 'index']);
            Route::post('/modules', [ModuleController::class, 'store']);
            Route::put('/modules/{id}', [ModuleController::class, 'update']);
            Route::delete('/modules/{id}', [ModuleController::class, 'destroy']);
            Route::post('/modules/positions', [ModuleController::class, 'updatePositions']);
            
            // Управління уроками
            Route::get('/lessons', [LessonController::class, 'index']);
            Route::post('/lessons', [LessonController::class, 'store']);
            Route::put('/lessons/{id}', [LessonController::class, 'update']);
            Route::delete('/lessons/{id}', [LessonController::class, 'destroy']);
            Route::post('/lessons/positions', [LessonController::class, 'updatePositions']);
        });
    });

        // ========================================
        // Маршрути для адміністраторів
        // ========================================
    Route::middleware(['auth:sanctum',\App\Http\Middleware\CheckRole::class . ':admin,super_admin' ])->group(function () {     
            // ----------------------------------------
            // Користувачі (адміністрування)
            // ----------------------------------------
            Route::prefix('users')->group(function () {
                    // Спочатку конкретні маршрути
                    Route::get('/admins/list', [UserController::class, 'admins']);
                    Route::post('/admins', [UserController::class, 'storeAdmin']);
                    Route::post('/teachers', [UserController::class, 'storeTeacher']);
                    
                    Route::put('/{id}/change-role', [UserController::class, 'changeRole']);
                    
                    // Потім загальні маршрути
                    Route::get('/', [UserController::class, 'index']);
                    Route::get('/{id}', [UserController::class, 'show']);
                    Route::put('/{id}', [UserController::class, 'update']);
                    Route::delete('/{id}', [UserController::class, 'destroy']);   });
        
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
             Route::prefix('courses')->group(function () {
             Route::post('/', [CourseController::class, 'store']);
             Route::put('/{id}', [CourseController::class, 'update'])->where('id', '[0-9]+');
             Route::delete('/{id}', [CourseController::class, 'destroy'])->where('id', '[0-9]+');
             Route::put('/{id}/publish', [CourseController::class, 'publish'])->where('id', '[0-9]+');
             Route::put('/{id}/unpublish', [CourseController::class, 'unpublish'])->where('id', '[0-9]+');
        });
        
            // Адміністрування модулів і уроків (з правами на всі курси)
            Route::prefix('admin')->group(function () {
                // Управління модулями
                Route::get('/modules', [ModuleController::class, 'index']);
                Route::post('/modules', [ModuleController::class, 'store']);
                Route::put('/modules/{id}', [ModuleController::class, 'update']);
                Route::delete('/modules/{id}', [ModuleController::class, 'destroy']);
                Route::post('/modules/positions', [ModuleController::class, 'updatePositions']);
                
                // Управління уроками
                Route::get('/lessons', [LessonController::class, 'index']);
                Route::post('/lessons', [LessonController::class, 'store']);
                Route::put('/lessons/{id}', [LessonController::class, 'update']);
                Route::delete('/lessons/{id}', [LessonController::class, 'destroy']);
                Route::post('/lessons/positions', [LessonController::class, 'updatePositions']);
            });
        });

        
    });