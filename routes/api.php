<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LevelController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\CourseEnrollmentController;
use App\Http\Controllers\Api\PaymentController;

// ========================================
// ТЕСТОВІ ТА ДОПОМІЖНІ МАРШРУТИ
// ========================================

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

// Утиліти для розробки
Route::get('/run-seeders', function () {
    Artisan::call('db:seed');
    return response()->json(['message' => 'Seeders have been run successfully.']);
})->name('run.seeders');

Route::get('/migrate-fresh', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);
    return response()->json(['message' => 'Database has been refreshed and migrations have been re-run.']);
})->name('migrate.fresh');

// ========================================
// ПУБЛІЧНІ МАРШРУТИ (БЕЗ АВТЕНТИФІКАЦІЇ)
// ========================================

// Автентифікація і реєстрація
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Скидання паролю
Route::prefix('password')->group(function () {
    Route::post('/send-reset-code', [PasswordResetController::class, 'sendResetCode']);
    Route::post('/verify-code', [PasswordResetController::class, 'verifyResetCode']);
    Route::post('/reset', [PasswordResetController::class, 'resetPassword']);
});

// Категорії (публічний доступ)
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/active', [CategoryController::class, 'getActive']);
    Route::get('/hierarchy', [CategoryController::class, 'getHierarchy']);
    Route::get('/slug/{slug}', [CategoryController::class, 'getBySlug']);
    Route::get('/{category}', [CategoryController::class, 'show']);
});

// Рівні (публічний доступ)
Route::prefix('levels')->group(function () {
    Route::get('/', [LevelController::class, 'index']);
    Route::get('/{level}', [LevelController::class, 'show']);
});

// Курси (публічний доступ)
Route::prefix('courses')->group(function () {
    Route::get('/', [CourseController::class, 'index']);
    Route::get('/search', [CourseController::class, 'search']);
    Route::get('/category/{categoryId}', [CourseController::class, 'getByCategory'])->where('categoryId', '[0-9]+');
    Route::get('/level/{levelId}', [CourseController::class, 'getByLevel'])->where('levelId', '[0-9]+');
    Route::get('/instructor/{instructorId}', [CourseController::class, 'getByInstructor'])->where('instructorId', '[0-9]+');
    Route::get('/{courseId}/modules', [ModuleController::class, 'index'])->where('courseId', '[0-9]+');
    Route::get('/{id}', [CourseController::class, 'show'])->where('id', '[0-9]+');
});

// Модулі та уроки (публічний доступ)
Route::prefix('modules')->group(function () {
    Route::get('/{moduleId}', [ModuleController::class, 'show'])->where('moduleId', '[0-9]+');
    Route::get('/{moduleId}/lessons', [LessonController::class, 'index'])->where('moduleId', '[0-9]+');
});

Route::prefix('lessons')->group(function () {
    Route::get('/{lessonId}', [LessonController::class, 'show'])->where('lessonId', '[0-9]+');
    Route::get('/{lessonId}/file/{type}', [LessonController::class, 'getFile']);
});

// Платежі - публічні маршрути
Route::prefix('payments')->group(function () {
    // Callback від LiqPay
    Route::post('/liqpay/callback', [PaymentController::class, 'liqpayCallback'])->name('liqpay.callback');
    
    // Публічні маршрути для платежів
    Route::get('/{paymentId}/failed', [PaymentController::class, 'paymentFailed']);
    Route::post('/{paymentId}/retry', [PaymentController::class, 'retryPayment']);
});

// ========================================
// ЗАХИЩЕНІ МАРШРУТИ (ПОТРІБНА АВТЕНТИФІКАЦІЯ)
// ========================================

Route::middleware('auth:sanctum')->group(function () {
    // Базові маршрути автентифікації
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Діагностичний маршрут
    Route::get('/debug-auth', function (Request $request) {
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
    
    // Профіль користувача
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'getProfile']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
    });
    
    // ----------------------------------------
    // Підписки та оплата
    // ----------------------------------------
    
    // Підписки на курси
    Route::prefix('enrollments')->group(function () {
        Route::get('/', [CourseEnrollmentController::class, 'index']);
        Route::get('/course/{courseId}', [CourseEnrollmentController::class, 'checkAccess']);
        Route::post('/free/{courseId}', [CourseEnrollmentController::class, 'enrollFree']);
    });
    
    // Оплата та платежі
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'getUserPayments']);
        Route::post('/course/{courseId}', [PaymentController::class, 'createCoursePayment']);
        Route::post('/course/{courseId}/liqpay', [PaymentController::class, 'initiateCoursePayment']);
        Route::get('/{paymentId}/status', [PaymentController::class, 'checkPaymentStatus']);
        Route::get('/course/{courseId}/success', [PaymentController::class, 'paymentSuccess'])->name('courses.payment.success');
    });
    Route::get('/test-payment-callback/{paymentId}', [PaymentController::class, 'testProcessCallback']);
    // ----------------------------------------
    // Доступ до вмісту курсів (з перевіркою доступу)
    // ----------------------------------------
    Route::middleware('check.course.access')->group(function () {
        Route::get('/courses/{courseId}/modules', [ModuleController::class, 'getModulesByCourse']);
        Route::get('/courses/{courseId}/modules/{moduleId}/lessons', [LessonController::class, 'getLessonsByModule']);
        // Інші маршрути доступу до вмісту курсу...
    });
    
    // ----------------------------------------
    // Для викладачів та адміністраторів
    // ----------------------------------------
    Route::middleware([\App\Http\Middleware\CheckRole::class . ':teacher,admin,super_admin'])->group(function () {
        // Управління курсами
        Route::prefix('courses/manage')->group(function () {
            Route::get('/', [CourseController::class, 'getMyCourses']);
            Route::post('/', [CourseController::class, 'store']);
            Route::put('/{id}', [CourseController::class, 'update'])->where('id', '[0-9]+');
            Route::delete('/{id}', [CourseController::class, 'destroy'])->where('id', '[0-9]+');
            Route::put('/{id}/publish', [CourseController::class, 'publish'])->where('id', '[0-9]+');
            Route::put('/{id}/unpublish', [CourseController::class, 'unpublish'])->where('id', '[0-9]+');
        });
        
        // Управління модулями
        Route::prefix('modules/manage')->group(function () {
            Route::get('/', [ModuleController::class, 'index']);
            Route::post('/', [ModuleController::class, 'store']);
            Route::put('/{id}', [ModuleController::class, 'update']);
            Route::delete('/{id}', [ModuleController::class, 'destroy']);
            Route::put('/{id}/position', [ModuleController::class, 'updatePosition']);
            Route::post('/positions', [ModuleController::class, 'updatePositions']);
        });
        
        // Управління уроками
        Route::prefix('lessons/manage')->group(function () {
            Route::get('/', [LessonController::class, 'index']);
            Route::get('/module/{moduleId}', [LessonController::class, 'index']);
            Route::post('/', [LessonController::class, 'store']);
            Route::put('/{id}', [LessonController::class, 'update']);
            Route::put('/{id}/position', [LessonController::class, 'updatePosition']);
            Route::post('/positions', [LessonController::class, 'updatePositions']);
            Route::delete('/{id}', [LessonController::class, 'destroy']);
        });
    });
    
    // ----------------------------------------
    // Тільки для адміністраторів
    // ----------------------------------------
    Route::middleware([\App\Http\Middleware\CheckRole::class . ':admin,super_admin'])->group(function () {
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
        Route::prefix('categories/manage')->group(function () {
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{category}', [CategoryController::class, 'update']);
            Route::delete('/{category}', [CategoryController::class, 'destroy']);
            Route::post('/positions', [CategoryController::class, 'updatePositions']);
            Route::put('/{id}/toggle-active', [CategoryController::class, 'toggleActive']);
            Route::put('/{id}/activate', [CategoryController::class, 'activate']);
            Route::put('/{id}/deactivate', [CategoryController::class, 'deactivate']);
        });
        
        // Управління рівнями
        Route::prefix('levels/manage')->group(function () {
            Route::post('/', [LevelController::class, 'store']);
            Route::put('/{level}', [LevelController::class, 'update']);
            Route::delete('/{level}', [LevelController::class, 'destroy']);
        });
        
        // Статистика платежів (якщо потрібно)
        Route::get('/admin/payment-stats', [PaymentController::class, 'getPaymentStats']);
    });
});