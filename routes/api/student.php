<?php
// routes/api/student.php
use App\Http\Controllers\Api\CourseEnrollmentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\LessonController;

Route::middleware('auth:sanctum')->group(function () {
    // Профіль користувача
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'getProfile']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
    });
    
    // Мої курси (студент)
    Route::prefix('my')->group(function () {
        Route::get('/courses', [CourseController::class, 'getEnrolledCourses']);
        Route::get('/courses/{courseId}/progress', [CourseController::class, 'getCourseProgress']);
    });
    
    // Підписки на курси
    Route::prefix('enrollments')->group(function () {
        Route::get('/', [CourseEnrollmentController::class, 'index']);
        Route::get('/course/{courseId}', [CourseEnrollmentController::class, 'checkAccess']);
        Route::post('/free/{courseId}', [CourseEnrollmentController::class, 'enrollFree']);
    });
    
    // Платежі
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'getUserPayments']);
        Route::post('/course/{courseId}', [PaymentController::class, 'createCoursePayment']);
        Route::post('/course/{courseId}/liqpay', [PaymentController::class, 'initiateCoursePayment']);
        Route::get('/{paymentId}/status', [PaymentController::class, 'checkPaymentStatus']);
        Route::get('/course/{courseId}/success', [PaymentController::class, 'paymentSuccess'])->name('courses.payment.success');
        
        // Публічні callback маршрути
        Route::withoutMiddleware('auth:sanctum')->group(function () {
            Route::post('/liqpay/callback', [PaymentController::class, 'liqpayCallback'])->name('liqpay.callback');
            Route::get('/{paymentId}/failed', [PaymentController::class, 'paymentFailed']);
            Route::post('/{paymentId}/retry', [PaymentController::class, 'retryPayment']);
        });
    });
    
    // Доступ до навчального контенту
    Route::middleware('check.course.access')->prefix('learn')->group(function () {
        Route::get('/courses/{courseId}/modules', [ModuleController::class, 'getModulesByCourse']);
        Route::get('/courses/{courseId}/modules/{moduleId}/lessons', [LessonController::class, 'getLessonsByModule']);
    });
    
    // Відгуки
    Route::prefix('reviews')->group(function () {
        Route::post('/course/{courseId}', [App\Http\Controllers\Api\ReviewController::class, 'storeReview'])
            ->middleware(\App\Http\Middleware\CheckCourseReviewAccess::class);
        Route::put('/{reviewId}', [App\Http\Controllers\Api\ReviewController::class, 'updateReview']);
        Route::delete('/{reviewId}', [App\Http\Controllers\Api\ReviewController::class, 'deleteReview']);
        
        // Коментарі до відгуків
        Route::post('/{reviewId}/comments', [App\Http\Controllers\Api\ReviewController::class, 'storeComment']);
        Route::put('/comments/{commentId}', [App\Http\Controllers\Api\ReviewController::class, 'updateComment']);
        Route::delete('/comments/{commentId}', [App\Http\Controllers\Api\ReviewController::class, 'deleteComment']);
    });
});
