<?php

namespace App\Services;

use App\Models\Course;
use App\Repositories\CourseRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

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

        return $this->courseRepository->create($data);
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
}