<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonExtraMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'material_type', // 'text', 'url', 'file', 'image', 'video'
        'content',
        'file_path',
        'file_type',
        'file_name',
        'url',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}