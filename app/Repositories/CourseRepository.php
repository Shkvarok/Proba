<?php

namespace App\Repositories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CourseRepository
{
    /**
     * Get all courses with pagination.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Course::latest()->paginate($perPage);
    }

    /**
     * Get all published courses with pagination.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPublishedPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Course::with(['category', 'level', 'instructor'])
            ->published()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get course by ID.
     *
     * @param int $id
     * @return Course|null
     */    public function findById(int $id): ?Course
{
    return Course::with([
        'category', 
        'level', 
        'instructor',
        'modules' => function($query) {
            $query->orderBy('position');
        },
        'modules.lessons' => function($query) {
            $query->orderBy('position');
        },
        'modules.lessons.lecture',
        'modules.lessons.test',
        'modules.lessons.extraMaterial'
    ])
    ->where('id', $id)
    ->first();
}

    /**
     * Get courses by category.
     *
     * @param int $categoryId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByCategoryPaginated(int $categoryId, int $perPage = 15): LengthAwarePaginator
    {
        return Course::with(['category', 'level', 'instructor'])
            ->where('category_id', $categoryId)
            ->published()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get courses by level.
     *
     * @param int $levelId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByLevelPaginated(int $levelId, int $perPage = 15): LengthAwarePaginator
    {
        return Course::with(['category', 'level', 'instructor'])
            ->where('level_id', $levelId)
            ->published()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get courses by instructor.
     *
     * @param int $instructorId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByInstructorPaginated(int $instructorId, int $perPage = 15): LengthAwarePaginator
    {
        return Course::with(['category', 'level', 'instructor'])
            ->where('instructor_id', $instructorId)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create a new course.
     *
     * @param array $data
     * @return Course
     */
    public function create(array $data): Course
    {
        return Course::create($data);
    }

    /**
     * Update a course.
     *
     * @param Course $course
     * @param array $data
     * @return Course
     */
    public function update(Course $course, array $data): Course
    {
        $course->update($data);
        return $course;
    }

    /**
     * Delete a course.
     *
     * @param Course $course
     * @return bool|null
     */
    public function delete(Course $course): ?bool
    {
        return $course->delete();
    }

    /**
     * Search courses.
     *
     * @param string $query
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function search(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return Course::with(['category', 'level', 'instructor'])
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('what_you_learn', 'like', "%{$query}%");
            })
            ->published()
            ->latest()
            ->paginate($perPage);
    }
    
    /**
     * Publish a course.
     *
     * @param Course $course
     * @return Course
     */
    public function publish(Course $course): Course
    {
        $course->is_published = true;
        $course->save();
        return $course;
    }
    
    /**
     * Unpublish a course.
     *
     * @param Course $course
     * @return Course
     */
    public function unpublish(Course $course): Course
    {
        $course->is_published = false;
        $course->save();
        return $course;
    }
}