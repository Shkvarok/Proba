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
        'user_answer' => $userAnswer
    ];

    try {
        switch ($this->question_type) {
            case 'single_choice':
                $result = $this->checkSingleChoice($userAnswer);
                break;
                
            case 'multiple_choice':
                $result = $this->checkMultipleChoice($userAnswer);
                break;
                
            case 'text_input':
                $result = $this->checkTextInput($userAnswer);
                break;
                
            default:
                throw new \InvalidArgumentException("Unsupported question type: {$this->question_type}");
        }
        
    } catch (\Exception $e) {
        \Log::error('Error checking answer', [
            'question_id' => $this->id,
            'question_type' => $this->question_type,
            'user_answer' => $userAnswer,
            'error' => $e->getMessage()
        ]);
        
        // Повертаємо безпечний результат у випадку помилки
        $result['error'] = $e->getMessage();
    }

    return $result;
}
/**
 * Перевірка одиночного вибору
 */
private function checkSingleChoice($userAnswer): array
{
    // Для одиночного вибору очікуємо ID відповіді (число або рядок з числом)
    $answerId = is_array($userAnswer) ? (int)$userAnswer[0] : (int)$userAnswer;
    
    $correctAnswer = $this->answers()->where('is_correct', true)->first();
    $selectedAnswer = $this->answers()->find($answerId);
    
    if (!$selectedAnswer) {
        return [
            'is_correct' => false,
            'points_earned' => 0,
            'correct_answers' => [$correctAnswer->id],
            'user_answer' => $answerId,
            'error' => 'Selected answer not found'
        ];
    }
    
    $isCorrect = $selectedAnswer->is_correct;
    
    return [
        'is_correct' => $isCorrect,
        'points_earned' => $isCorrect ? $this->points : 0,
        'correct_answers' => [$correctAnswer->id],
        'user_answer' => $answerId
    ];
}

/**
 * Перевірка множинного вибору
 */
private function checkMultipleChoice($userAnswer): array
{
    // Для множинного вибору очікуємо масив ID відповідей
    $selectedIds = is_array($userAnswer) ? array_map('intval', $userAnswer) : [(int)$userAnswer];
    
    $correctAnswers = $this->answers()->where('is_correct', true)->pluck('id')->toArray();
    $selectedAnswers = $this->answers()->whereIn('id', $selectedIds)->get();
    
    // Перевіряємо, чи всі вибрані відповіді існують
    if ($selectedAnswers->count() !== count($selectedIds)) {
        return [
            'is_correct' => false,
            'points_earned' => 0,
            'correct_answers' => $correctAnswers,
            'user_answer' => $selectedIds,
            'error' => 'Some selected answers not found'
        ];
    }
    
    // Перевіряємо, чи збігаються вибрані відповіді з правильними
    sort($selectedIds);
    sort($correctAnswers);
    $isCorrect = $selectedIds === $correctAnswers;
    
    return [
        'is_correct' => $isCorrect,
        'points_earned' => $isCorrect ? $this->points : 0,
        'correct_answers' => $correctAnswers,
        'user_answer' => $selectedIds
    ];
}

/**
 * Перевірка текстового введення
 */
private function checkTextInput($userAnswer): array
{
    // Для текстового введення очікуємо рядок
    $userText = is_array($userAnswer) ? implode(' ', $userAnswer) : (string)$userAnswer;
    $userText = trim($userText);
    
    if (empty($userText)) {
        return [
            'is_correct' => false,
            'points_earned' => 0,
            'correct_answers' => $this->getCorrectTextAnswers(),
            'user_answer' => $userText
        ];
    }
    
    $correctAnswers = $this->answers()->where('is_correct', true)->get();
    $isCorrect = false;
    
    foreach ($correctAnswers as $correctAnswer) {
        $correctText = trim($correctAnswer->answer_text);
        
        // Порівнюємо без урахування регістру
        if (strtolower($userText) === strtolower($correctText)) {
            $isCorrect = true;
            break;
        }
        
        // Перевіряємо часткове співпадіння (опціонально)
        if ($this->allowPartialMatch() && 
            strpos(strtolower($correctText), strtolower($userText)) !== false) {
            $isCorrect = true;
            break;
        }
    }
    
    return [
        'is_correct' => $isCorrect,
        'points_earned' => $isCorrect ? $this->points : 0,
        'correct_answers' => $this->getCorrectTextAnswers(),
        'user_answer' => $userText
    ];
}

/**
 * Отримати правильні текстові відповіді
 */
private function getCorrectTextAnswers(): array
{
    return $this->answers()
        ->where('is_correct', true)
        ->pluck('answer_text')
        ->toArray();
}

/**
 * Перевірити, чи дозволено часткове співпадіння
 */
private function allowPartialMatch(): bool
{
    // Це можна зробити налаштовуваним через поле в БД або конфігурацію
    return false;
}

/**
 * Валідація типу питання
 */
public function validateQuestionType(): bool
{
    switch ($this->question_type) {
        case 'single_choice':
            // Повинна бути рівно одна правильна відповідь
            return $this->answers()->where('is_correct', true)->count() === 1;
            
        case 'multiple_choice':
            // Повинна бути принаймні одна правильна відповідь
            return $this->answers()->where('is_correct', true)->count() >= 1;
            
        case 'text_input':
            // Повинна бути принаймні одна правильна відповідь
            return $this->answers()->where('is_correct', true)->count() >= 1;
            
        default:
            return false;
    }
}

 
   /**
     * Отримати варіанти відповідей для відображення (без правильних відповідей)
     */

    public function getAnswersForDisplay()
{
    return $this->answers->map(function ($answer) {
        $answerData = [
            'id' => $answer->id,
            'answer_text' => $answer->answer_text,
            'position' => $answer->position,
        ];

        // Додаємо медіафайл відповіді, якщо є
        if ($answer->hasMedia()) {
            $answerData['media_url'] = $answer->getMediaUrl();
            $answerData['media_type'] = $answer->media_type;
            $answerData['media_original_name'] = $answer->media_original_name;
            $answerData['has_media'] = true;
        } else {
            $answerData['has_media'] = false;
        }

        return $answerData;
    });
}
}