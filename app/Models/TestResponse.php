<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_attempt_id',
        'test_question_id',
        'selected_answers',
        'text_answer',
        'is_correct',
        'points_earned',
    ];

    protected $casts = [
        'selected_answers' => 'array',
        'is_correct' => 'boolean',
        'points_earned' => 'integer',
    ];

    /**
     * Спроба тесту, до якої належить ця відповідь
     */
    public function testAttempt(): BelongsTo
    {
        return $this->belongsTo(TestAttempt::class);
    }

    /**
     * Питання, на яке дається відповідь
     */
    public function testQuestion(): BelongsTo
    {
        return $this->belongsTo(TestQuestion::class);
    }

    /**
     * Отримати відповідь користувача в зручному форматі
     */
    public function getUserAnswerText(): string
    {
        if ($this->testQuestion->question_type === 'text_input') {
            return $this->text_answer ?? '';
        }

        if ($this->selected_answers) {
            $answers = QuestionAnswer::whereIn('id', $this->selected_answers)
                ->pluck('answer_text')
                ->toArray();
            
            return implode(', ', $answers);
        }

        return '';
    }

    /**
     * Отримати правильні відповіді в текстовому форматі
     */
    public function getCorrectAnswerText(): string
    {
        if ($this->testQuestion->question_type === 'text_input') {
            $correctAnswers = $this->testQuestion->correctAnswers()
                ->pluck('answer_text')
                ->toArray();
            
            return implode(', ', $correctAnswers);
        }

        $correctAnswers = $this->testQuestion->correctAnswers()
            ->pluck('answer_text')
            ->toArray();
        
        return implode(', ', $correctAnswers);
    }
}