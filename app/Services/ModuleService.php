<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Eloquent\Collection;

class ModuleService
{
    protected $courseService;
    
    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }
    
    public function getAllModulesByCourseId(int $courseId): Collection
    {
        return Module::where('course_id', $courseId)
            ->orderBy('position')
            ->get();
    }

    public function getModuleById(int $id): ?Module
    {
        return Module::with('lessons')->findOrFail($id);
    }

    public function createModule(array $data): Module
    {
        // Визначаємо максимальну позицію серед модулів курсу
        $maxPosition = Module::where('course_id', $data['course_id'])->max('position') ?? 0;
        
        // Встановлюємо нову позицію, якщо не вказано явно
        if (!isset($data['position'])) {
            $data['position'] = $maxPosition + 1;
        }
        
        return Module::create($data);
    }

    public function updateModule(int $id, array $data): Module
    {
        $module = Module::findOrFail($id);
        $module->update($data);
        
        return $module;
    }

    public function deleteModule(int $id): bool
    {
        $module = Module::findOrFail($id);
        return $module->delete();
    }

    public function updateModulePositions(array $positions): bool
    {
        foreach ($positions as $position) {
            Module::where('id', $position['id'])->update(['position' => $position['position']]);
        }
        
        return true;
    }

    // Метод для перевірки, чи може користувач редагувати модуль
    public function canUserManageModule(int $userId, int $moduleId): bool
    {
        $module = Module::findOrFail($moduleId);
        $course = $module->course;
        
        // Перевіряємо, чи користувач є автором курсу, до якого належить модуль,
        // або адміністратором/супер-адміністратором
        return $course->instructor_id === $userId || auth()->user()->isAdmin();
    }

        /**
     * Перевірка, чи може користувач керувати курсом
     */
    public function canUserManageCourse(int $userId, int $courseId): bool
    {
        // Використовуємо метод з CourseService
        return $this->courseService->canUserManageCourse($userId, $courseId);
    }
}