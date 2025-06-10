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
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\InternalTestController;
use App\Http\Controllers\Api\TestMediaController;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckCourseAccess;

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
    
    // Публічний доступ до відгуків курсу
    Route::get('/{courseId}/reviews', [ReviewController::class, 'getCourseReviews']);
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
    
    // ========================================
    // БАЗОВІ МАРШРУТИ АВТЕНТИФІКАЦІЇ
    // ========================================
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
    
    // ========================================
    // ПРОФІЛЬ КОРИСТУВАЧА
    // ========================================
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'getProfile']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
    });
    
    // ========================================
    // ПІДПИСКИ ТА ОПЛАТА
    // ========================================
    
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
        
        // Тестова відповідь для обробки платежів які оплатили через LiqPay
        Route::get('/test-payment-callback/{paymentId}', [PaymentController::class, 'testProcessCallback']);
    });
    
    // ========================================
    // ТЕСТОВІ МАРШРУТИ ДЛЯ ДІАГНОСТИКИ
    // ========================================
    Route::get('/test-access/{testId}', function($testId) {
        $user = Auth::user();
        
        try {
            $test = \App\Models\InternalTest::with('lesson.module.course')->findOrFail($testId);
            $course = $test->lesson->module->course;
            
            // Перевірка підписки
            $enrollment = \App\Models\CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('is_active', true)
                ->first();
            
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role->name ?? null,
                ],
                'test' => [
                    'id' => $test->id,
                    'title' => $test->title,
                    'status' => $test->status,
                ],
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'instructor_id' => $course->instructor_id,
                ],
                'enrollment' => $enrollment ? [
                    'id' => $enrollment->id,
                    'is_active' => $enrollment->is_active,
                    'expires_at' => $enrollment->expires_at,
                ] : null,
                'access_checks' => [
                    'is_admin' => $user->hasRole('admin') || $user->hasRole('super_admin'),
                    'is_teacher' => $user->hasRole('teacher'),
                    'is_course_instructor' => $user->hasRole('teacher') && $course->instructor_id === $user->id,
                    'has_enrollment' => $enrollment !== null,
                    'test_is_active' => $test->status === 'active',
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ], 500);
        }
    });
    
    Route::get('/my-enrollments', function() {
        $user = Auth::user();
        
        $enrollments = \App\Models\CourseEnrollment::where('user_id', $user->id)
            ->with('course')
            ->get();
            
        return response()->json([
            'user_id' => $user->id,
            'enrollments' => $enrollments->map(function($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'course_id' => $enrollment->course_id,
                    'course_title' => $enrollment->course->title,
                    'is_active' => $enrollment->is_active,
                    'expires_at' => $enrollment->expires_at,
                    'enrollment_type' => $enrollment->enrollment_type,
                ];
            })
        ]);
    });
    
    Route::get('/test-routes-check', function() {
        $routes = [];
        
        foreach (Route::getRoutes() as $route) {
            if (strpos($route->uri, 'api/tests') !== false || 
                strpos($route->uri, 'api/internal-tests') !== false) {
                $routes[] = [
                    'uri' => $route->uri,
                    'methods' => $route->methods,
                    'action' => $route->getActionName(),
                    'middleware' => $route->middleware()
                ];
            }
        }
        
        return response()->json([
            'test_routes' => $routes
        ]);
    });

    // ========================================
    // ДОСТУП ДО ВМІСТУ КУРСІВ (З ПЕРЕВІРКОЮ ДОСТУПУ)
    // ========================================
    Route::middleware(CheckCourseAccess::class)->group(function () {
        
        // Навчальний контент
        Route::prefix('lern')->group(function () {
            Route::get('/courses/{courseId}/modules', [ModuleController::class, 'getModulesByCourse']);
            Route::get('/courses/{courseId}/modules/{moduleId}/lessons', [LessonController::class, 'getLessonsByModule']);
        });

        // ========================================
        // ПРОХОДЖЕННЯ ТЕСТІВ (для студентів з доступом до курсу)
        // ========================================
        Route::prefix('tests')->group(function () {
            
            // Перегляд тесту перед початком
            Route::get('/{testId}', [InternalTestController::class, 'show']);
            
            // Початок тесту
            Route::post('/{testId}/start', [InternalTestController::class, 'startAttempt']);
            
            // Відповідь на питання
            Route::post('/{testId}/attempts/{attemptId}/answer', [InternalTestController::class, 'submitAnswer']);
            
            // Завершення тесту
            Route::post('/{testId}/attempts/{attemptId}/finish', [InternalTestController::class, 'finishAttempt']);
            
            // Отримання результатів
            Route::get('/{testId}/attempts/{attemptId}/results', [InternalTestController::class, 'getResults']);
            
            // Історія спроб користувача
            Route::get('/{testId}/my-attempts', [InternalTestController::class, 'getUserAttempts']);
            
            // Отримання поточного стану тесту (для продовження)
            Route::get('/{testId}/current-attempt', [InternalTestController::class, 'getCurrentAttempt']);
        });
        
        // ========================================
        // МЕДІАФАЙЛИ ТЕСТІВ (з перевіркою доступу)
        // ========================================
        Route::prefix('test-media')->group(function () {
            Route::get('/question/{questionId}', [TestMediaController::class, 'getQuestionMedia']);
            Route::get('/answer/{answerId}', [TestMediaController::class, 'getAnswerMedia']);
        });
    });
    
    // ========================================
    // ВІДГУКИ КОРИСТУВАЧІВ
    // ========================================
    Route::prefix('reviews')->group(function () {
        // Додати відгук до курсу (тільки для користувачів, що мають доступ до курсу)
        Route::post('/course/{courseId}', [ReviewController::class, 'storeReview'])
            ->middleware(\App\Http\Middleware\CheckCourseReviewAccess::class);
        
        // Оновити свій відгук
        Route::put('/{reviewId}', [ReviewController::class, 'updateReview']);
        
        // Видалити свій відгук
        Route::delete('/{reviewId}', [ReviewController::class, 'deleteReview']);
        
        // Коментарі до відгуків
        Route::post('/{reviewId}/comments', [ReviewController::class, 'storeComment']);
        Route::put('/comments/{commentId}', [ReviewController::class, 'updateComment']);
        Route::delete('/comments/{commentId}', [ReviewController::class, 'deleteComment']);
    });
    
    // ========================================
    // ДЛЯ ВИКЛАДАЧІВ ТА АДМІНІСТРАТОРІВ
    // ========================================
    Route::middleware([CheckRole::class . ':teacher,admin,super_admin'])->group(function () {
        
        // ========================================
        // УПРАВЛІННЯ КУРСАМИ
        // ========================================
        Route::prefix('courses/manage')->group(function () {
            Route::get('/', [CourseController::class, 'getMyCourses']);
            Route::post('/', [CourseController::class, 'store']);
            Route::put('/{id}', [CourseController::class, 'update'])->where('id', '[0-9]+');
            Route::delete('/{id}', [CourseController::class, 'destroy'])->where('id', '[0-9]+');
            Route::put('/{id}/publish', [CourseController::class, 'publish'])->where('id', '[0-9]+');
            Route::put('/{id}/unpublish', [CourseController::class, 'unpublish'])->where('id', '[0-9]+');
        });
        
        // ========================================
        // УПРАВЛІННЯ МОДУЛЯМИ
        // ========================================
        Route::prefix('modules/manage')->group(function () {
            Route::get('/', [ModuleController::class, 'index']);
            Route::post('/', [ModuleController::class, 'store']);
            Route::put('/{id}', [ModuleController::class, 'update']);
            Route::delete('/{id}', [ModuleController::class, 'destroy']);
            Route::put('/{id}/position', [ModuleController::class, 'updatePosition']);
            Route::post('/positions', [ModuleController::class, 'updatePositions']);
        });
        
        // ========================================
        // УПРАВЛІННЯ УРОКАМИ
        // ========================================
        Route::prefix('lessons/manage')->group(function () {
            Route::get('/', [LessonController::class, 'index']);
            Route::get('/module/{moduleId}', [LessonController::class, 'index']);
            Route::post('/', [LessonController::class, 'store']);
            Route::put('/{id}', [LessonController::class, 'update']);
            Route::put('/{id}/position', [LessonController::class, 'updatePosition']);
            Route::post('/positions', [LessonController::class, 'updatePositions']);
            Route::delete('/{id}', [LessonController::class, 'destroy']);
        });

        // ========================================
        // ВНУТРІШНІ ТЕСТИ (CRUD операції)
        // ========================================
        Route::prefix('internal-tests')->group(function () {
            // Основні CRUD операції
            Route::post('/', [InternalTestController::class, 'store']);
            Route::get('/{id}', [InternalTestController::class, 'show']);
            Route::put('/{id}', [InternalTestController::class, 'update']);
            Route::delete('/{id}', [InternalTestController::class, 'destroy']);
            
            // Управління питаннями
            Route::post('/{testId}/questions', [InternalTestController::class, 'addQuestion']);
            Route::put('/{testId}/questions/{questionId}', [InternalTestController::class, 'updateQuestion']);
            Route::delete('/{testId}/questions/{questionId}', [InternalTestController::class, 'deleteQuestion']);
            
            // Додаткові функції управління
            Route::put('/{testId}/questions/order', [InternalTestController::class, 'updateQuestionsOrder']);
            Route::post('/{testId}/duplicate', [InternalTestController::class, 'duplicateTest']);
            
            // Аналітика та статистика (тільки для викладачів своїх тестів та адміністраторів)
            Route::get('/{testId}/analytics', [InternalTestController::class, 'getAnalytics']);
            Route::get('/{testId}/attempts', [InternalTestController::class, 'getAllAttempts']);
        });

        // ========================================
        // УПРАВЛІННЯ МЕДІАФАЙЛАМИ ТЕСТІВ
        // ========================================
        Route::prefix('test-media')->group(function () {
            Route::post('/question/{questionId}/upload', [TestMediaController::class, 'uploadQuestionMedia']);
            Route::post('/answer/{answerId}/upload', [TestMediaController::class, 'uploadAnswerMedia']);
            Route::delete('/question/{questionId}/media', [TestMediaController::class, 'deleteQuestionMedia']);
            Route::delete('/answer/{answerId}/media', [TestMediaController::class, 'deleteAnswerMedia']);
            Route::get('/stats', [TestMediaController::class, 'getMediaStats']);
            Route::post('/cleanup', [TestMediaController::class, 'cleanupOrphanedFiles']);
        });
    });
    
    // ========================================
    // ТІЛЬКИ ДЛЯ АДМІНІСТРАТОРІВ
    // ========================================
    Route::middleware([CheckRole::class . ':admin,super_admin'])->group(function () {
        
        // ========================================
        // УПРАВЛІННЯ КОРИСТУВАЧАМИ
        // ========================================
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
        
        // ========================================
        // УПРАВЛІННЯ КАТЕГОРІЯМИ
        // ========================================
        Route::prefix('categories/manage')->group(function () {
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{category}', [CategoryController::class, 'update']);
            Route::delete('/{category}', [CategoryController::class, 'destroy']);
            Route::post('/positions', [CategoryController::class, 'updatePositions']);
            Route::put('/{id}/toggle-active', [CategoryController::class, 'toggleActive']);
            Route::put('/{id}/activate', [CategoryController::class, 'activate']);
            Route::put('/{id}/deactivate', [CategoryController::class, 'deactivate']);
        });
        
        // ========================================
        // УПРАВЛІННЯ РІВНЯМИ
        // ========================================
        Route::prefix('levels/manage')->group(function () {
            Route::post('/', [LevelController::class, 'store']);
            Route::put('/{level}', [LevelController::class, 'update']);
            Route::delete('/{level}', [LevelController::class, 'destroy']);
        });
        
        // ========================================
        // МОДЕРАЦІЯ ВІДГУКІВ
        // ========================================
        Route::prefix('moderation')->group(function () {
            // Отримання списків відгуків і коментарів, які очікують модерації
            Route::get('/reviews/pending', [ReviewController::class, 'getPendingReviews']);
            Route::get('/comments/pending', [ReviewController::class, 'getPendingComments']);
            
            // Схвалення/відхилення відгуків і коментарів
            Route::put('/reviews/{reviewId}/approve', [ReviewController::class, 'approveReview']);
            Route::put('/comments/{commentId}/approve', [ReviewController::class, 'approveComment']);
            Route::delete('/reviews/{reviewId}/reject', [ReviewController::class, 'rejectReview']);
            Route::delete('/comments/{commentId}/reject', [ReviewController::class, 'rejectComment']);
        });
        
        // ========================================
        // СТАТИСТИКА ПЛАТЕЖІВ
        // ========================================
        Route::get('/admin/payment-stats', [PaymentController::class, 'getPaymentStats']);
    });
});