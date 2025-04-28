<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LessonController extends Controller
{
    /**
     * Отримання всіх уроків для конкретного курсу
     */
    public function index(Request $request, $courseId)
    {
        // Перевіряємо, чи існує курс
        $course = Course::findOrFail($courseId);
        
        // Визначаємо, чи має користувач доступ до курсу
        $isAdmin = $request->user() && $request->user()->hasAnyRole(['admin', 'super_admin', 'instructor']);
        $isInstructor = $request->user() && $request->user()->id === $course->instructor_id;
        $hasAccess = $isAdmin || $isInstructor;
        
        // Якщо звичайний користувач, перевіряємо доступ до курсу
        if (!$hasAccess && $request->user()) {
            $hasAccess = $request->user()->studentCourses()
                ->where('course_id', $courseId)
                ->active()
                ->exists();
        }
        
        // Базовий запит
        $query = Lesson::where('course_id', $courseId)->ordered();
        
        // Для адміністраторів або авторів курсу показуємо всі уроки
        if (!$hasAccess) {
            // Для інших показуємо лише опубліковані
            $query->published()
                  ->where(function($q) {
                      $q->where('is_free', true)
                        ->orWhereHas('course', function($cq) {
                            $cq->where('price', 0);
                        });
                  });
        }
        
        $lessons = $query->get();
        
        return response()->json([
            'success' => true,
            'data' => $lessons,
            'has_full_access' => $hasAccess
        ]);
    }

    /**
     * Отримання конкретного уроку
     */
    public function show(Request $request, $courseId, $lessonId)
    {
        // Перевіряємо, чи існує курс
        $course = Course::findOrFail($courseId);
        
        // Перевіряємо, чи існує урок
        $lesson = Lesson::where('course_id', $courseId)
            ->where('id', $lessonId)
            ->firstOrFail();
        
        // Визначаємо, чи має користувач доступ до курсу
        $isAdmin = $request->user() && $request->user()->hasAnyRole(['admin', 'super_admin', 'instructor']);
        $isInstructor = $request->user() && $request->user()->id === $course->instructor_id;
        $hasAccess = $isAdmin || $isInstructor;
        
        // Якщо звичайний користувач, перевіряємо доступ до курсу
        if (!$hasAccess && $request->user()) {
            $hasAccess = $request->user()->studentCourses()
                ->where('course_id', $courseId)
                ->active()
                ->exists();
        }
        
        // Якщо урок не опублікований і користувач не має доступу
        if (!$hasAccess && (!$lesson->is_published || (!$lesson->is_free && $course->price > 0))) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього уроку'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $lesson
        ]);
    }

    /**
     * Створення нового уроку
     */
    public function store(Request $request, $courseId)
    {
        // Перевіряємо, чи має користувач доступ до створення уроків
        if (!$request->user() || !$request->user()->hasPermission('create-lessons')) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }
        
        // Перевіряємо, чи існує курс
        $course = Course::findOrFail($courseId);
        
        // Валідація даних
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'type' => 'required|in:lecture,video,test',
            'order' => 'nullable|integer|min:0',
            'file' => 'nullable|file|mimes:pdf|max:10240', // Max 10MB для PDF
            'video' => 'nullable|file|mimes:mp4,mov,avi|max:102400', // Max 100MB для відео
            'video_url' => 'nullable|string|url',
            'test_url' => 'nullable|string|url',
            'is_free' => 'boolean',
            'duration_minutes' => 'nullable|integer|min:1',
            'is_published' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Підготовка даних
        $data = $validator->validated();
        $data['course_id'] = $courseId;
        
        // Визначення порядку уроку, якщо не вказано
        if (!isset($data['order'])) {
            $lastOrder = Lesson::where('course_id', $courseId)->max('order');
            $data['order'] = $lastOrder ? $lastOrder + 1 : 1;
        }
        
        // Обробка файлів
        if ($request->hasFile('file') && $data['type'] === 'lecture') {
            $filePath = $request->file('file')->store('lessons/files', 'public');
            $data['file_path'] = $filePath;
        }
        
        if ($request->hasFile('video') && $data['type'] === 'video') {
            $videoPath = $request->file('video')->store('lessons/videos', 'public');
            $data['video_url'] = $videoPath;
        }
        
        // Створення уроку
        $lesson = Lesson::create($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Урок успішно створено',
            'data' => $lesson
        ], 201);
    }

    /**
     * Оновлення уроку
     */
    public function update(Request $request, $courseId, $lessonId)
    {
        // Перевіряємо, чи має користувач доступ до редагування уроків
        if (!$request->user() || !$request->user()->hasPermission('edit-lessons')) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }
        
        // Перевіряємо, чи існує курс
        $course = Course::findOrFail($courseId);
        
        // Перевіряємо, чи існує урок
        $lesson = Lesson::where('course_id', $courseId)
            ->where('id', $lessonId)
            ->firstOrFail();
        
        // Валідація даних
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'type' => 'sometimes|required|in:lecture,video,test',
            'order' => 'nullable|integer|min:0',
            'file' => 'nullable|file|mimes:pdf|max:10240', // Max 10MB для PDF
            'video' => 'nullable|file|mimes:mp4,mov,avi|max:102400', // Max 100MB для відео
            'video_url' => 'nullable|string|url',
            'test_url' => 'nullable|string|url',
            'is_free' => 'boolean',
            'duration_minutes' => 'nullable|integer|min:1',
            'is_published' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Підготовка даних
        $data = $validator->validated();
        
        // Обробка файлів
        if ($request->hasFile('file') && (!isset($data['type']) || $data['type'] === 'lecture' || $lesson->type === 'lecture')) {
            // Видаляємо старий файл, якщо він існує
            if ($lesson->file_path) {
                Storage::disk('public')->delete($lesson->file_path);
            }
            
            $filePath = $request->file('file')->store('lessons/files', 'public');
            $data['file_path'] = $filePath;
        }
        
        if ($request->hasFile('video') && (!isset($data['type']) || $data['type'] === 'video' || $lesson->type === 'video')) {
            // Видаляємо старе відео, якщо воно збережене локально
            if ($lesson->video_url && !filter_var($lesson->video_url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                Storage::disk('public')->delete($lesson->video_url);
            }
            
            $videoPath = $request->file('video')->store('lessons/videos', 'public');
            $data['video_url'] = $videoPath;
        }
        
        // Оновлення уроку
        $lesson->update($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Урок успішно оновлено',
            'data' => $lesson
        ]);
    }

    /**
     * Видалення уроку
     */
    public function destroy(Request $request, $courseId, $lessonId)
    {
        // Перевіряємо, чи має користувач доступ до видалення уроків
        if (!$request->user() || !$request->user()->hasPermission('delete-lessons')) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }
        
        // Перевіряємо, чи існує курс
        $course = Course::findOrFail($courseId);
        
        // Перевіряємо, чи існує урок
        $lesson = Lesson::where('course_id', $courseId)
            ->where('id', $lessonId)
            ->firstOrFail();
        
        // Видаляємо файли, пов'язані з уроком
        if ($lesson->file_path) {
            Storage::disk('public')->delete($lesson->file_path);
        }
        
        if ($lesson->video_url && !filter_var($lesson->video_url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            Storage::disk('public')->delete($lesson->video_url);
        }
        
        // Видаляємо урок
        $lesson->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Урок успішно видалено'
        ]);
    }

    /**
     * Оновлення порядку уроків
     */
    public function updateOrder(Request $request, $courseId)
    {
        // Перевіряємо, чи має користувач доступ до редагування уроків
        if (!$request->user() || !$request->user()->hasPermission('edit-lessons')) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }
        
        // Перевіряємо, чи існує курс
        $course = Course::findOrFail($courseId);
        
        // Валідація даних
        $validator = Validator::make($request->all(), [
            'lessons' => 'required|array',
            'lessons.*.id' => 'required|integer|exists:lessons,id',
            'lessons.*.order' => 'required|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Оновлення порядку
        foreach ($request->lessons as $item) {
            Lesson::where('id', $item['id'])
                ->where('course_id', $courseId)
                ->update(['order' => $item['order']]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Порядок уроків успішно оновлено'
        ]);
    }
}