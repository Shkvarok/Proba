<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'payment_method',
        'payment_status',
        'transaction_id',
        'entity_type',
        'entity_id',
        'discount_amount',
        'original_amount',
        'promo_code_id'
    ];

    /**
     * Користувач, який здійснив платіж
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Підписка на курс, пов'язана з платежем
     */
    public function enrollment(): HasOne
    {
        return $this->hasOne(CourseEnrollment::class);
    }

    /**
     * Курс, пов'язаний з платежем (тільки якщо entity_type == 'course')
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'entity_id');
    }

    /**
     * Динамічне отримання пов'язаної сутності
     * ВИПРАВЛЕНО: повертаємо null замість проблемного зв'язку
     */
    public function entity()
    {
        if ($this->entity_type === 'course') {
            return $this->course();
        }
        
        // Повертаємо null замість проблемного зв'язку
        return null;
    }

    /**
     * Отримання сутності як атрибута (безпечно)
     */
    public function getEntityAttribute()
    {
        if ($this->entity_type === 'course') {
            return $this->course;
        }
        
        return null;
    }

    /**
     * Атрибут для отримання назви сутності
     */
    public function getEntityNameAttribute(): ?string
    {
        if ($this->entity_type === 'course') {
            $course = $this->course;
            return $course ? $course->title : "Курс ID: {$this->entity_id}";
        }
        
        return "Невідома сутність";
    }

    /**
     * Scope для завантаження з курсом
     */
    public function scopeWithCourse($query)
    {
        return $query->with('course');
    }

    /**
     * Scope для завантаження з користувачем
     */
    public function scopeWithUser($query)
    {
        return $query->with('user');
    }
}