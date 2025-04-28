<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'access_granted_at',
        'expires_at',
        'is_active',
        'granted_by'
    ];

    protected $casts = [
        'access_granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Отримати студента
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Отримати курс
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Отримати адміністратора, який надав доступ
     */
    public function grantor()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Перевірка, чи доступ активний
     */
    public function isActive()
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && now()->greaterThan($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Скоуп для отримання активних доступів
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }
}