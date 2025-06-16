<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'category_id',
        'instructor_id',
        'price',
        'discount_price',
        'discount_expires_at',
        'level_id',
        'language',
        'cover_image',
        'promo_video_url',
        'requirements',
        'what_you_learn',
        'is_published',
        'meta_title',
        'meta_description'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'discount_expires_at' => 'datetime',
        'is_published' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'cover_image_url',
        'is_on_discount',
        'current_price'
    ];

    /**
     * Get the category that owns the course.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the instructor that owns the course.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Get the level that owns the course.
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * Get the modules for the course.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('position');
    }

    /**
     * Підписки на цей курс
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Активні підписки на цей курс
     */
    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Користувачі, підписані на цей курс
     */
    public function enrolledUsers()
    {
        return $this->belongsToMany(User::class, 'course_enrollments')
            ->withPivot(['enrolled_at', 'expires_at', 'enrollment_type', 'is_active'])
            ->wherePivot('is_active', true)
            ->where(function($query) {
                $query->wherePivotNull('expires_at')
                      ->orWherePivot('expires_at', '>', now());
            });
    }

    /**
     * Отримати відгуки до курсу
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Отримати схвалені відгуки до курсу
     */
    public function approvedReviews(): HasMany
    {
        return $this->reviews()->approved();
    }

    /**
     * Scope a query to only include published courses.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope a query to include courses with valid discount.
     */
    public function scopeWithValidDiscount($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('discount_expires_at')
              ->orWhere('discount_expires_at', '>', now());
        })->whereNotNull('discount_price');
    }

    /**
     * Scope для пошуку
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('title', 'LIKE', "%{$searchTerm}%")
              ->orWhere('description', 'LIKE', "%{$searchTerm}%")
              ->orWhere('requirements', 'LIKE', "%{$searchTerm}%")
              ->orWhere('what_you_learn', 'LIKE', "%{$searchTerm}%");
        });
    }

    /**
     * Determine if the course is on discount.
     */
    public function isOnDiscount(): bool
    {
        if (is_null($this->discount_price)) {
            return false;
        }

        if (is_null($this->discount_expires_at)) {
            return true;
        }

        return $this->discount_expires_at > now();
    }

    /**
     * Get the current price (considering discount).
     */
    public function getCurrentPrice(): float
    {
        if ($this->isOnDiscount()) {
            return (float) $this->discount_price;
        }

        return (float) $this->price;
    }

    /**
     * Get the cover image URL attribute.
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }

    /**
     * Get is on discount attribute.
     */
    public function getIsOnDiscountAttribute(): bool
    {
        return $this->isOnDiscount();
    }

    /**
     * Get current price attribute.
     */
    public function getCurrentPriceAttribute(): float
    {
        return $this->getCurrentPrice();
    }

    /**
     * Отримати середній рейтинг курсу
     */
    public function getAverageRatingAttribute(): float
    {
        return round($this->approvedReviews()->avg('rating') ?: 0, 1);
    }

    /**
     * Отримати кількість відгуків до курсу
     */
    public function getReviewsCountAttribute(): int
    {
        return $this->approvedReviews()->count();
    }

    /**
     * Отримати кількість модулів
     */
    public function getModulesCountAttribute(): int
    {
        return $this->modules()->count();
    }

    /**
     * Отримати кількість уроків
     */
    public function getLessonsCountAttribute(): int
    {
        return Lesson::whereHas('module', function($query) {
            $query->where('course_id', $this->id);
        })->count();
    }

    /**
     * Отримати кількість активних підписок
     */
    public function getActiveEnrollmentsCountAttribute(): int
    {
        return $this->activeEnrollments()->count();
    }

    /**
     * Перевірити чи курс безкоштовний
     */
    public function isFree(): bool
    {
        return $this->getCurrentPrice() == 0;
    }

    /**
     * Отримати тривалість курсу в хвилинах
     */
    public function getTotalDurationAttribute(): int
    {
        return $this->modules()->with('lessons.lecture')->get()->sum(function($module) {
            return $module->lessons->sum(function($lesson) {
                if ($lesson->type === 'lecture' && $lesson->lecture) {
                    return $lesson->lecture->duration_minutes ?? 0;
                }
                return 0;
            });
        });
    }



    /**
     * Визначити чи користувач має доступ до курсу
     */
    public function hasUserAccess(int $userId): bool
    {
        // Перевірити чи користувач підписаний
        $enrollment = $this->enrollments()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($enrollment) {
            return true;
        }

        // Перевірити чи користувач є інструктором курсу
        if ($this->instructor_id === $userId) {
            return true;
        }

        // Перевірити чи користувач адміністратор
        $user = User::find($userId);
        if ($user && ($user->hasRole('admin') || $user->hasRole('super_admin'))) {
            return true;
        }

        return false;
    }

    /**
     * Отримати статистику курсу
     */
    public function getStatsAttribute(): array
    {
        return [
            'modules_count' => $this->modules_count,
            'lessons_count' => $this->lessons_count,
            'enrollments_count' => $this->active_enrollments_count,
            'reviews_count' => $this->reviews_count,
            'average_rating' => $this->average_rating,
            'total_duration' => $this->total_duration,
            'completion_rate' => $this->completion_rate,
            'is_free' => $this->isFree()
        ];
    }

    /**
     * Перевірити чи курс готовий до публікації
     */
    public function isReadyForPublication(): bool
    {
        // Курс готовий до публікації якщо:
        // 1. Має назву
        // 2. Має опис
        // 3. Має категорію
        // 4. Має рівень складності
        // 5. Має хоча б один модуль
        // 6. Має хоча б один урок

        if (empty($this->title) || empty($this->description) || 
            !$this->category_id || !$this->level_id) {
            return false;
        }

        $modulesCount = $this->modules()->count();
        if ($modulesCount === 0) {
            return false;
        }

        $lessonsCount = $this->lessons_count;
        if ($lessonsCount === 0) {
            return false;
        }

        return true;
    }

    /**
     * Отримати відсутні вимоги для публікації
     */
    public function getPublicationRequirements(): array
    {
        $requirements = [];

        if (empty($this->title)) {
            $requirements[] = 'Додайте назву курсу';
        }

        if (empty($this->description)) {
            $requirements[] = 'Додайте опис курсу';
        }

        if (!$this->category_id) {
            $requirements[] = 'Оберіть категорію курсу';
        }

        if (!$this->level_id) {
            $requirements[] = 'Оберіть рівень складності';
        }

        if ($this->modules()->count() === 0) {
            $requirements[] = 'Додайте хоча б один модуль';
        }

        if ($this->lessons_count === 0) {
            $requirements[] = 'Додайте хоча б один урок';
        }

        if (empty($this->cover_image)) {
            $requirements[] = 'Додайте обкладинку курсу';
        }

        return $requirements;
    }

     /**
     * Отримати всі уроки курсу через модулі
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    /**
     * Отримати прогрес користувача по курсу
     */
    public function getUserProgress(int $userId): array
    {
        // Отримуємо всі уроки курсу
        $totalLessons = $this->lessons()->count();
        
        if ($totalLessons === 0) {
            return [
                'course_id' => $this->id,
                'user_id' => $userId,
                'total_lessons' => 0,
                'completed_lessons' => 0,
                'started_lessons' => 0,
                'progress_percentage' => 0,
                'total_time_spent' => 0,
                'started_at' => null,
                'last_activity_at' => null,
                'is_completed' => false,
            ];
        }

        // Отримуємо прогрес по всіх уроках курсу
        $progressStats = DB::table('lesson_progress')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->where('modules.course_id', $this->id)
            ->where('lesson_progress.user_id', $userId)
            ->selectRaw('
                COUNT(*) as lessons_with_progress,
                SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_lessons,
                SUM(CASE WHEN started_at IS NOT NULL THEN 1 ELSE 0 END) as started_lessons,
                AVG(progress_percentage) as avg_progress,
                SUM(time_spent) as total_time_spent,
                MIN(started_at) as first_started_at,
                MAX(last_accessed_at) as last_activity_at
            ')
            ->first();

        $completedLessons = $progressStats->completed_lessons ?? 0;
        $startedLessons = $progressStats->started_lessons ?? 0;
        $avgProgress = $progressStats->avg_progress ?? 0;
        $totalTimeSpent = $progressStats->total_time_spent ?? 0;

        // Розраховуємо загальний прогрес курсу
        $overallProgress = $totalLessons > 0 ? ($completedLessons / $totalLessons) * 100 : 0;

        return [
            'course_id' => $this->id,
            'user_id' => $userId,
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessons,
            'started_lessons' => $startedLessons,
            'progress_percentage' => round($overallProgress, 2),
            'average_lesson_progress' => round($avgProgress, 2),
            'total_time_spent' => $totalTimeSpent,
            'started_at' => $progressStats->first_started_at,
            'last_activity_at' => $progressStats->last_activity_at,
            'is_completed' => $completedLessons === $totalLessons && $totalLessons > 0,
        ];
    }

    /**
     * Перевірити чи курс завершений користувачем
     */
    public function isCompletedByUser(int $userId): bool
    {
        $progress = $this->getUserProgress($userId);
        return $progress['is_completed'];
    }

    /**
     * Отримати відсоток завершення курсу для користувача
     */
    public function getCompletionPercentageForUser(int $userId): float
    {
        $progress = $this->getUserProgress($userId);
        return $progress['progress_percentage'];
    }

    /**
     * Отримати наступний незавершений урок для користувача
     */
    public function getNextLessonForUser(int $userId): ?Lesson
    {
        // Знаходимо перший урок, який не завершений або не розпочатий
        return $this->lessons()
            ->leftJoin('lesson_progress', function($join) use ($userId) {
                $join->on('lessons.id', '=', 'lesson_progress.lesson_id')
                     ->where('lesson_progress.user_id', '=', $userId);
            })
            ->whereNull('lesson_progress.id')
            ->orWhere('lesson_progress.is_completed', false)
            ->orderBy('modules.position')
            ->orderBy('lessons.position')
            ->select('lessons.*')
            ->first();
    }

    /**
     * Отримати останній переглянутий урок користувачем
     */
    public function getLastAccessedLessonForUser(int $userId): ?Lesson
    {
        return $this->lessons()
            ->join('lesson_progress', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->where('lesson_progress.user_id', $userId)
            ->whereNotNull('lesson_progress.last_accessed_at')
            ->orderBy('lesson_progress.last_accessed_at', 'desc')
            ->select('lessons.*')
            ->first();
    }

    /**
     * Отримати статистику завершення курсу (для всіх користувачів)
     */
    public function getCompletionStats(): array
    {
        $enrolledUsers = $this->activeEnrollments()->count();
        
        if ($enrolledUsers === 0) {
            return [
                'total_enrolled' => 0,
                'completed_count' => 0,
                'completion_rate' => 0,
                'average_progress' => 0,
                'average_time_spent' => 0,
            ];
        }

        $totalLessons = $this->lessons()->count();
        
        if ($totalLessons === 0) {
            return [
                'total_enrolled' => $enrolledUsers,
                'completed_count' => 0,
                'completion_rate' => 0,
                'average_progress' => 0,
                'average_time_spent' => 0,
            ];
        }

        // Отримуємо статистику по всіх користувачах
        $completionStats = DB::table('course_enrollments')
            ->join('lesson_progress', 'course_enrollments.user_id', '=', 'lesson_progress.user_id')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->where('course_enrollments.course_id', $this->id)
            ->where('course_enrollments.is_active', true)
            ->where('modules.course_id', $this->id)
            ->selectRaw('
                course_enrollments.user_id,
                COUNT(lessons.id) as total_lessons_with_progress,
                SUM(CASE WHEN lesson_progress.is_completed = 1 THEN 1 ELSE 0 END) as completed_lessons,
                AVG(lesson_progress.progress_percentage) as avg_progress,
                SUM(lesson_progress.time_spent) as total_time_spent
            ')
            ->groupBy('course_enrollments.user_id')
            ->get();

        $completedUsers = 0;
        $totalProgress = 0;
        $totalTimeSpent = 0;

        foreach ($completionStats as $userStats) {
            $userCompletionRate = $userStats->completed_lessons / $totalLessons * 100;
            $totalProgress += $userCompletionRate;
            $totalTimeSpent += $userStats->total_time_spent;
            
            if ($userCompletionRate >= 100) {
                $completedUsers++;
            }
        }

        return [
            'total_enrolled' => $enrolledUsers,
            'completed_count' => $completedUsers,
            'completion_rate' => $enrolledUsers > 0 ? round($completedUsers / $enrolledUsers * 100, 2) : 0,
            'average_progress' => $enrolledUsers > 0 ? round($totalProgress / $enrolledUsers, 2) : 0,
            'average_time_spent' => $enrolledUsers > 0 ? round($totalTimeSpent / $enrolledUsers) : 0,
        ];
    }

    /**
     * Оновлений атрибут completion_rate
     */
    public function getCompletionRateAttribute(): float
    {
        $stats = $this->getCompletionStats();
        return $stats['completion_rate'];
    }
}