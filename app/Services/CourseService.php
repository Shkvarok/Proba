<?php

namespace App\Services;

use App\Models\Course;
use App\Repositories\CourseRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourseService
{
    /**
     * @var CourseRepository
     */
    protected $courseRepository;

    /**
     * CourseService constructor.
     *
     * @param CourseRepository $courseRepository
     */
    public function __construct(CourseRepository $courseRepository)
    {
        $this->courseRepository = $courseRepository;
    }

    /**
     * Get all courses with pagination.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllCourses(int $perPage = 15): LengthAwarePaginator
    {
        return $this->courseRepository->getAllPaginated($perPage);
    }

    /**
     * Get all published courses with pagination.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPublishedCourses(int $perPage = 15): LengthAwarePaginator
    {
        return $this->courseRepository->getPublishedPaginated($perPage);
    }


    /**
     * Get course by ID.
     *
     * @param int $id
     * @return Course
     * @throws Exception
     */
    public function getCourseById(int $id): Course
    {
        $course = $this->courseRepository->findById($id);
        
        if (!$course) {
            throw new Exception("Курс з ID {$id} не знайдено", 404);
        }
        
        return $course;
    }

    /**
     * Get courses by category with pagination.
     *
     * @param int $categoryId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getCoursesByCategory(int $categoryId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->courseRepository->getByCategoryPaginated($categoryId, $perPage);
    }

    /**
     * Get courses by level with pagination.
     *
     * @param int $levelId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getCoursesByLevel(int $levelId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->courseRepository->getByLevelPaginated($levelId, $perPage);
    }

    /**
     * Get courses by instructor with pagination.
     *
     * @param int $instructorId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getCoursesByInstructor(int $instructorId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->courseRepository->getByInstructorPaginated($instructorId, $perPage);
    }

    /**
     * Create a new course.
     *
     * @param array $data
     * @param UploadedFile|null $coverImage
     * @return Course
     */
     public function createCourse(array $data, ?UploadedFile $coverImage = null): Course
    {
    // Логування даних перед створенням
        \Log::info('CourseService::createCourse called with data:', $data);
        
        // Переконуємося що thumbnail включений в fillable поля моделі
        $course = Course::create($data);

        // Обробка зображення обкладинки
        if ($coverImage) {
            $data['cover_image'] = $this->uploadCoverImage($coverImage);
        }
        
        // Створення мета-заголовка, якщо він не вказаний
        if (!isset($data['meta_title']) || empty($data['meta_title'])) {
            $data['meta_title'] = $data['title'];
        }
        
        // Створення мета-опису, якщо він не вказаний
        if (!isset($data['meta_description']) || empty($data['meta_description'])) {
            $data['meta_description'] = Str::limit(strip_tags($data['description'] ?? ''), 160);
        }
        
        // Встановлюємо автора курсу
        // Якщо користувач є адміністратором або супер-адміністратором і не вказав instructor_id,
        // встановлюємо instructor_id = 1
        if (auth()->user()->isAdmin() && (!isset($data['instructor_id']) || empty($data['instructor_id']))) {
            $data['instructor_id'] = 1;
        } else {
            // В іншому випадку (для вчителів), автор - це поточний користувач
            $data['instructor_id'] = auth()->id();
        }

        // Логування після створення
        Log::info('Course created in database:', [
            'id' => $course->id,
            'cover_image' => $course->cover_image,  // ← На це
            'all_attributes' => $course->toArray()
        ]);
        
        return Course::create($data);
    }

    // Метод для перевірки, чи може користувач редагувати курс
 public function canUserManageCourse(int $userId, int $courseId): bool
    {
        $course = Course::findOrFail($courseId);
        
        // Перевіряємо, чи користувач є автором курсу або адміністратором
        return $course->instructor_id === $userId || auth()->user()->isAdmin();
    }

    /**
     * Update a course.
     *
     * @param Course $course
     * @param array $data
     * @param UploadedFile|null $coverImage
     * @return Course
     */
    public function updateCourse(Course $course, array $data, ?UploadedFile $coverImage = null): Course
    {
        // Обробка зображення обкладинки
        if ($coverImage) {
            // Видалення старого зображення
            if ($course->cover_image) {
                $this->deleteCoverImage($course->cover_image);
            }
            
            $data['cover_image'] = $this->uploadCoverImage($coverImage);
        }

        // Оновлення мета-заголовка, якщо змінився заголовок і мета-заголовок не вказаний
        if (isset($data['title']) && (!isset($data['meta_title']) || empty($data['meta_title']))) {
            $data['meta_title'] = $data['title'];
        }

        // Оновлення мета-опису, якщо змінився опис і мета-опис не вказаний
        if (isset($data['description']) && (!isset($data['meta_description']) || empty($data['meta_description']))) {
            $data['meta_description'] = Str::limit(strip_tags($data['description']), 160);
        }

        return $this->courseRepository->update($course, $data);
    }

    /**
     * Delete a course.
     *
     * @param Course $course
     * @return bool|null
     */
    public function deleteCourse(Course $course): ?bool
    {
        // Видалення зображення обкладинки
        if ($course->cover_image) {
            $this->deleteCoverImage($course->cover_image);
        }

        return $this->courseRepository->delete($course);
    }

    /**
     * Search courses by query.
     *
     * @param string $query
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchCourses(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return $this->courseRepository->search($query, $perPage);
    }

    /**
     * Publish a course.
     *
     * @param Course $course
     * @return Course
     */
    public function publishCourse(Course $course): Course
    {
        return $this->courseRepository->publish($course);
    }

    /**
     * Unpublish a course.
     *
     * @param Course $course
     * @return Course
     */
    public function unpublishCourse(Course $course): Course
    {
        return $this->courseRepository->unpublish($course);
    }

    /**
     * Upload course cover image.
     *
     * @param UploadedFile $image
     * @return string
     */
    protected function uploadCoverImage(UploadedFile $image): string
    {
        $filename = 'course_' . time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
        
        $path = $image->storeAs('course-covers', $filename, 'public');
        
        return $path;
    }

    /**
     * Delete course cover image.
     *
     * @param string $path
     * @return bool
     */
    protected function deleteCoverImage(string $path): bool
    {
        return Storage::disk('public')->delete($path);
    }

      public function getCoursesByInstructorId(int $instructorId): Collection
    {
        return Course::where('instructor_id', $instructorId)
            ->with(['category', 'level', 'instructor'])
            ->latest()
            ->get();
    }
      /**
     * Get courses enrolled by user
     * 
     * @param int $userId
     * @return Collection
     */
    public function getEnrolledCoursesByUserId(int $userId): Collection
    {
        // Для повноцінної роботи цього методу нам потрібно буде створити
        // таблицю з записами студентів на курси (enrollments)
        // Спрощений варіант:
        
        // В реальному випадку ви б шукали через відношення:
        // return $user->enrolledCourses()->with(['category', 'level', 'instructor'])->get();
        
        // Оскільки таблиці enrollments ще може не бути, повертаємо порожню колекцію
        return collect([]);
        
        // Закоментуйте рядок вище і розкоментуйте цей код, коли створите
        // таблицю та модель для enrollments:
        
        /*
        return Course::whereHas('enrollments', function($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->where('is_active', true)
                  ->where(function($q) {
                      $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                  });
        })
        ->with(['category', 'level', 'instructor'])
        ->get();
        */
    }

    /**
     * Get user progress for specific course
     * 
     * @param int $userId
     * @param int $courseId
     * @return array
     */
    public function getCourseProgressForUser(int $userId, int $courseId): array
    {
        // Для повноцінної роботи цього методу нам потрібно буде створити
        // таблиці для відстеження прогресу користувача
        // Спрощений варіант:
        
        $course = Course::with(['modules.lessons'])->findOrFail($courseId);
        
        // Підраховуємо загальну кількість уроків
        $totalLessons = 0;
        foreach ($course->modules as $module) {
            $totalLessons += $module->lessons->count();
        }
        
        // В реальному випадку ви б шукали кількість завершених уроків через відношення
        // Поки що повертаємо тестові дані
        $completedLessons = 0;
        $progress = $totalLessons > 0 ? round($completedLessons / $totalLessons * 100, 2) : 0;
        
    return [
        'course_id' => $courseId,
        'user_id' => $userId,
        'total_lessons' => $totalLessons,
        'completed_lessons' => $completedLessons,
        'progress_percentage' => $progress,
        'started_at' => null,
        'last_activity_at' => null
    ];
    
    // Закоментуйте код вище і розкоментуйте цей, коли створите
    // необхідні таблиці для відстеження прогресу:
    
    /*
    $progress = UserCourseProgress::where('user_id', $userId)
        ->where('course_id', $courseId)
        ->first();
        
    if (!$progress) {
        // Якщо запис прогресу не знайдено, створюємо новий
        $course = Course::with(['modules.lessons'])->findOrFail($courseId);
        
        // Підраховуємо загальну кількість уроків
        $totalLessons = 0;
        foreach ($course->modules as $module) {
            $totalLessons += $module->lessons->count();
        }
        
        return [
            'course_id' => $courseId,
            'user_id' => $userId,
            'total_lessons' => $totalLessons,
            'completed_lessons' => 0,
            'progress_percentage' => 0,
            'started_at' => null,
            'last_activity_at' => null
        ];
    }
    
    // Інакше повертаємо існуючий прогрес
    return [
        'course_id' => $progress->course_id,
        'user_id' => $progress->user_id,
        'total_lessons' => $progress->total_lessons,
        'completed_lessons' => $progress->completed_lessons,
        'progress_percentage' => $progress->completion_percentage,
        'started_at' => $progress->started_at,
        'last_activity_at' => $progress->last_accessed_at
    ];
    */
}

public function searchCoursesWithFilters(string $query, int $perPage = 15, array $filters = [])
{
    $queryBuilder = Course::query()
        ->with(['category', 'level', 'instructor'])
        ->where('is_published', true);

    // Пошук по назві та опису
    $queryBuilder->where(function ($q) use ($query) {
        $q->where('title', 'LIKE', "%{$query}%")
        ->orWhere('description', 'LIKE', "%{$query}%")
        ->orWhere('requirements', 'LIKE', "%{$query}%")
        ->orWhere('what_you_learn', 'LIKE', "%{$query}%");
    });

    // Застосування фільтрів
    if (!empty($filters['category_id'])) {
        $queryBuilder->where('category_id', $filters['category_id']);
    }

    if (!empty($filters['level_id'])) {
        $queryBuilder->where('level_id', $filters['level_id']);
    }

    if (!empty($filters['is_free'])) {
        if ($filters['is_free']) {
            $queryBuilder->where('is_free', true);
        } else {
            $queryBuilder->where('is_free', false);
        }
    }

    if (!empty($filters['price_min'])) {
        $queryBuilder->where('price', '>=', $filters['price_min']);
    }

    if (!empty($filters['price_max'])) {
        $queryBuilder->where('price', '<=', $filters['price_max']);
    }

    if (!empty($filters['instructor_id'])) {
        $queryBuilder->where('instructor_id', $filters['instructor_id']);
    }

    if (!empty($filters['language'])) {
        $queryBuilder->where('language', $filters['language']);
    }

    if (!empty($filters['difficulty_level'])) {
        $queryBuilder->where('difficulty_level', $filters['difficulty_level']);
    }

    // Сортування
    $sortBy = $filters['sort_by'] ?? 'created_at';
    $sortDirection = $filters['sort_direction'] ?? 'desc';
    
    $allowedSortFields = ['created_at', 'title', 'price', 'updated_at'];
    if (in_array($sortBy, $allowedSortFields)) {
        $queryBuilder->orderBy($sortBy, $sortDirection);
    }

    return $queryBuilder->paginate($perPage);
}

/**
 * Get popular courses
 */
public function getPopularCourses(int $limit = 10)
{
    return Course::query()
        ->with(['category', 'level', 'instructor'])
        ->where('is_published', true)
        ->withCount('enrollments')
        ->orderBy('enrollments_count', 'desc')
        ->limit($limit)
        ->get();
}

/**
 * Get featured courses
 */
public function getFeaturedCourses(int $limit = 6)
{
    return Course::query()
        ->with(['category', 'level', 'instructor'])
        ->where('is_published', true)
        ->where('is_featured', true) // Припускаємо, що у вас є поле is_featured
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();
}

/**
 * Get courses statistics
 */
public function getCoursesStatistics()
{
    return [
        'total_courses' => Course::count(),
        'published_courses' => Course::where('is_published', true)->count(),
        'free_courses' => Course::where('is_free', true)->count(),
        'paid_courses' => Course::where('is_free', false)->count(),
        'courses_by_category' => Course::select('category_id')
            ->with('category:id,name')
            ->groupBy('category_id')
            ->get()
            ->groupBy('category.name')
            ->map->count(),
        'courses_by_level' => Course::select('level_id')
            ->with('level:id,name')
            ->groupBy('level_id')
            ->get()
            ->groupBy('level.name')
            ->map->count(),
    ];
}

/**
 * Get recommended courses for user
 */
public function getRecommendedCourses(int $userId, int $limit = 5)
{
    // Отримуємо категорії курсів, на які користувач вже записаний
    $userCategoryIds = Course::query()
        ->join('course_enrollments', 'courses.id', '=', 'course_enrollments.course_id')
        ->where('course_enrollments.user_id', $userId)
        ->pluck('courses.category_id')
        ->unique();

    if ($userCategoryIds->isEmpty()) {
        // Якщо користувач ще не записаний на курси, повертаємо популярні
        return $this->getPopularCourses($limit);
    }

    // Знаходимо курси з тих же категорій, на які користувач ще не записаний
    return Course::query()
        ->with(['category', 'level', 'instructor'])
        ->where('is_published', true)
        ->whereIn('category_id', $userCategoryIds)
        ->whereNotExists(function ($query) use ($userId) {
            $query->select(DB::raw(1))
                ->from('course_enrollments')
                ->whereColumn('course_enrollments.course_id', 'courses.id')
                ->where('course_enrollments.user_id', $userId);
        })
        ->withCount('enrollments')
        ->orderBy('enrollments_count', 'desc')
        ->limit($limit)
        ->get();
}
}
