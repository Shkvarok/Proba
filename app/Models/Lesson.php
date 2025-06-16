<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'title',
        'description',
        'type',
        'position',
        'status',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function lecture(): HasOne
    {
        return $this->hasOne(LessonLecture::class);
    }

    public function test(): HasOne
    {
        return $this->hasOne(LessonTest::class);
    }

    public function extraMaterial(): HasOne
    {
        return $this->hasOne(LessonExtraMaterial::class);
    }

    /**
     * Внутрішній тест (якщо test.source_type === 'internal')
     */
    public function internalTest(): HasOne
    {
        return $this->hasOne(InternalTest::class);
    }

    // Метод для отримання пов'язаних даних в залежності від типу уроку
    public function details()
    {
        return match($this->type) {
            'lecture' => $this->lecture,
            'test' => $this->test,
            'extra_material' => $this->extraMaterial,
            default => null,
        };
    }

    // Метод для отримання курсу через модуль
    public function course()
    {
        return $this->module->course;
    }

    /**
     * Перевірити, чи урок має внутрішній тест
     */
    public function hasInternalTest(): bool
    {
        return $this->type === 'test' && 
               $this->test && 
               $this->test->source_type === 'internal' &&
               $this->internalTest !== null;
    }

    /**
     * Перевірити, чи урок має зовнішній тест
     */
    public function hasExternalTest(): bool
    {
        return $this->type === 'test' && 
               $this->test && 
               $this->test->source_type === 'external';
    }


    /**
     * Прогрес уроку для різних користувачів
     */
    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Отримати прогрес уроку для конкретного користувача
     */
    public function getProgressForUser(int $userId): ?LessonProgress
    {
        return $this->progress()->where('user_id', $userId)->first();
    }

    /**
     * Перевірити чи урок завершений користувачем
     */
    public function isCompletedByUser(int $userId): bool
    {
        $progress = $this->getProgressForUser($userId);
        return $progress ? $progress->is_completed : false;
    }

    /**
     * Перевірити чи урок розпочатий користувачем
     */
    public function isStartedByUser(int $userId): bool
    {
        $progress = $this->getProgressForUser($userId);
        return $progress ? !is_null($progress->started_at) : false;
    }

    /**
     * Отримати відсоток прогресу уроку для користувача
     */
    public function getProgressPercentageForUser(int $userId): float
    {
        $progress = $this->getProgressForUser($userId);
        return $progress ? $progress->progress_percentage : 0;
    }

    /**
     * Позначити урок як розпочатий для користувача
     */
    public function markAsStartedForUser(int $userId): LessonProgress
    {
        $progress = LessonProgress::firstOrCreate(
            [
                'user_id' => $userId,
                'lesson_id' => $this->id,
            ]
        );

        return $progress->markAsStarted();
    }

    /**
     * Позначити урок як завершений для користувача
     */
    public function markAsCompletedForUser(int $userId): LessonProgress
    {
        $progress = LessonProgress::firstOrCreate(
            [
                'user_id' => $userId,
                'lesson_id' => $this->id,
            ]
        );

        return $progress->markAsCompleted();
    }

    /**
     * Оновити прогрес уроку для користувача
     */
    public function updateProgressForUser(int $userId, float $percentage, int $timeSpent = 0): LessonProgress
    {
        $progress = LessonProgress::firstOrCreate(
            [
                'user_id' => $userId,
                'lesson_id' => $this->id,
            ]
        );

        return $progress->updateProgress($percentage, $timeSpent);
    }

     /**
     * Отримати статистику завершення уроку
     */
    public function getCompletionStats(): array
    {
        $totalProgress = $this->progress()->count();
        $completedCount = $this->progress()->where('is_completed', true)->count();
        $averageProgress = $this->progress()->avg('progress_percentage') ?: 0;
        $averageTimeSpent = $this->progress()->avg('time_spent') ?: 0;

        return [
            'total_students' => $totalProgress,
            'completed_count' => $completedCount,
            'completion_rate' => $totalProgress > 0 ? round($completedCount / $totalProgress * 100, 2) : 0,
            'average_progress' => round($averageProgress, 2),
            'average_time_spent' => round($averageTimeSpent),
        ];
    }

    /**
     * Отримати наступний урок в модулі
     */
    public function getNextLesson(): ?self
    {
        return self::where('module_id', $this->module_id)
            ->where('position', '>', $this->position)
            ->orderBy('position')
            ->first();
    }

    /**
     * Отримати попередній урок в модулі
     */
    public function getPreviousLesson(): ?self
    {
        return self::where('module_id', $this->module_id)
            ->where('position', '<', $this->position)
            ->orderBy('position', 'desc')
            ->first();
    }

    /**
     * Перевірити чи урок доступний для користувача
     */
    public function isAccessibleByUser(int $userId): bool
    {
        // Перевіряємо доступ до курсу
        $course = $this->module->course;
        
        if (!$course->hasUserAccess($userId)) {
            return false;
        }

        // Додаткова логіка: можна зробити так, щоб уроки були доступні послідовно
        // (тільки після завершення попереднього)
        
        return true;
    }

    /**
     * Отримати рекомендовану тривалість уроку (якщо це лекція)
     */
    public function getEstimatedDuration(): ?int
    {
        if ($this->type === 'lecture' && $this->lecture) {
            return $this->lecture->duration_minutes;
        }

        // Для інших типів уроків можна встановити стандартну тривалість
        return match($this->type) {
            'test' => 30, // 30 хвилин для тесту
            'extra_material' => 15, // 15 хвилин для додаткових матеріалів
            default => null,
        };
    }

}