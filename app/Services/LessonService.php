<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonLecture;
use App\Models\LessonTest;
use App\Models\LessonExtraMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LessonService
{
    public function getAllLessonsByModuleId(int $moduleId): Collection
    {
        return Lesson::where('module_id', $moduleId)
            ->orderBy('position')
            ->get();
    }

    public function getLessonById(int $id): ?Lesson
    {
        return Lesson::with(['lecture', 'test', 'extraMaterial'])->findOrFail($id);
    }

    public function createLesson(array $data): Lesson
    {
        // Визначаємо максимальну позицію серед уроків модуля
        $maxPosition = Lesson::where('module_id', $data['module_id'])->max('position') ?? 0;
        
        // Встановлюємо нову позицію, якщо не вказано явно
        if (!isset($data['position'])) {
            $data['position'] = $maxPosition + 1;
        }
        
        return DB::transaction(function () use ($data) {
            // Створюємо урок
            $lesson = Lesson::create([
                'module_id' => $data['module_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'position' => $data['position'],
                'status' => $data['status'] ?? 'active',
            ]);
            
            // Створюємо відповідні деталі для різних типів уроків
            switch ($data['type']) {
                case 'lecture':
                    LessonLecture::create([
                        'lesson_id' => $lesson->id,
                        'content' => $data['content'] ?? null,
                        'duration_minutes' => $data['duration_minutes'] ?? null,
                    ]);
                    break;
                
                case 'test':
                    LessonTest::create([
                        'lesson_id' => $lesson->id,
                        'source_type' => $data['source_type'] ?? 'internal',
                        'external_url' => $data['external_url'] ?? null,
                        'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                        'passing_score' => $data['passing_score'] ?? null,
                    ]);
                    break;
                
                case 'extra_material':
                    LessonExtraMaterial::create([
                        'lesson_id' => $lesson->id,
                        'material_type' => $data['material_type'] ?? 'text',
                        'content' => $data['content'] ?? null,
                        'file_path' => $data['file_path'] ?? null,
                        'url' => $data['url'] ?? null,
                    ]);
                    break;
            }
            
            return $lesson->fresh(['lecture', 'test', 'extraMaterial']);
        });
    }

    public function updateLesson(int $id, array $data): Lesson
    {
        return DB::transaction(function () use ($id, $data) {
            $lesson = Lesson::findOrFail($id);
            
            // Оновлюємо базову інформацію про урок
            $lesson->update([
                'title' => $data['title'] ?? $lesson->title,
                'description' => $data['description'] ?? $lesson->description,
                'position' => $data['position'] ?? $lesson->position,
                'status' => $data['status'] ?? $lesson->status,
            ]);
            
            // Оновлюємо деталі в залежності від типу уроку
            switch ($lesson->type) {
                case 'lecture':
                    $lecture = $lesson->lecture ?? LessonLecture::create(['lesson_id' => $lesson->id]);
                    $lecture->update([
                        'content' => $data['content'] ?? $lecture->content,
                        'duration_minutes' => $data['duration_minutes'] ?? $lecture->duration_minutes,
                    ]);
                    break;
                
                case 'test':
                    $test = $lesson->test ?? LessonTest::create(['lesson_id' => $lesson->id]);
                    $test->update([
                        'source_type' => $data['source_type'] ?? $test->source_type,
                        'external_url' => $data['external_url'] ?? $test->external_url,
                        'time_limit_minutes' => $data['time_limit_minutes'] ?? $test->time_limit_minutes,
                        'passing_score' => $data['passing_score'] ?? $test->passing_score,
                    ]);
                    break;
                
                case 'extra_material':
                    $material = $lesson->extraMaterial ?? LessonExtraMaterial::create(['lesson_id' => $lesson->id]);
                    $material->update([
                        'material_type' => $data['material_type'] ?? $material->material_type,
                        'content' => $data['content'] ?? $material->content,
                        'file_path' => $data['file_path'] ?? $material->file_path,
                        'url' => $data['url'] ?? $material->url,
                    ]);
                    break;
            }
            
            return $lesson->fresh(['lecture', 'test', 'extraMaterial']);
        });
    }

    public function deleteLesson(int $id): bool
    {
        $lesson = Lesson::findOrFail($id);
        return $lesson->delete();
    }

    public function updateLessonPositions(array $positions): bool
    {
        foreach ($positions as $position) {
            Lesson::where('id', $position['id'])->update(['position' => $position['position']]);
        }
        
        return true;
    }

    // Метод для перевірки, чи може користувач редагувати урок
    public function canUserManageLesson(int $userId, int $lessonId): bool
    {
        $lesson = Lesson::findOrFail($lessonId);
        $module = $lesson->module;
        $course = $module->course;
        
        // Перевіряємо, чи користувач є автором курсу, до якого належить урок,
        // або адміністратором/супер-адміністратором
        return $course->instructor_id === $userId || auth()->user()->isAdmin();
    }
}