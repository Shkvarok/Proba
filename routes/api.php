<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
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

/**
 * Базова перевірка роботи API
 */
Route::get('/test', function() {
    return response()->json(['message' => 'testing'], 200);
});

/**
 * Адміністративні команди Artisan
 */

// Створення символічного посилання для storage
Route::get('/storage-link', function () {
    try {
        Artisan::call('storage:link');
        return response()->json(['message' => 'Storage link created successfully.']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
})->name('storage.link');

// Запуск міграцій
Route::get('/run-migrations', function () {
    Artisan::call('migrate');
    return response()->json(['message' => 'Migrations have been run successfully.']);
})->name('run.migrations');

// Запуск сідерів
Route::get('/run-seeders', function () {
    Artisan::call('db:seed');
    return response()->json(['message' => 'Seeders have been run successfully.']);
})->name('run.seeders');

// Повне перестворення БД
Route::get('/migrate-fresh', function () {
    try {
        Artisan::call('migrate:fresh', ['--force' => true]);
        return response()->json(['message' => 'Database has been refreshed and migrations have been re-run.']);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to refresh database',
            'message' => $e->getMessage(),
        ], 500);
    }
})->name('migrate.fresh');

// ========================================
// ПУБЛІЧНІ МАРШРУТИ (БЕЗ АВТЕНТИФІКАЦІЇ)
// ========================================

/*
|--------------------------------------------------------------------------
| Автентифікація та реєстрація
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

/*
|--------------------------------------------------------------------------
| Скидання паролю
|--------------------------------------------------------------------------
*/

Route::prefix('password')->group(function () {
    Route::post('/send-reset-code', [PasswordResetController::class, 'sendResetCode']);
    Route::post('/verify-code', [PasswordResetController::class, 'verifyResetCode']);
    Route::post('/reset', [PasswordResetController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| Категорії (публічний доступ)
|--------------------------------------------------------------------------
*/

Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);                    // Всі категорії
    Route::get('/active', [CategoryController::class, 'getActive']);          // Тільки активні
    Route::get('/hierarchy', [CategoryController::class, 'getHierarchy']);    // Ієрархічна структура
    Route::get('/slug/{slug}', [CategoryController::class, 'getBySlug']);     // За slug
    Route::get('/{category}', [CategoryController::class, 'show']);           // Конкретна категорія
});

/*
|--------------------------------------------------------------------------
| Рівні складності (публічний доступ)
|--------------------------------------------------------------------------
*/

Route::prefix('levels')->group(function () {
    Route::get('/', [LevelController::class, 'index']);        // Всі рівні
    Route::get('/{level}', [LevelController::class, 'show']);  // Конкретний рівень
});

/*
|--------------------------------------------------------------------------
| Курси (публічний доступ)
|--------------------------------------------------------------------------
*/

Route::prefix('courses')->group(function () {
    // Базові списки курсів
    Route::get('/', [CourseController::class, 'index']);                          // СТАРИЙ API з повною інформацією
    Route::get('/only', [CourseController::class, 'indexOnly']);                  // НОВИЙ API без модулів/уроків
    
    // Пошук та фільтрація
    Route::get('/search', [CourseController::class, 'search']);                   // Пошук курсів
    Route::get('/popular', [CourseController::class, 'getPopular']);              // Популярні курси
    Route::get('/featured', [CourseController::class, 'getFeatured']);            // Рекомендовані курси
    
    // Фільтрація за параметрами
    Route::get('/category/{categoryId}', [CourseController::class, 'getByCategory'])->where('categoryId', '[0-9]+');
    Route::get('/level/{levelId}', [CourseController::class, 'getByLevel'])->where('levelId', '[0-9]+');
    Route::get('/instructor/{instructorId}', [CourseController::class, 'getByInstructor'])->where('instructorId', '[0-9]+');
    
    // Інформація про курс
    Route::get('/{id}', [CourseController::class, 'show'])->where('id', '[0-9]+');                          // Базова інформація
    Route::get('/{id}/content', [CourseController::class, 'showWithContent'])->where('id', '[0-9]+');       // З модулями і уроками
    Route::get('/{id}/stats', [CourseController::class, 'getContentStats'])->where('id', '[0-9]+');         // Статистика контенту
    
    // Модулі курсу
    Route::get('/{courseId}/modules', [ModuleController::class, 'index'])->where('courseId', '[0-9]+');
    
    // Відгуки курсу (публічний доступ)
    Route::get('/{courseId}/reviews', [ReviewController::class, 'getCourseReviews']);
});

/*
|--------------------------------------------------------------------------
| Модулі та уроки (публічний доступ)
|--------------------------------------------------------------------------
*/

Route::prefix('modules')->group(function () {
    Route::get('/{moduleId}', [ModuleController::class, 'show'])->where('moduleId', '[0-9]+');
    Route::get('/{moduleId}/lessons', [LessonController::class, 'index'])->where('moduleId', '[0-9]+');
});

Route::prefix('lessons')->group(function () {
    Route::get('/{lessonId}', [LessonController::class, 'show'])->where('lessonId', '[0-9]+');
    Route::get('/{lessonId}/file/{type}', [LessonController::class, 'getFile']);
});

/*
|--------------------------------------------------------------------------
| Платежі - публічні маршрути
|--------------------------------------------------------------------------
*/

Route::prefix('payments')->group(function () {
    // Callback від платіжної системи
    Route::post('/liqpay/callback', [PaymentController::class, 'liqpayCallback'])->name('liqpay.callback');
    
    // Обробка результатів платежів
    Route::get('/{paymentId}/failed', [PaymentController::class, 'paymentFailed']);
    Route::post('/{paymentId}/retry', [PaymentController::class, 'retryPayment']);
});

// ========================================
// ЗАХИЩЕНІ МАРШРУТИ (ПОТРІБНА АВТЕНТИФІКАЦІЯ)
// ========================================

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Базові маршрути автентифікації
    |--------------------------------------------------------------------------
    */
    
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Перевірка доступу користувача до курсу
    Route::get('/courses/{id}/access', [CourseController::class, 'checkUserAccess'])->where('id', '[0-9]+');
    
    /*
    |--------------------------------------------------------------------------
    | Профіль користувача
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'getProfile']);           // Отримати профіль
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);  // Оновити аватар
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']); // Видалити аватар
    });
    
    /*
    |--------------------------------------------------------------------------
    | Підписки та записи на курси
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('enrollments')->group(function () {
        Route::get('/', [CourseEnrollmentController::class, 'index']);                      // Мої підписки
        Route::get('/course/{courseId}', [CourseEnrollmentController::class, 'checkAccess']); // Перевірка доступу
        Route::post('/free/{courseId}', [CourseEnrollmentController::class, 'enrollFree']);   // Безкоштовна підписка
    });
    
    /*
    |--------------------------------------------------------------------------
    | Оплата та платежі
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'getUserPayments']);                        // Мої платежі
        Route::post('/course/{courseId}', [PaymentController::class, 'createCoursePayment']);  // Створити платіж
        Route::post('/course/{courseId}/liqpay', [PaymentController::class, 'initiateCoursePayment']); // Ініціювати оплату через LiqPay
        Route::get('/{paymentId}/status', [PaymentController::class, 'checkPaymentStatus']);   // Статус платежу
        Route::post('/{paymentId}/confirm', [PaymentController::class, 'confirmPayment']);     // Підтвердити платіж
        Route::get('/course/{courseId}/success', [PaymentController::class, 'paymentSuccess'])->name('courses.payment.success');
        
        // Тестова відповідь для обробки платежів
        Route::get('/test-payment-callback/{paymentId}', [PaymentController::class, 'testProcessCallback']);
    });
    
    /*
    |--------------------------------------------------------------------------
    | Тестові маршрути для діагностики
    |--------------------------------------------------------------------------
    */
    
    // Тестування доступу до тестів
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
                    'enrollment_type' => $enrollment->enrollment_type,
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
    
    // Мої підписки на курси
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
    
    // Перевірка тестових маршрутів
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

    /*
    |--------------------------------------------------------------------------
    | Доступ до вмісту курсів (з перевіркою доступу)
    |--------------------------------------------------------------------------
    */
    
    Route::middleware(CheckCourseAccess::class)->group(function () {
        
        // Навчальний контент
        Route::prefix('lern')->group(function () {
            Route::get('/courses/{courseId}/modules', [ModuleController::class, 'getModulesByCourse']);
            Route::get('/courses/{courseId}/modules/{moduleId}/lessons', [LessonController::class, 'getLessonsByModule']);
        });

        /*
        |--------------------------------------------------------------------------
        | Проходження тестів (для студентів з доступом до курсу)
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('tests')->group(function () {
            Route::get('/{testId}', [InternalTestController::class, 'show']);                                      // Перегляд тесту
            Route::post('/{testId}/start', [InternalTestController::class, 'startAttempt']);                       // Почати тест
            Route::post('/{testId}/attempts/{attemptId}/answer', [InternalTestController::class, 'submitAnswer']); // Відповісти на питання
            Route::post('/{testId}/attempts/{attemptId}/finish', [InternalTestController::class, 'finishAttempt']); // Завершити тест
            Route::get('/{testId}/attempts/{attemptId}/results', [InternalTestController::class, 'getResults']);   // Результати
            Route::get('/{testId}/my-attempts', [InternalTestController::class, 'getUserAttempts']);               // Історія спроб
            Route::get('/{testId}/current-attempt', [InternalTestController::class, 'getCurrentAttempt']);         // Поточна спроба
        });
        
        /*
        |--------------------------------------------------------------------------
        | Медіафайли тестів (з перевіркою доступу)
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('test-media')->group(function () {
            Route::get('/question/{questionId}', [TestMediaController::class, 'getQuestionMedia']); // Медіа питання
            Route::get('/answer/{answerId}', [TestMediaController::class, 'getAnswerMedia']);       // Медіа відповіді
        });
    });
    
    /*
    |--------------------------------------------------------------------------
    | Відгуки користувачів
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('reviews')->group(function () {
        // Операції з відгуками
        Route::post('/course/{courseId}', [ReviewController::class, 'storeReview']);   // Додати відгук
        Route::put('/{reviewId}', [ReviewController::class, 'updateReview']);          // Оновити відгук
        Route::delete('/{reviewId}', [ReviewController::class, 'deleteReview']);       // Видалити відгук
        
        // Коментарі до відгуків
        Route::post('/{reviewId}/comments', [ReviewController::class, 'storeComment']);        // Додати коментар
        Route::put('/comments/{commentId}', [ReviewController::class, 'updateComment']);       // Оновити коментар
        Route::delete('/comments/{commentId}', [ReviewController::class, 'deleteComment']);    // Видалити коментар
    });
    
    /*
    |--------------------------------------------------------------------------
    | Тільки для адміністраторів
    |--------------------------------------------------------------------------
    */
    
    Route::middleware([\App\Http\Middleware\CheckRole::class . ':admin,super_admin'])->group(function () {
        // Загальна статистика курсів
        Route::get('/admin/courses/statistics', [CourseController::class, 'getStatistics']);
        
        // Фінансові звіти
        Route::prefix('payments')->group(function () {
            Route::get('/all', [PaymentController::class, 'getPayments']);              // Всі платежі
            Route::get('/financial-report', [PaymentController::class, 'getFinancialReport']); // Фінансовий звіт
            Route::get('/sales-report', [PaymentController::class, 'getSalesReport']);  // Звіт по продажах
            Route::get('/course-sales', [PaymentController::class, 'getCourseSales']);  // Звіт по курсах
        });
    });

    

    /*
    |--------------------------------------------------------------------------
    | Для вчителів (вчителі, адміни та супер-адміни)
    |--------------------------------------------------------------------------
    */
    
    Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':teacher,admin,super_admin'])->group(function () {
        
        // Управління студентами для вчителів
        Route::prefix('teacher')->group(function () {
            
            // Загальна інформація (без додаткових перевірок)
            Route::get('/students', [App\Http\Controllers\Api\TeacherStudentsController::class, 'index']);             // Всі студенти
            Route::get('/students/statistics', [App\Http\Controllers\Api\TeacherStudentsController::class, 'getStatistics']); // Статистика
            Route::get('/courses-overview', [App\Http\Controllers\Api\TeacherStudentsController::class, 'getCoursesOverview']); // Огляд курсів
            
            // Детальна інформація (з перевіркою доступу до студента)
            Route::middleware([\App\Http\Middleware\TeacherStudentAccess::class])->group(function () {
                Route::get('/students/{studentId}', [App\Http\Controllers\Api\TeacherStudentsController::class, 'show']); // Детальна інформація про студента
                Route::get('/students/{studentId}/lessons/{lessonId}/progress', [App\Http\Controllers\Api\TeacherStudentsController::class, 'getLessonProgress']); // Прогрес студента по уроку
            });
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Аналітика та звіти (тільки для адмінів)
    |--------------------------------------------------------------------------
    */
    
    Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':admin,super_admin'])->group(function () {
        
        Route::prefix('admin/analytics')->group(function () {
            Route::get('/teachers-overview', [App\Http\Controllers\Api\TeacherStudentsController::class, 'getTeachersOverview']); // Огляд вчителів
            Route::get('/courses/{courseId}/detailed-stats', [App\Http\Controllers\Api\TeacherStudentsController::class, 'getCourseDetailedStats']); // Детальна статистика курсу
            Route::get('/students/export', [App\Http\Controllers\Api\TeacherStudentsController::class, 'exportStudentsData']); // Експорт даних студентів
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Для студентів (перегляд власного прогресу)
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('student')->group(function () {
        Route::get('/my-progress', [App\Http\Controllers\Api\ProgressController::class, 'getMyProgress']);           // Мій прогрес
        Route::get('/courses/{courseId}/progress', [App\Http\Controllers\Api\ProgressController::class, 'getCourseProgress']); // Прогрес по курсу
        Route::get('/learning-stats', [App\Http\Controllers\Api\ProgressController::class, 'getLearningStats']);    // Статистика навчання
    });
    
    /*
    |--------------------------------------------------------------------------
    | Для викладачів та адміністраторів
    |--------------------------------------------------------------------------
    */
    
    Route::middleware([\App\Http\Middleware\CheckRole::class . ':teacher,admin,super_admin'])->group(function () {
        
        /*
        |--------------------------------------------------------------------------
        | Управління курсами
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('courses/manage')->group(function () {
            Route::get('/', [CourseController::class, 'getMyCourses']);                                      // Мої курси
            Route::post('/', [CourseController::class, 'store']);                                            // Створити курс
            Route::match(['PUT', 'POST'], '/{id}', [CourseController::class, 'update'])->where('id', '[0-9]+'); // Оновити курс
            Route::post('/{id}/cover', [CourseController::class, 'updateCover'])->where('id', '[0-9]+');    // Оновити обкладинку
            Route::delete('/{id}', [CourseController::class, 'destroy'])->where('id', '[0-9]+');            // Видалити курс
            Route::post('/{id}/clone', [CourseController::class, 'cloneCourse'])->where('id', '[0-9]+');    // Клонувати курс
            
            // Публікація курсів
            Route::get('/{id}/publication-check', [CourseController::class, 'checkPublicationReadiness'])->where('id', '[0-9]+');
            Route::put('/{id}/publish', [CourseController::class, 'publish'])->where('id', '[0-9]+');       // Опублікувати
            Route::put('/{id}/unpublish', [CourseController::class, 'unpublish'])->where('id', '[0-9]+');   // Зняти з публікації
            
            // Масові операції та аналітика
            Route::post('/bulk-action', [CourseController::class, 'bulkAction']);                            // Масові операції
            Route::get('/{id}/analytics', [CourseController::class, 'getContentStats'])->where('id', '[0-9]+'); // Аналітика курсу
            Route::get('/{id}/completion-stats', [\App\Http\Controllers\Api\CourseController::class, 'getCompletionStats'])->where('id', '[0-9]+'); // Статистика завершення
        });
        
        /*
        |--------------------------------------------------------------------------
        | Управління модулями
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('modules/manage')->group(function () {
            Route::get('/', [ModuleController::class, 'index']);                          // Список модулів
            Route::post('/', [ModuleController::class, 'store']);                         // Створити модуль
            Route::put('/{id}', [ModuleController::class, 'update']);                     // Оновити модуль
            Route::delete('/{id}', [ModuleController::class, 'destroy']);                 // Видалити модуль
            Route::put('/{id}/position', [ModuleController::class, 'updatePosition']);    // Оновити позицію
            Route::post('/positions', [ModuleController::class, 'updatePositions']);      // Оновити позиції (масово)
        });
        
        /*
        |--------------------------------------------------------------------------
        | Управління уроками
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('lessons/manage')->group(function () {
            Route::get('/', [LessonController::class, 'index']);                          // Список уроків
            Route::get('/module/{moduleId}', [LessonController::class, 'index']);         // Уроки модуля
            Route::post('/', [LessonController::class, 'store']);                         // Створити урок
            Route::post('/{id}', [LessonController::class, 'update']);                    // Оновити урок
            Route::put('/{id}/position', [LessonController::class, 'updatePosition']);    // Оновити позицію
            Route::post('/positions', [LessonController::class, 'updatePositions']);      // Оновити позиції (масово)
            Route::delete('/{id}', [LessonController::class, 'destroy']);                 // Видалити урок
        });

        /*
        |--------------------------------------------------------------------------
        | Внутрішні тести (CRUD операції)
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('internal-tests')->group(function () {
            // Основні CRUD операції
            Route::post('/', [InternalTestController::class, 'store']);                   // Створити тест
            Route::get('/{id}', [InternalTestController::class, 'show']);                 // Переглянути тест
            Route::put('/{id}', [InternalTestController::class, 'update']);               // Оновити тест
            Route::delete('/{id}', [InternalTestController::class, 'destroy']);           // Видалити тест
            
            // Управління питаннями
            Route::post('/{testId}/questions', [InternalTestController::class, 'addQuestion']);                    // Додати питання
            Route::put('/{testId}/questions/{questionId}', [InternalTestController::class, 'updateQuestion']);    // Оновити питання
            Route::delete('/{testId}/questions/{questionId}', [InternalTestController::class, 'deleteQuestion']); // Видалити питання
            
            // Додаткові функції
            Route::put('/{testId}/questions/order', [InternalTestController::class, 'updateQuestionsOrder']);     // Змінити порядок питань
            Route::post('/{testId}/duplicate', [InternalTestController::class, 'duplicateTest']);                 // Дублювати тест
            
            // Аналітика та статистика
            Route::get('/{testId}/analytics', [InternalTestController::class, 'getAnalytics']);                   // Аналітика тесту
            Route::get('/{testId}/attempts', [InternalTestController::class, 'getAllAttempts']);                  // Всі спроби
        });

        /*
        |--------------------------------------------------------------------------
        | Управління медіафайлами тестів
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('test-media')->group(function () {
            // Завантаження медіа
            Route::post('/question/{questionId}/upload', [TestMediaController::class, 'uploadQuestionMedia']); // Завантажити медіа для питання
            Route::post('/answer/{answerId}/upload', [TestMediaController::class, 'uploadAnswerMedia']);     // Завантажити медіа для відповіді
            
            // Видалення медіа
            Route::delete('/question/{questionId}/media', [TestMediaController::class, 'deleteQuestionMedia']); // Видалити медіа питання
            Route::delete('/answer/{answerId}/media', [TestMediaController::class, 'deleteAnswerMedia']);       // Видалити медіа відповіді
            
            // Статистика та очищення
            Route::get('/stats', [TestMediaController::class, 'getMediaStats']);                     // Статистика медіафайлів
            Route::post('/cleanup', [TestMediaController::class, 'cleanupOrphanedFiles']);           // Очистити осирілі файли
        });
    });
    
    /*
    |--------------------------------------------------------------------------
    | Тільки для адміністраторів
    |--------------------------------------------------------------------------
    */
    
    Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':admin,super_admin'])->group(function () {
        
        /*
        |--------------------------------------------------------------------------
        | Управління користувачами
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);                               // Список користувачів
            Route::get('/admins/list', [UserController::class, 'admins']);                   // Список адміністраторів
            Route::post('/admins', [UserController::class, 'storeAdmin']);                   // Створити адміністратора
            Route::post('/teachers', [UserController::class, 'storeTeacher']);               // Створити вчителя
            Route::get('/{id}', [UserController::class, 'show']);                            // Переглянути користувача
            Route::put('/{id}', [UserController::class, 'update']);                          // Оновити користувача
            Route::put('/{id}/change-role', [UserController::class, 'changeRole']);          // Змінити роль
            Route::delete('/{id}', [UserController::class, 'destroy']);                      // Видалити користувача
        });
        
        /*
        |--------------------------------------------------------------------------
        | Управління категоріями
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('categories/manage')->group(function () {
            Route::post('/', [CategoryController::class, 'store']);                          // Створити категорію
            Route::put('/{category}', [CategoryController::class, 'update']);                // Оновити категорію
            Route::delete('/{category}', [CategoryController::class, 'destroy']);            // Видалити категорію
            Route::post('/positions', [CategoryController::class, 'updatePositions']);       // Оновити позиції
            Route::put('/{id}/toggle-active', [CategoryController::class, 'toggleActive']);  // Переключити активність
            Route::put('/{id}/activate', [CategoryController::class, 'activate']);           // Активувати
            Route::put('/{id}/deactivate', [CategoryController::class, 'deactivate']);       // Деактивувати
        });
        
        /*
        |--------------------------------------------------------------------------
        | Управління рівнями
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('levels/manage')->group(function () {
            Route::post('/', [LevelController::class, 'store']);                             // Створити рівень
            Route::put('/{level}', [LevelController::class, 'update']);                      // Оновити рівень
            Route::delete('/{level}', [LevelController::class, 'destroy']);                  // Видалити рівень
        });
        
        /*
        |--------------------------------------------------------------------------
        | Модерація відгуків
        |--------------------------------------------------------------------------
        */
        
        Route::prefix('moderation')->group(function () {
            // Списки на модерацію
            Route::get('/reviews/pending', [ReviewController::class, 'getPendingReviews']);     // Відгуки на модерації
            Route::get('/comments/pending', [ReviewController::class, 'getPendingComments']);   // Коментарі на модерації
            
            // Схвалення/відхилення
            Route::put('/reviews/{reviewId}/approve', [ReviewController::class, 'approveReview']);     // Схвалити відгук
            Route::put('/comments/{commentId}/approve', [ReviewController::class, 'approveComment']);  // Схвалити коментар
            Route::delete('/reviews/{reviewId}/reject', [ReviewController::class, 'rejectReview']);    // Відхилити відгук
            Route::delete('/comments/{commentId}/reject', [ReviewController::class, 'rejectComment']); // Відхилити коментар
        });
        
        /*
        |--------------------------------------------------------------------------
        | Статистика платежів та курсів
        |--------------------------------------------------------------------------
        */
        
        Route::get('/admin/payment-stats', [PaymentController::class, 'getPaymentStats']);   // Статистика платежів
    });

    /*
    |--------------------------------------------------------------------------
    | Система прогресу уроків
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('lessons')->group(function () {
        Route::post('/{lessonId}/start', [\App\Http\Controllers\Api\ProgressController::class, 'startLesson'])->where('lessonId', '[0-9]+');        // Почати урок
        Route::post('/{lessonId}/complete', [\App\Http\Controllers\Api\ProgressController::class, 'completeLesson'])->where('lessonId', '[0-9]+');  // Завершити урок
        Route::put('/{lessonId}/progress', [\App\Http\Controllers\Api\ProgressController::class, 'updateLessonProgress'])->where('lessonId', '[0-9]+'); // Оновити прогрес
        Route::get('/{lessonId}/progress', [\App\Http\Controllers\Api\ProgressController::class, 'getLessonProgress'])->where('lessonId', '[0-9]+');   // Отримати прогрес
        Route::delete('/{lessonId}/progress', [\App\Http\Controllers\Api\ProgressController::class, 'resetLessonProgress'])->where('lessonId', '[0-9]+'); // Скинути прогрес
        Route::get('/{lessonId}/access', [\App\Http\Controllers\Api\ProgressController::class, 'checkLessonAccess'])->where('lessonId', '[0-9]+');    // Перевірити доступ
    });
    
    Route::prefix('courses')->group(function () {
        Route::get('/{courseId}/progress', [\App\Http\Controllers\Api\ProgressController::class, 'getCourseProgress'])->where('courseId', '[0-9]+');             // Прогрес курсу
        Route::get('/{courseId}/detailed-progress', [\App\Http\Controllers\Api\ProgressController::class, 'getDetailedCourseProgress'])->where('courseId', '[0-9]+'); // Детальний прогрес
        Route::delete('/{courseId}/progress', [\App\Http\Controllers\Api\ProgressController::class, 'resetCourseProgress'])->where('courseId', '[0-9]+');         // Скинути прогрес курсу
        Route::get('/{courseId}/next-lesson', [\App\Http\Controllers\Api\ProgressController::class, 'getNextLesson'])->where('courseId', '[0-9]+');                // Наступний урок
        Route::get('/{courseId}/top-students', [\App\Http\Controllers\Api\ProgressController::class, 'getCourseTopStudents'])->where('courseId', '[0-9]+')
            ->middleware([\App\Http\Middleware\CheckRole::class . ':teacher,admin,super_admin']); // Топ студенти (тільки для вчителів)
    });
    
    Route::prefix('users')->group(function () {
        Route::get('/my-progress', [\App\Http\Controllers\Api\ProgressController::class, 'getMyProgress']);           // Мій прогрес
        Route::get('/learning-stats', [\App\Http\Controllers\Api\ProgressController::class, 'getLearningStats']);    // Статистика навчання
        Route::get('/recent-activity', [\App\Http\Controllers\Api\ProgressController::class, 'getRecentActivity']);  // Недавня активність
    });

    /*
    |--------------------------------------------------------------------------
    | Підписки на сповіщення
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('notifications')->group(function () {
        Route::post('/subscribe', [\App\Http\Controllers\Api\NotificationSubscriptionController::class, 'subscribe']);       // Підписатися на сповіщення
        Route::post('/unsubscribe', [\App\Http\Controllers\Api\NotificationSubscriptionController::class, 'unsubscribe']);   // Відписатися від сповіщень
        Route::get('/my', [\App\Http\Controllers\Api\NotificationSubscriptionController::class, 'getMySubscriptions']);      // Мої підписки
        Route::put('/{id}', [\App\Http\Controllers\Api\NotificationSubscriptionController::class, 'updateSubscription']);    // Оновити підписку
    });

    /*
    |--------------------------------------------------------------------------
    | Тестові маршрути для розробки
    |--------------------------------------------------------------------------
    */
    
    // Тест створення курсів
    Route::get('/test-course-creation', function() {
        return response()->json([
            'message' => 'Тестовий маршрут для перевірки створення курсів',
            'timestamp' => now(),
            'user' => auth()->user() ? [
                'id' => auth()->id(),
                'email' => auth()->user()->email,
                'role' => auth()->user()->role->name ?? 'no_role'
            ] : null
        ]);
    });

    // Тест завантаження файлів
    Route::post('/test-course-upload', function(Request $request) {
        return response()->json([
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'has_file' => $request->hasFile('cover_image'),
            'all_data' => $request->except(['cover_image']),
            'file_info' => $request->hasFile('cover_image') ? [
                'name' => $request->file('cover_image')->getClientOriginalName(),
                'size' => $request->file('cover_image')->getSize(),
                'mime' => $request->file('cover_image')->getMimeType()
            ] : null
        ]);
    });

    // Тест обробки форм
    Route::post('/test-form-data', function(Request $request) {
        return response()->json([
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'has_method_field' => $request->has('_method'),
            '_method_value' => $request->input('_method'),
            'all_data' => $request->except(['cover_image']),
            'all_files' => array_keys($request->allFiles()),
            'has_cover_image' => $request->hasFile('cover_image'),
            'price_type' => gettype($request->input('price')),
            'price_value' => $request->input('price'),
            'is_published_type' => gettype($request->input('is_published')),
            'is_published_value' => $request->input('is_published'),
        ]);
    });

    // Тестовий маршрут PUT через POST
    Route::match(['PUT', 'POST'], '/test-put-course/{id}', function(Request $request, $id) {
        Log::info('Test PUT course called', [
            'id' => $id,
            'method' => $request->method(),
            'has_method' => $request->has('_method'),
            'method_value' => $request->input('_method'),
            'all_data' => $request->except(['cover_image'])
        ]);

        // Симулюємо CourseRequest логіку
        $data = $request->only([
            'title', 'description', 'category_id', 'instructor_id', 'price',
            'discount_price', 'level_id', 'language', 'promo_video_url',
            'requirements', 'what_you_learn', 'is_published'
        ]);

        // Фільтруємо тільки поля які прийшли
        $filteredData = array_filter($data, function($value, $key) use ($request) {
            return $request->has($key);
        }, ARRAY_FILTER_USE_BOTH);

        return response()->json([
            'success' => true,
            'course_id' => $id,
            'method' => $request->method(),
            'received_data' => $data,
            'filtered_data' => $filteredData,
            'fields_present' => array_keys($request->except(['_token', '_method', 'cover_image'])),
            'would_update' => !empty($filteredData) ? 'Yes' : 'No (no data to update)'
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Тестові маршрути для прогресу
    |--------------------------------------------------------------------------
    */
    
    // Створити тестовий прогрес для курсу
    Route::post('/test/create-sample-progress/{courseId}', function($courseId) {
        $course = \App\Models\Course::findOrFail($courseId);
        $userId = auth()->id();
        $lessons = $course->lessons()->limit(5)->get();
        $created = [];
        
        foreach ($lessons as $lesson) {
            $progress = \App\Models\LessonProgress::firstOrCreate(
                [ 'user_id' => $userId, 'lesson_id' => $lesson->id ],
                [
                    'progress_percentage' => rand(10, 100),
                    'time_spent' => rand(300, 3600),
                    'started_at' => now()->subHours(rand(1, 48)),
                    'last_accessed_at' => now()->subHours(rand(0, 24)),
                ]
            );
            
            if ($progress->progress_percentage >= 100) {
                $progress->is_completed = true;
                $progress->completed_at = now()->subHours(rand(0, 24));
                $progress->save();
            }
            
            $created[] = $progress;
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Тестовий прогрес створено',
            'created_count' => count($created)
        ]);
    });
    
    // Очистити весь прогрес користувача
    Route::delete('/test/clear-my-progress', function() {
        $userId = auth()->id();
        $deleted = \App\Models\LessonProgress::where('user_id', $userId)->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Весь прогрес користувача видалено',
            'deleted_count' => $deleted
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Кінець захищених маршрутів
    |--------------------------------------------------------------------------
    */
});

/*
|--------------------------------------------------------------------------
| Кінець файлу маршрутів
|--------------------------------------------------------------------------

| 1. Публічні маршрути - доступні без автентифікації
| 2. Захищені маршрути - потребують автентифікації
| 3. Ролеві маршрути - додаткові обмеження за ролями

|--------------------------------------------------------------------------
*/

DB::table('course_enrollments')->truncate();