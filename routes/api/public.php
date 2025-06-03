<?php

// routes/api/public.php
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LevelController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\LessonController;

// Публічні маршрути для категорій
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/active', [CategoryController::class, 'getActive']);
    Route::get('/hierarchy', [CategoryController::class, 'getHierarchy']);
    Route::get('/slug/{slug}', [CategoryController::class, 'getBySlug']);
    Route::get('/{category}', [CategoryController::class, 'show']);
});

// Публічні маршрути для рівнів
Route::prefix('levels')->group(function () {
    Route::get('/', [LevelController::class, 'index']);
    Route::get('/{level}', [LevelController::class, 'show']);
});

// Публічні маршрути для курсів
Route::prefix('courses')->group(function () {
    Route::get('/', [CourseController::class, 'index']);
    Route::get('/search', [CourseController::class, 'search']);
    Route::get('/category/{categoryId}', [CourseController::class, 'getByCategory'])->where('categoryId', '[0-9]+');
    Route::get('/level/{levelId}', [CourseController::class, 'getByLevel'])->where('levelId', '[0-9]+');
    Route::get('/instructor/{instructorId}', [CourseController::class, 'getByInstructor'])->where('instructorId', '[0-9]+');
    Route::get('/{id}', [CourseController::class, 'show'])->where('id', '[0-9]+');
    Route::get('/{courseId}/modules', [ModuleController::class, 'index'])->where('courseId', '[0-9]+');
});