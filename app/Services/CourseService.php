<?php

namespace App\Services;

use App\Models\Course;
use App\Repositories\CourseRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Collection;

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
}