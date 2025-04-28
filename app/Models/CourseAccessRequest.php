<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseAccessRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'comment',
        'processed_by',
        'processed_at'
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    /**
     * Константи для статусів
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Отримати користувача, який подав запит
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Отримати курс, на який подається запит
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Отримати адміністратора, який обробив запит
     */
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Перевірка, чи запит очікує на розгляд
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Перевірка, чи запит схвалено
     */
    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Перевірка, чи запит відхилено
     */
    public function isRejected()
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Скоуп для отримання запитів, що очікують на розгляд
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}