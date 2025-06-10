<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class TestAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_test_id',
        'user_id',
        'started_at',
        'completed_at',
        'score',
        'max_score',
        'percentage',
        'is_passed',
        'status',
        'questions_data',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'score' => 'integer',
        'max_score' => 'integer',
        'percentage' => 'decimal:2',
        'is_passed' => 'boolean',
        'questions_data' => 'array',
    ];

    /**
     * Тест, який проходить користувач
     */
    public function internalTest(): BelongsTo
    {
        return $this->belongsTo(InternalTest::class);
    }

    /**
     * Користувач, який проходить тест
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Відповіді користувача в цій спробі
     */
    public function responses(): HasMany
    {
        return $this->hasMany(TestResponse::class);
    }

    /**
     * Перевірити, чи закінчився час тесту
     */
    public function isTimeUp(): bool
    {
        if (!$this->internalTest->time_limit_minutes) {
            return false;
        }

        $timeLimit = $this->started_at->addMinutes($this->internalTest->time_limit_minutes);
        return Carbon::now()->isAfter($timeLimit);
    }

    /**
     * Отримати залишок часу в хвилинах
     */
    public function getRemainingTimeMinutes(): ?int
    {
        if (!$this->internalTest->time_limit_minutes) {
            return null;
        }

        $timeLimit = $this->started_at->addMinutes($this->internalTest->time_limit_minutes);
        $now = Carbon::now();

        if ($now->isAfter($timeLimit)) {
            return 0;
        }

        return $now->diffInMinutes($timeLimit);
    }

    /**
     * Завершити спробу та підрахувати результат
     */
    public function complete(): void
    {
        if ($this->status === 'completed') {
            return;
        }

        $this->completed_at = Carbon::now();
        $this->status = 'completed';

        $totalScore = 0;
        $maxScore = 0;

        foreach ($this->responses as $response) {
            $totalScore += $response->points_earned;
            $maxScore += $response->testQuestion->points;
        }

        $this->score = $totalScore;
        $this->max_score = $maxScore;
        $this->percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
        $this->is_passed = $this->percentage >= $this->internalTest->passing_score;

        $this->save();
    }

    /**
     * Перевірити, чи може користувач продовжити тест
     */
    public function canContinue(): bool
    {
        return $this->status === 'in_progress' && !$this->isTimeUp();
    }

    /**
     * Отримати прогрес проходження тесту
     */
    public function getProgress(): array
    {
        $totalQuestions = count($this->questions_data ?? []);
        $answeredQuestions = $this->responses()->count();

        return [
            'total_questions' => $totalQuestions,
            'answered_questions' => $answeredQuestions,
            'remaining_questions' => $totalQuestions - $answeredQuestions,
            'progress_percentage' => $totalQuestions > 0 ? ($answeredQuestions / $totalQuestions) * 100 : 0,
        ];
    }
}