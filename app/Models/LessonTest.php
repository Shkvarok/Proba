<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LessonTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'source_type', // 'internal' або 'external'
        'external_url',
        'time_limit_minutes',
        'passing_score',
    ];

    protected $casts = [
        'time_limit_minutes' => 'integer',
        'passing_score' => 'integer',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Внутрішній тест (якщо source_type === 'internal')
     */
    public function internalTest(): HasOne
    {
        return $this->hasOne(InternalTest::class, 'lesson_id', 'lesson_id');
    }

    /**
     * Перевірити, чи це внутрішній тест
     */
    public function isInternal(): bool
    {
        return $this->source_type === 'internal';
    }

    /**
     * Перевірити, чи це зовнішній тест
     */
    public function isExternal(): bool
    {
        return $this->source_type === 'external';
    }

    /**
     * Отримати активний тест (внутрішній або зовнішній)
     */
    public function getActiveTest()
    {
        if ($this->isInternal()) {
            return $this->internalTest;
        }
        
        return $this; // Для зовнішніх тестів повертаємо сам LessonTest
    }
}