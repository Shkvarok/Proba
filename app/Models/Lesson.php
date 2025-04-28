<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'type',
        'order',
        'file_path',
        'video_url',
        'test_url',
        'is_free',
        'duration_minutes',
        'is_published'
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'is_published' => 'boolean',
        'duration_minutes' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Отримати курс, до якого належить цей урок
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Отримати шлях до файлу з урахуванням типу уроку
     */
    public function getContentUrlAttribute()
    {
        switch ($this->type) {
            case 'lecture':
                return $this->file_path;
            case 'video':
                return $this->video_url;
            case 'test':
                return $this->test_url;
            default:
                return null;
        }
    }

    /**
     * Отримати уроки, відсортовані за порядком
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    /**
     * Отримати лише опубліковані уроки
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}