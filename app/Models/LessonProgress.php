<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    use HasFactory;

    protected $table = 'lesson_progress';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'is_completed',
        'progress_percentage',
        'time_spent',
        'started_at',
        'completed_at',
        'last_accessed_at',
        'additional_data',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'progress_percentage' => 'decimal:2',
        'time_spent' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'additional_data' => 'array',
    ];

    /**
     * Отримати користувача
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Отримати урок
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Позначити урок як розпочатий
     */
    public function markAsStarted(): self
    {
        if (!$this->started_at) {
            $this->started_at = now();
        }
        $this->last_accessed_at = now();
        $this->save();

        return $this;
    }

    /**
     * Позначити урок як завершений
     */
    public function markAsCompleted(): self
    {
        $this->is_completed = true;
        $this->progress_percentage = 100;
        $this->completed_at = now();
        $this->last_accessed_at = now();
        
        if (!$this->started_at) {
            $this->started_at = now();
        }
        
        $this->save();

        return $this;
    }

    /**
     * Оновити прогрес
     */
    public function updateProgress(float $percentage, int $timeSpent = 0): self
    {
        $this->progress_percentage = min(100, max(0, $percentage));
        
        if ($timeSpent > 0) {
            $this->time_spent += $timeSpent;
        }
        
        $this->last_accessed_at = now();
        
        if (!$this->started_at) {
            $this->started_at = now();
        }

        // Автоматично позначаємо як завершений при 100%
        if ($this->progress_percentage >= 100 && !$this->is_completed) {
            $this->is_completed = true;
            $this->completed_at = now();
        }

        $this->save();

        return $this;
    }

    /**
     * Скинути прогрес
     */
    public function resetProgress(): self
    {
        $this->is_completed = false;
        $this->progress_percentage = 0;
        $this->completed_at = null;
        $this->save();

        return $this;
    }

    /**
     * Отримати час у зручному форматі
     */
    public function getFormattedTimeSpentAttribute(): string
    {
        $hours = floor($this->time_spent / 3600);
        $minutes = floor(($this->time_spent % 3600) / 60);
        $seconds = $this->time_spent % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Scope для завершених уроків
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope для розпочатих уроків
     */
    public function scopeStarted($query)
    {
        return $query->whereNotNull('started_at');
    }

    /**
     * Scope для прогресу користувача
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope для прогресу уроку
     */
    public function scopeForLesson($query, int $lessonId)
    {
        return $query->where('lesson_id', $lessonId);
    }
}