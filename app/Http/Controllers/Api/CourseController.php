<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseRequest;
use App\Http\Resources\CourseResource;
use App\Services\CourseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    /**
     * @var CourseService
     */
    protected $courseService;

    /**
     * CourseController constructor.
     *
     * @param CourseService $courseService
     */
    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    /**
     * Display a listing of the courses.
     *
     * @param Request $request
     * @return JsonResponse|AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        try {
            \Log::info('Початок виконання методу index');
            $perPage = $request->input('per_page', 15);
            $onlyPublished = $request->boolean('published', false);
            
            \Log::info('Параметри запиту', [
                'perPage' => $perPage,
                'onlyPublished' => $onlyPublished
            ]);
            
            if ($onlyPublished) {
                $courses = $this->courseService->getPublishedCourses($perPage);
            } else {
                $courses = $this->courseService->getAllCourses($perPage);
            }
            
            \Log::info('Курси отримано успішно');
            return CourseResource::collection($courses);
        } catch (Exception $e) {
            \Log::error('Помилка в методі index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні списку курсів: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created course in storage.
     *
     * @param CourseRequest $request
     * @return JsonResponse|CourseResource
     */
    public function store(CourseRequest $request)
    {
        try {
            $course = $this->courseService->createCourse(
                $request->validated(),
                $request->hasFile('cover_image') ? $request->file('cover_image') : null
            );
            
            return (new CourseResource($course))
                ->response()
                ->setStatusCode(201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при створенні курсу: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getMyCourses()
    {
        $userId = auth()->id();
        $courses = $this->courseService->getCoursesByInstructorId($userId);
        
        return CourseResource::collection($courses);
    }
    
    /**
     * Отримання курсів, на які записаний студент
     */
    public function getEnrolledCourses()
    {
        $userId = auth()->id();
        $courses = $this->courseService->getEnrolledCoursesByUserId($userId);
        
        return CourseResource::collection($courses);
    }
    
    /**
     * Отримання прогресу по курсу
     */
    public function getCourseProgress($courseId)
    {
        $userId = auth()->id();
        $progress = $this->courseService->getCourseProgressForUser($userId, $courseId);
        
        return response()->json([
            'progress' => $progress
        ]);
    }

    /**
     * Display the specified course.
     *
     * @param int $id
     * @return JsonResponse|CourseResource
     */
    public function show(int $id)
    {
        try {
            $course = $this->courseService->getCourseById($id);
            
            // Створюємо структуру даних для відповіді
            $courseData = [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'description' => $course->description,
                'price' => $course->price,
                'is_free' => $course->is_free,
                'is_published' => $course->is_published,
                'thumbnail' => $course->thumbnail,
                'category' => $course->category ? [
                    'id' => $course->category->id,
                    'name' => $course->category->name,
                    'slug' => $course->category->slug
                ] : null,
                'level' => $course->level ? [
                    'id' => $course->level->id,
                    'name' => $course->level->name
                ] : null,
                'instructor' => $course->instructor ? [
                    'id' => $course->instructor->id,
                    'name' => $course->instructor->name
                ] : null,
                'modules' => []
            ];
            
            // Додаємо модулі з уроками
            foreach ($course->modules as $module) {
                $moduleData = [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'position' => $module->position,
                    'lessons' => []
                ];
                
                // Додаємо уроки з деталями
                foreach ($module->lessons as $lesson) {
                    $lessonData = [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'description' => $lesson->description,
                        'type' => $lesson->type,
                        'position' => $lesson->position,
                        'status' => $lesson->status
                    ];
                    
                    // Додаємо специфічні деталі уроку в залежності від типу
                    switch ($lesson->type) {
                        case 'lecture':
                            if ($lesson->lecture) {
                                $lessonData['lecture'] = [
                                    'id' => $lesson->lecture->id,
                                    'content' => $lesson->lecture->content,
                                    'duration_minutes' => $lesson->lecture->duration_minutes
                                ];
                            }
                            break;
                        
                        case 'test':
                            if ($lesson->test) {
                                $lessonData['test'] = [
                                    'id' => $lesson->test->id,
                                    'source_type' => $lesson->test->source_type,
                                    'external_url' => $lesson->test->external_url,
                                    'time_limit_minutes' => $lesson->test->time_limit_minutes,
                                    'passing_score' => $lesson->test->passing_score
                                ];
                            }
                            break;
                        
                        case 'extra_material':
                            if ($lesson->extraMaterial) {
                                $lessonData['extra_material'] = [
                                    'id' => $lesson->extraMaterial->id,
                                    'material_type' => $lesson->extraMaterial->material_type,
                                    'content' => $lesson->extraMaterial->content,
                                    'file_path' => $lesson->extraMaterial->file_path,
                                    'url' => $lesson->extraMaterial->url
                                ];
                            }
                            break;
                    }
                    
                    $moduleData['lessons'][] = $lessonData;
                }
                
                $courseData['modules'][] = $moduleData;
            }
            
            return response()->json([
                'success' => true,
                'course' => $courseData
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Курс не знайдено або виникла помилка: ' . $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }

    /**
     * Update the specified course in storage.
     *
     * @param CourseRequest $request
     * @param int $id
     * @return JsonResponse|CourseResource
     */
    public function update(CourseRequest $request, int $id)
    {
        try {
            $course = $this->courseService->getCourseById($id);
            
            $updatedCourse = $this->courseService->updateCourse(
                $course,
                $request->validated(),
                $request->hasFile('cover_image') ? $request->file('cover_image') : null
            );
            
            return new CourseResource($updatedCourse);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при оновленні курсу: ' . $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }

    /**
     * Remove the specified course from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $course = $this->courseService->getCourseById($id);
            $this->courseService->deleteCourse($course);
            
            return response()->json([
                'success' => true,
                'message' => 'Курс успішно видалено'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при видаленні курсу: ' . $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }

    /**
     * Search courses by keyword.
     *
     * @param Request $request
     * @return JsonResponse|AnonymousResourceCollection
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);
            
            $query = $request->input('query');
            $perPage = $request->input('per_page', 15);
            
            $courses = $this->courseService->searchCourses($query, $perPage);
            
            return CourseResource::collection($courses);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при пошуку курсів: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get courses by category.
     *
     * @param int $categoryId
     * @param Request $request
     * @return JsonResponse|AnonymousResourceCollection
     */
    public function getByCategory(int $categoryId, Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $courses = $this->courseService->getCoursesByCategory($categoryId, $perPage);
            
            return CourseResource::collection($courses);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні курсів за категорією: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get courses by level.
     *
     * @param int $levelId
     * @param Request $request
     * @return JsonResponse|AnonymousResourceCollection
     */
    public function getByLevel(int $levelId, Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $courses = $this->courseService->getCoursesByLevel($levelId, $perPage);
            
            return CourseResource::collection($courses);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні курсів за рівнем складності: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get courses by instructor.
     *
     * @param int $instructorId
     * @param Request $request
     * @return JsonResponse|AnonymousResourceCollection
     */
    public function getByInstructor(int $instructorId, Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $courses = $this->courseService->getCoursesByInstructor($instructorId, $perPage);
            
            return CourseResource::collection($courses);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні курсів за інструктором: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish a course.
     *
     * @param int $id
     * @return JsonResponse|CourseResource
     */
    public function publish(int $id)
    {
        try {
            $course = $this->courseService->getCourseById($id);
            $publishedCourse = $this->courseService->publishCourse($course);
            
            return new CourseResource($publishedCourse);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при публікації курсу: ' . $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }

    /**
     * Unpublish a course.
     *
     * @param int $id
     * @return JsonResponse|CourseResource
     */
    public function unpublish(int $id)
    {
        try {
            $course = $this->courseService->getCourseById($id);
            $unpublishedCourse = $this->courseService->unpublishCourse($course);
            
            return new CourseResource($unpublishedCourse);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при знятті курсу з публікації: ' . $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }
}