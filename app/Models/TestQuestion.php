<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class TestQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_test_id',
        'question_text',
        'question_type',
        'position',
        'points',
        'explanation',
        'is_required',
        'media_type',
        'media_path',
        'media_original_name',
        'media_size',
        'media_mime_type',
    ];

    protected $casts = [
        'position' => 'integer',
        'points' => 'integer',
        'is_required' => 'boolean',
        'media_size' => 'integer',
    ];

    /**
     * Тест, до якого належить питання
     */
    public function internalTest(): BelongsTo
    {
        return $this->belongsTo(InternalTest::class);
    }

    /**
     * Варіанти відповідей
     */
    public function answers(): HasMany
    {
        return $this->hasMany(QuestionAnswer::class)->orderBy('position');
    }

    /**
     * Правильні відповіді
     */
    public function correctAnswers(): HasMany
    {
        return $this->hasMany(QuestionAnswer::class)->where('is_correct', true);
    }

    /**
     * Відповіді користувачів на це питання
     */
    public function responses(): HasMany
    {
        return $this->hasMany(TestResponse::class);
    }

    /**
     * Отримати URL медіафайлу питання
     */
    public function getMediaUrl(): ?string
    {
        if (!$this->media_path) {
            return null;
        }

        return Storage::disk('public')->url($this->media_path);
    }

    /**
     * Перевірити, чи має питання медіафайл
     */
    public function hasMedia(): bool
    {
        return !empty($this->media_path) && !empty($this->media_type);
    }

    /**
     * Видалити медіафайл питання
     */
    public function deleteMedia(): bool
    {
        if ($this->media_path && Storage::disk('public')->exists($this->media_path)) {
            Storage::disk('public')->delete($this->media_path);
        }

        $this->update([
            'media_type' => null,
            'media_path' => null,
            'media_original_name' => null,
            'media_size' => null,
            'media_mime_type' => null,
        ]);

        return true;
    }

    /**
     * Перевірити відповідь користувача
     */
    public function checkAnswer($userAnswer): array
    {
        $result = [
            'is_correct' => false,
            'points_earned' => 0,
            'correct_answers' => [],
        ];

        switch ($this->question_type) {
            case 'single_choice':
                $correctAnswer = $this->correctAnswers()->first();
                if ($correctAnswer && $userAnswer == $correctAnswer->id) {
                    $result['is_correct'] = true;
                    $result['points_earned'] = $this->points;
                }
                $result['correct_answers'] = [$correctAnswer->id ?? null];
                break;

            case 'multiple_choice':
                $correctAnswerIds = $this->correctAnswers()->pluck('id')->toArray();
                $userAnswerIds = is_array($userAnswer) ? $userAnswer : [$userAnswer];
                
                sort($correctAnswerIds);
                sort($userAnswerIds);
                
                if ($correctAnswerIds === $userAnswerIds) {
                    $result['is_correct'] = true;
                    $result['points_earned'] = $this->points;
                }
                $result['correct_answers'] = $correctAnswerIds;
                break;

            case 'text_input':
                $correctAnswers = $this->correctAnswers()->pluck('answer_text')->toArray();
                $userText = trim(strtolower($userAnswer));
                
                foreach ($correctAnswers as $correctText) {
                    if (strtolower(trim($correctText)) === $userText) {
                        $result['is_correct'] = true;
                        $result['points_earned'] = $this->points;
                        break;
                    }
                }
                $result['correct_answers'] = $correctAnswers;
                break;
        }

        return $result;
    }

    /**
     * Отримати варіанти відповідей для відображення (без правильних відповідей)
     */
    public function getAnswersForDisplay(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->question_type === 'text_input') {
            return collect();
        }

        return $this->answers()->select('id', 'answer_text', 'position', 'media_type', 'media_path', 'media_original_name')->get()->map(function ($answer) {
            $answer->media_url = $answer->getMediaUrl();
            return $answer;
        });
    }

    /**
     * Валідація типу питання
     */
    public function validateQuestionType(): bool
    {
        switch ($this->question_type) {
            case 'single_choice':
                return $this->correctAnswers()->count() === 1;
            
            case 'multiple_choice':
                return $this->correctAnswers()->count() >= 1;
            
            case 'text_input':
                return $this->correctAnswers()->count() >= 1;
            
            default:
                return false;
        }
    }
}