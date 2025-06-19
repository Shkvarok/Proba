<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'country_id',
        'phone_number',
        'email',
        'password',
        'name',
        'last_name',
        'avatar',
        'role_id',
    ];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    
    /**
     * Отримати країну користувача
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
    
    /**
     * Отримати роль користувача
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
    
    /**
     * Перевірити чи користувач має певну роль
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }
    
    /**
     * Перевірити чи користувач має якусь із ролей
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->role && in_array($this->role->name, $roleNames);
    }
    
    
    /**
     * Отримати повне ім'я користувача з урахуванням прізвища
     */
    public function getFullNameAttribute(): string
    {
        $name = $this->name ?? '';
        $lastName = $this->last_name ?? '';
        
        return trim($name . ' ' . $lastName);
    }
    
    /**
     * Перевірити чи користувач є адміністратором
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super_admin']);
    }

    /**
     * Підписки користувача на курси
     */
    public function courseEnrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Платежі користувача
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Доступні курси користувача
     */
    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'course_enrollments')
            ->withPivot(['enrolled_at', 'expires_at', 'enrollment_type', 'is_active'])
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->wherePivot('expires_at', null)
                      ->orWherePivot('expires_at', '>', now());
            });
    }
    /**
 * Отримати відгуки користувача
 */
public function reviews()
{
    return $this->hasMany(Review::class);
}

/**
 * Отримати коментарі користувача до відгуків
 */
public function reviewComments()
{
    return $this->hasMany(ReviewComment::class);
}

public function lessonProgress(): HasMany
{
    return $this->hasMany(LessonProgress::class);
}

/**
 * Отримати прогрес користувача по конкретному курсу
 */
public function getCourseProgress(int $courseId): array
{
    // Загальна кількість уроків в курсі
    $totalLessons = \DB::table('lessons')
        ->join('modules', 'lessons.module_id', '=', 'modules.id')
        ->where('modules.course_id', $courseId)
        ->count();

    if ($totalLessons === 0) {
        return [
            'total_lessons' => 0,
            'completed_lessons' => 0,
            'progress_percentage' => 0,
            'is_completed' => false,
            'total_time_spent' => 0
        ];
    }

    // Кількість завершених уроків
    $completedLessons = $this->lessonProgress()
        ->whereHas('lesson.module', function($query) use ($courseId) {
            $query->where('course_id', $courseId);
        })
        ->where('is_completed', true)
        ->count();

    // Загальний час навчання
    $totalTimeSpent = $this->lessonProgress()
        ->whereHas('lesson.module', function($query) use ($courseId) {
            $query->where('course_id', $courseId);
        })
        ->sum('time_spent');

    $progressPercentage = round(($completedLessons / $totalLessons) * 100, 1);

    return [
        'total_lessons' => $totalLessons,
        'completed_lessons' => $completedLessons,
        'progress_percentage' => $progressPercentage,
        'is_completed' => $progressPercentage >= 100,
        'total_time_spent' => $totalTimeSpent ?? 0
    ];
}

/**
 * Отримати детальний прогрес по курсу з модулями та уроками
 */
public function getDetailedCourseProgress(int $courseId): array
{
    $modules = \DB::table('modules')
        ->where('course_id', $courseId)
        ->orderBy('position')
        ->get();

    $detailedProgress = [];

    foreach ($modules as $module) {
        $lessons = \DB::table('lessons')
            ->where('module_id', $module->id)
            ->orderBy('position')
            ->get();

        $moduleProgress = [
            'module_id' => $module->id,
            'module_title' => $module->title,
            'lessons' => []
        ];

        foreach ($lessons as $lesson) {
            $progress = $this->lessonProgress()
                ->where('lesson_id', $lesson->id)
                ->first();

            $moduleProgress['lessons'][] = [
                'lesson_id' => $lesson->id,
                'lesson_title' => $lesson->title,
                'lesson_type' => $lesson->type,
                'is_completed' => $progress ? $progress->is_completed : false,
                'progress_percentage' => $progress ? $progress->progress_percentage : 0,
                'time_spent' => $progress ? $progress->time_spent : 0,
                'formatted_time_spent' => $progress ? $progress->formatted_time_spent : '00:00',
                'last_accessed_at' => $progress ? $progress->last_accessed_at : null,
                'progress_status' => $progress ? 
                    ($progress->is_completed ? 'completed' : 
                        ($progress->started_at ? 'in_progress' : 'not_started')) : 'not_started'
            ];
        }

        $detailedProgress[] = $moduleProgress;
    }

    return $detailedProgress;
}

/**
 * Перевірити, чи користувач має доступ до курсу
 */
public function hasAccessToCourse(int $courseId): bool
{
    return $this->courseEnrollments()
        ->where('course_id', $courseId)
        ->where('is_active', true)
        ->where(function($query) {
            $query->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
        })
        ->exists();
}

/**
 * Отримати прогрес по всіх курсах користувача
 */
public function getAllCoursesProgress(): array
{
    $enrollments = $this->courseEnrollments()
        ->where('is_active', true)
        ->with('course')
        ->get();

    $coursesProgress = [];

    foreach ($enrollments as $enrollment) {
        $progress = $this->getCourseProgress($enrollment->course_id);
        
        $coursesProgress[] = [
            'course_id' => $enrollment->course_id,
            'course_title' => $enrollment->course->title,
            'enrolled_at' => $enrollment->enrolled_at,
            'expires_at' => $enrollment->expires_at,
            'progress' => $progress
        ];
    }

    return $coursesProgress;
}

/**
 * Позначити урок як розпочатий
 */
public function startLesson(int $lessonId): LessonProgress
{
    $progress = LessonProgress::firstOrCreate(
        [
            'user_id' => $this->id,
            'lesson_id' => $lessonId,
        ],
        [
            'started_at' => now(),
            'last_accessed_at' => now(),
        ]
    );

    if (!$progress->started_at) {
        $progress->markAsStarted();
    } else {
        $progress->last_accessed_at = now();
        $progress->save();
    }

    return $progress;
}

/**
 * Позначити урок як завершений
 */
public function completeLesson(int $lessonId): LessonProgress
{
    $progress = LessonProgress::firstOrCreate(
        [
            'user_id' => $this->id,
            'lesson_id' => $lessonId,
        ]
    );

    return $progress->markAsCompleted();
}

/**
 * Оновити прогрес уроку
 */
public function updateLessonProgress(int $lessonId, float $percentage, int $timeSpent = 0): LessonProgress
{
    $progress = LessonProgress::firstOrCreate(
        [
            'user_id' => $this->id,
            'lesson_id' => $lessonId,
        ]
    );

    return $progress->updateProgress($percentage, $timeSpent);
}

/**
 * Отримати останню активність користувача по урокам
 */
public function getRecentLessonActivity(int $limit = 10): \Illuminate\Database\Eloquent\Collection
{
    return $this->lessonProgress()
        ->with(['lesson.module.course'])
        ->whereNotNull('last_accessed_at')
        ->orderBy('last_accessed_at', 'desc')
        ->limit($limit)
        ->get();
}

/**
 * Отримати статистику навчання користувача
 */
public function getLearningStats(): array
{
    $totalCourses = $this->courseEnrollments()
        ->where('is_active', true)
        ->count();

    $completedCourses = 0;
    $totalProgress = 0;
    $totalTimeSpent = 0;

    $enrollments = $this->courseEnrollments()
        ->where('is_active', true)
        ->get();

    foreach ($enrollments as $enrollment) {
        $progress = $this->getCourseProgress($enrollment->course_id);
        
        if ($progress['is_completed']) {
            $completedCourses++;
        }
        
        $totalProgress += $progress['progress_percentage'];
        $totalTimeSpent += $progress['total_time_spent'];
    }

    $averageProgress = $totalCourses > 0 ? round($totalProgress / $totalCourses, 1) : 0;

    return [
        'total_courses' => $totalCourses,
        'completed_courses' => $completedCourses,
        'in_progress_courses' => $totalCourses - $completedCourses,
        'average_progress' => $averageProgress,
        'total_time_spent' => $totalTimeSpent,
        'total_lessons_completed' => $this->lessonProgress()->completed()->count(),
        'last_activity' => $this->lessonProgress()
            ->orderBy('last_accessed_at', 'desc')
            ->first()
            ?->last_accessed_at
    ];
}

/**
 * Перевірити, чи користувач є вчителем
 */
public function isTeacher(): bool
{
    return $this->role && in_array($this->role->name, ['teacher', 'admin', 'super_admin']);
}



/**
 * Перевірити, чи користувач є супер адміном
 */
public function isSuperAdmin(): bool
{
    return $this->role && $this->role->name === 'super_admin';
}

/**
 * Перевірити чи користувач завершив конкретний урок
 */
public function hasCompletedLesson(int $lessonId): bool
{
    return $this->lessonProgress()
        ->where('lesson_id', $lessonId)
        ->where('is_completed', true)
        ->exists();
}

/**
 * Перевірити чи користувач завершив конкретний курс
 */
public function hasCompletedCourse(int $courseId): bool
{
    $course = Course::findOrFail($courseId);
    return $course->isCompletedByUser($this->id);
}

/**
 * Отримати наступний урок для вивчення в курсі
 */
public function getNextLessonInCourse(int $courseId): ?Lesson
{
    $course = Course::findOrFail($courseId);
    return $course->getNextLessonForUser($this->id);
}

}