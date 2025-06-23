<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'enrolled_at',
        'expires_at',
        'enrollment_type',
        'payment_id',
        'is_active',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Курс, на який підписаний користувач
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Користувач, який підписаний на курс
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Платіж, пов'язаний з підпискою
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Перевірка, чи активна підписка
     */
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at === null) {
            return true; // Безстроковий доступ
        }

        return $this->expires_at->isFuture();
    }
    
    /**
     * Отримати кількість днів до закінчення підписки
     */
    public function getRemainingDays(): ?int
    {
        if ($this->expires_at === null) {
            return null; // Безстроковий доступ
        }
        
        if ($this->expires_at->isPast()) {
            return 0; // Підписка закінчилася
        }
        
        return (int) $this->expires_at->diffInDays(now());
    }
    
    /**
     * Отримати статус підписки у вигляді тексту
     */
    public function getStatusText(): string
    {
        if (!$this->is_active) {
            return 'Неактивна';
        }
        
        if ($this->expires_at === null) {
            return 'Безстроковий доступ';
        }
        
        if ($this->expires_at->isPast()) {
            return 'Закінчилася';
        }
        
        $days = $this->getRemainingDays();
        
        if ($days === 0) {
            return 'Закінчується сьогодні';
        } elseif ($days === 1) {
            return 'Закінчується завтра';
        } else {
            return "Залишилося {$days} днів";
        }
    }
    
    /**
     * Scope для активних підписок
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }
    
    /**
     * Scope для підписок, що закінчуються
     */
    public function scopeExpiring($query, $days = 7)
    {
        return $query->where('is_active', true)
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }
}