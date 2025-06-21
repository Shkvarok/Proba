<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonLecture extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'content',
        'content_type', // 'text', 'file' або 'mixed'
        'file_path',
        'file_type',
        'file_name',
        'duration_minutes',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}

