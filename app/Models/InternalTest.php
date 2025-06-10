<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'title',
        'description',
        'passing_score',
        'time_limit_minutes',
        'status',
        'randomize_questions',
        'questions_to_show',
        'max_attempts',
        'show_results_immediately',
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'time_limit_minutes' => 'integer',
        'randomize_questions' => 'boolean',
        'questions_to_show' => 'integer',
        'max_attempts' => 'integer',
        'show_results_immediately' => 'boolean',
    ];

    /**
     * Урок, до якого належить тест
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Питання тесту
     */
    public function questions(): HasMany
    {
        return $this->hasMany(TestQuestion::class)->orderBy('position');
    }

    /**
     * Спроби проходження тесту
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    /**
     * Перевірка, чи активний тест
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Отримати питання для проходження тесту
     */
    public function getQuestionsForAttempt(): \Illuminate\Database\Eloquent\Collection
    {
        $questions = $this->questions()->with('answers')->get();

        if ($this->randomize_questions) {
            $questions = $questions->shuffle();
        }

        if ($this->questions_to_show && $this->questions_to_show < $questions->count()) {
            $questions = $questions->take($this->questions_to_show);
        }

        return $questions;
    }

    /**
     * Підрахувати максимальну кількість балів
     */
    public function getMaxScore(): int
    {
        return $this->questions()->sum('points');
    }

    /**
     * Перевірити, чи може користувач проходити тест
     */
    public function canUserAttempt(int $userId): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $userAttemptsCount = $this->attempts()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->count();

        return $userAttemptsCount < $this->max_attempts;
    }

    /**
     * Отримати останню спробу користувача
     */
    public function getLastUserAttempt(int $userId): ?TestAttempt
    {
        return $this->attempts()
            ->where('user_id', $userId)
            ->latest()
            ->first();
    }

    /**
     * Отримати найкращу спробу користувача
     */
    public function getBestUserAttempt(int $userId): ?TestAttempt
    {
        return $this->attempts()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->orderByDesc('percentage')
            ->first();
    }
}