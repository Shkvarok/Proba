<?php

// routes/api/teacher.php
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\LessonController;

Route::middleware(['auth:sanctum', 'role:teacher,admin,super_admin'])->prefix('teacher')->group(function () {
    // Управління курсами
    Route::prefix('courses')->group(function () {
        Route::get('/', [CourseController::class, 'getMyCourses']);
        Route::post('/', [CourseController::class, 'store']);
        Route::put('/{id}', [CourseController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [CourseController::class, 'destroy'])->where('id', '[0-9]+');
        Route::put('/{id}/publish', [CourseController::class, 'publish'])->where('id', '[0-9]+');
        Route::put('/{id}/unpublish', [CourseController::class, 'unpublish'])->where('id', '[0-9]+');
    });
    
    // Управління модулями
    Route::prefix('modules')->group(function () {
        Route::get('/', [ModuleController::class, 'index']);
        Route::post('/', [ModuleController::class, 'store']);
        Route::put('/{id}', [ModuleController::class, 'update']);
        Route::delete('/{id}', [ModuleController::class, 'destroy']);
        Route::put('/{id}/position', [ModuleController::class, 'updatePosition']);
        Route::post('/positions', [ModuleController::class, 'updatePositions']);
    });
    
    // Управління уроками
    Route::prefix('lessons')->group(function () {
        Route::get('/', [LessonController::class, 'index']);
        Route::get('/module/{moduleId}', [LessonController::class, 'index']);
        Route::post('/', [LessonController::class, 'store']);
        Route::put('/{id}', [LessonController::class, 'update']);
        Route::put('/{id}/position', [LessonController::class, 'updatePosition']);
        Route::post('/positions', [LessonController::class, 'updatePositions']);
        Route::delete('/{id}', [LessonController::class, 'destroy']);
    });
});