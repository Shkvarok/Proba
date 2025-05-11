<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'source_type',
        'external_url',
        'time_limit_minutes',
        'passing_score',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}

