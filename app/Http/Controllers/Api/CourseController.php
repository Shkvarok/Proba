<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseRequest;
use App\Http\Resources\CourseResource;
use App\Services\CourseService;
use App\Services\FileUploadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    protected CourseService $courseService;
    protected FileUploadService $fileUploadService;

    public function __construct(CourseService $courseService, FileUploadService $fileUploadService)
    {
        $this->courseService = $courseService;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Display a listing of the courses.
     */
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        try {
            Log::info('Початок виконання методу index', [
                'user_id' => auth()->id(),
                'params' => $request->all()
            ]);
            
            $perPage = $request->input('per_page', 15);
            $onlyPublished = $request->boolean('published', false);
            
            if ($onlyPublished) {
                $courses = $this->courseService->getPublishedCourses($perPage);
            } else {
                $courses = $this->courseService->getAllCourses($perPage);
            }
            
            Log::info('Курси отримано успішно', ['count' => $courses->count()]);
            return CourseResource::collection($courses);
            
        } catch (Exception $e) {
            Log::error('Помилка в методі index', [
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
     */
    public function store(CourseRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            Log::info('=== ДІАГНОСТИКА ЗАВАНТАЖЕННЯ КУРСУ ===');
            Log::info('Request data:', $request->all());
            Log::info('Files in request:', $request->allFiles());
            
            $courseData = $request->getCourseData();
            Log::info('Course data from request:', $courseData);
            
            // Обробка завантаження обкладинки
            if ($request->hasFile('cover_image')) {
                $coverImage = $request->file('cover_image');
                
                // Перевірка на валідність файлу
                if (!$coverImage->isValid()) {
                    Log::error('File is not valid:', [
                        'error' => $coverImage->getError(),
                        'error_message' => $coverImage->getErrorMessage()
                    ]);
                    throw new Exception('Завантажений файл пошкоджений: ' . $coverImage->getErrorMessage());
                }
                
                Log::info('Processing file upload...', [
                    'original_name' => $coverImage->getClientOriginalName(),
                    'mime_type' => $coverImage->getMimeType(),
                    'size' => $coverImage->getSize()
                ]);
                
                // Завантаження і збереження зображення
                $imagePath = $this->fileUploadService->uploadCourseImage(
                    $coverImage, 
                    'course-covers'
                );
                
                Log::info('Image uploaded successfully:', [
                    'path' => $imagePath,
                    'exists_in_storage' => Storage::disk('public')->exists($imagePath),
                    'file_size' => Storage::disk('public')->exists($imagePath) ? Storage::disk('public')->size($imagePath) : 'N/A'
                ]);
                
                // Важливо: зберігаємо відносний шлях до файлу
                $courseData['cover_image'] = $imagePath;
            }
            
            Log::info('Final course data before creation:', $courseData);
            
            // Створюємо курс
            $course = $this->courseService->createCourse($courseData);
            
            // Перевіряємо, чи збереглося зображення
            if ($course->cover_image) {
                Log::info('Course image saved in database:', [
                    'course_id' => $course->id,
                    'cover_image' => $course->cover_image,
                    'exists_in_storage' => Storage::disk('public')->exists($course->cover_image)
                ]);
            } else {
                Log::warning('Course image was not saved in database', [
                    'course_id' => $course->id,
                    'course_data' => $courseData
                ]);
            }
            
            DB::commit();
            
            return (new CourseResource($course))
                ->response()
                ->setStatusCode(201);
                
        } catch (Exception $e) {
            DB::rollBack();
            
            // Видалення завантаженого файлу у разі помилки
            if (isset($imagePath) && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            
            Log::error('Помилка при створенні курсу', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при створенні курсу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified course.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $course = $this->courseService->getCourseById($id);
            
            return response()->json([
                'success' => true,
                'course' => $this->formatCourseData($course)
            ]);
            
        } catch (Exception $e) {
            Log::error('Помилка при отриманні курсу', [
                'course_id' => $id,
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Курс не знайдено або виникла помилка: ' . $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }

    /**
     * Update the specified course in storage.
     */
    public function update(CourseRequest $request, int $id): JsonResponse|CourseResource
    {
        DB::beginTransaction();
        
        try {
            $course = $this->courseService->getCourseById($id);
            $courseData = $request->getCourseData();
            $oldImagePath = $course->thumbnail;
            
            // Обробка нового зображення обкладинки
            if ($request->hasFile('cover_image')) {
                $coverImage = $request->file('cover_image');
                
                if (!$coverImage->isValid()) {
                    throw new Exception('Завантажений файл пошкоджений');
                }
                
                // Завантаження нового зображення
                $imagePath = $this->fileUploadService->uploadCourseImage(
                    $coverImage, 
                    'course-covers'
                );
                
                $courseData['thumbnail'] = $imagePath;
                
                Log::info('Нове зображення обкладинки завантажено', [
                    'course_id' => $id,
                    'new_path' => $imagePath,
                    'old_path' => $oldImagePath
                ]);
            }
            
            $updatedCourse = $this->courseService->updateCourse($course, $courseData);
            
            // Видалення старого зображення після успішного оновлення
            if (isset($imagePath) && $oldImagePath && Storage::exists($oldImagePath)) {
                Storage::delete($oldImagePath);
                Log::info('Старе зображення видалено', ['path' => $oldImagePath]);
            }
            
            DB::commit();
            
            Log::info('Курс успішно оновлено', [
                'course_id' => $id,
                'title' => $updatedCourse->title
            ]);
            
            return new CourseResource($updatedCourse);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            // Видалення нового файлу у разі помилки
            if (isset($imagePath) && Storage::exists($imagePath)) {
                Storage::delete($imagePath);
            }
            
            Log::error('Помилка при оновленні курсу', [
                'course_id' => $id,
                'message' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при оновленні курсу: ' . $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }

    /**
     * Remove the specified course from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $course = $this->courseService->getCourseById($id);
            $imagePath = $course->thumbnail;
            
            $this->courseService->deleteCourse($course);
            
            // Видалення зображення обкладинки
            if ($imagePath && Storage::exists($imagePath)) {
                Storage::delete($imagePath);
                Log::info('Зображення курсу видалено', ['path' => $imagePath]);
            }
            
            DB::commit();
            
            Log::info('Курс успішно видалено', [
                'course_id' => $id,
                'title' => $course->title
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Курс успішно видалено'
            ]);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Помилка при видаленні курсу', [
                'course_id' => $id,
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при видаленні курсу: ' . $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }

    /**
     * Get courses for the authenticated instructor.
     */
    public function getMyCourses(): AnonymousResourceCollection
    {
        $userId = auth()->id();
        $courses = $this->courseService->getCoursesByInstructorId($userId);
        
        return CourseResource::collection($courses);
    }
    
    /**
     * Get enrolled courses for the authenticated student.
     */
    public function getEnrolledCourses(): AnonymousResourceCollection
    {
        $userId = auth()->id();
        $courses = $this->courseService->getEnrolledCoursesByUserId($userId);
        
        return CourseResource::collection($courses);
    }
    
    /**
     * Get course progress for the authenticated user.
     */
    public function getCourseProgress(int $courseId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $progress = $this->courseService->getCourseProgressForUser($userId, $courseId);
            
            return response()->json([
                'success' => true,
                'progress' => $progress
            ]);
            
        } catch (Exception $e) {
            Log::error('Помилка при отриманні прогресу курсу', [
                'course_id' => $courseId,
                'user_id' => auth()->id(),
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні прогресу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search courses by keyword.
     */
    public function search(Request $request): JsonResponse|AnonymousResourceCollection
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2|max:100',
                'per_page' => 'nullable|integer|min:1|max:100',
                'category_id' => 'nullable|integer|exists:categories,id',
                'level_id' => 'nullable|integer|exists:levels,id',
                'price_min' => 'nullable|numeric|min:0',
                'price_max' => 'nullable|numeric|min:0',
                'is_free' => 'nullable|boolean',
                'instructor_id' => 'nullable|integer|exists:users,id',
                'language' => 'nullable|string|max:50',
                'difficulty_level' => 'nullable|in:beginner,intermediate,advanced',
                'sort_by' => 'nullable|in:created_at,title,price,updated_at',
                'sort_direction' => 'nullable|in:asc,desc'
            ]);
            
            $query = $request->input('query');
            $perPage = $request->input('per_page', 15);
            $filters = $request->only([
                'category_id', 'level_id', 'price_min', 'price_max', 'is_free',
                'instructor_id', 'language', 'difficulty_level', 'sort_by', 'sort_direction'
            ]);
            
            // Використовуємо метод з фільтрами, якщо є фільтри, інакше простий пошук
            if (!empty(array_filter($filters))) {
                $courses = $this->courseService->searchCoursesWithFilters($query, $perPage, $filters);
            } else {
                $courses = $this->courseService->searchCourses($query, $perPage);
            }
            
            return CourseResource::collection($courses);
            
        } catch (Exception $e) {
            Log::error('Помилка при пошуку курсів', [
                'query' => $request->input('query'),
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при пошуку курсів: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get courses by category.
     */
    public function getByCategory(int $categoryId, Request $request): JsonResponse|AnonymousResourceCollection
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
     */
    public function getByLevel(int $levelId, Request $request): JsonResponse|AnonymousResourceCollection
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
     */
    public function getByInstructor(int $instructorId, Request $request): JsonResponse|AnonymousResourceCollection
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
     * Get popular courses
     */
    public function getPopular(Request $request): AnonymousResourceCollection
    {
        try {
            $limit = $request->input('limit', 10);
            $limit = min($limit, 50); // Максимум 50 курсів
            
            $courses = $this->courseService->getPopularCourses($limit);
            
            return CourseResource::collection($courses);
            
        } catch (Exception $e) {
            Log::error('Помилка при отриманні популярних курсів', [
                'message' => $e->getMessage()
            ]);
            
            return CourseResource::collection(collect([]));
        }
    }

    /**
     * Get featured courses
     */
    public function getFeatured(Request $request): AnonymousResourceCollection
    {
        try {
            $limit = $request->input('limit', 6);
            $limit = min($limit, 20); // Максимум 20 курсів
            
            $courses = $this->courseService->getFeaturedCourses($limit);
            
            return CourseResource::collection($courses);
            
        } catch (Exception $e) {
            Log::error('Помилка при отриманні рекомендованих курсів', [
                'message' => $e->getMessage()
            ]);
            
            return CourseResource::collection(collect([]));
        }
    }

    /**
     * Get recommended courses for authenticated user
     */
    public function getRecommended(Request $request): AnonymousResourceCollection
    {
        try {
            $userId = auth()->id();
            $limit = $request->input('limit', 5);
            $limit = min($limit, 20);
            
            $courses = $this->courseService->getRecommendedCourses($userId, $limit);
            
            return CourseResource::collection($courses);
            
        } catch (Exception $e) {
            Log::error('Помилка при отриманні рекомендованих курсів', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage()
            ]);
            
            return CourseResource::collection(collect([]));
        }
    }

    /**
     * Get courses statistics (admin only)
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->courseService->getCoursesStatistics();
            
            return response()->json([
                'success' => true,
                'statistics' => $statistics
            ]);
            
        } catch (Exception $e) {
            Log::error('Помилка при отриманні статистики курсів', [
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні статистики: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk operations on courses (admin/teacher only)
     */
    public function bulkAction(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'action' => 'required|in:publish,unpublish,delete,change_category',
                'course_ids' => 'required|array|min:1',
                'course_ids.*' => 'integer|exists:courses,id',
                'category_id' => 'required_if:action,change_category|integer|exists:categories,id'
            ]);

            $action = $request->input('action');
            $courseIds = $request->input('course_ids');
            $categoryId = $request->input('category_id');
            
            $user = auth()->user();
            $successCount = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($courseIds as $courseId) {
                try {
                    $course = $this->courseService->getCourseById($courseId);
                    
                    // Перевірка прав доступу
                    if ($course->instructor_id !== $user->id && !$user->isAdmin()) {
                        $errors[] = "Немає прав для курсу ID: {$courseId}";
                        continue;
                    }

                    switch ($action) {
                        case 'publish':
                            $this->courseService->publishCourse($course);
                            break;
                        case 'unpublish':
                            $this->courseService->unpublishCourse($course);
                            break;
                        case 'delete':
                            $this->courseService->deleteCourse($course);
                            break;
                        case 'change_category':
                            $this->courseService->updateCourse($course, ['category_id' => $categoryId]);
                            break;
                    }

                    $successCount++;

                } catch (Exception $e) {
                    $errors[] = "Помилка для курсу ID {$courseId}: " . $e->getMessage();
                }
            }

            DB::commit();

            Log::info('Масова операція з курсами виконана', [
                'action' => $action,
                'success_count' => $successCount,
                'errors_count' => count($errors),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "Операція виконана. Успішно оброблено: {$successCount} курсів.",
                'success_count' => $successCount,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Помилка при масовій операції з курсами', [
                'message' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при виконанні масової операції: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish a course.
     */
    public function publish(int $id): JsonResponse|CourseResource
    {
        try {
            $course = $this->courseService->getCourseById($id);
            
            // Перевірка права на публікацію
            if ($course->instructor_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для публікації цього курсу'
                ], 403);
            }
            
            $publishedCourse = $this->courseService->publishCourse($course);
            
            Log::info('Курс опубліковано', [
                'course_id' => $id,
                'published_by' => auth()->id()
            ]);
            
            return new CourseResource($publishedCourse);
            
        } catch (Exception $e) {
            Log::error('Помилка при публікації курсу', [
                'course_id' => $id,
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при публікації курсу: ' . $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }

    /**
     * Unpublish a course.
     */
    public function unpublish(int $id): JsonResponse|CourseResource
    {
        try {
            $course = $this->courseService->getCourseById($id);
            
            // Перевірка права на зняття з публікації
            if ($course->instructor_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для зняття з публікації цього курсу'
                ], 403);
            }
            
            $unpublishedCourse = $this->courseService->unpublishCourse($course);
            
            Log::info('Курс знято з публікації', [
                'course_id' => $id,
                'unpublished_by' => auth()->id()
            ]);
            
            return new CourseResource($unpublishedCourse);
            
        } catch (Exception $e) {
            Log::error('Помилка при знятті курсу з публікації', [
                'course_id' => $id,
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при знятті курсу з публікації: ' . $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }

    /**
     * Format course data for response.
     */
     private function formatCourseData($course): array
    {
        $courseData = [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'price' => $course->price,
            'discount_price' => $course->discount_price,
            'discount_expires_at' => $course->discount_expires_at,
            'is_published' => $course->is_published,
            'cover_image' => $course->cover_image ? Storage::disk('public')->url($course->cover_image) : null, // ← Змінено
            'promo_video_url' => $course->promo_video_url,
            'requirements' => $course->requirements,
            'what_you_learn' => $course->what_you_learn,
            'language' => $course->language,
            'meta_title' => $course->meta_title,
            'meta_description' => $course->meta_description,
            'created_at' => $course->created_at,
            'updated_at' => $course->updated_at,
            'category' => $course->category ? [
                'id' => $course->category->id,
                'name' => $course->category->name,
                'slug' => $course->category->slug ?? null
            ] : null,
            'level' => $course->level ? [
                'id' => $course->level->id,
                'name' => $course->level->name
            ] : null,
            'instructor' => $course->instructor ? [
                'id' => $course->instructor->id,
                'name' => $course->instructor->name,
                'email' => $course->instructor->email
            ] : null,
            'modules' => []
        ];
        
        // Додаємо модулі з уроками (якщо завантажені)
        if ($course->relationLoaded('modules')) {
            foreach ($course->modules as $module) {
                $moduleData = [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'position' => $module->position,
                    'lessons' => []
                ];
                
                if ($module->relationLoaded('lessons')) {
                    foreach ($module->lessons as $lesson) {
                        $lessonData = [
                            'id' => $lesson->id,
                            'title' => $lesson->title,
                            'description' => $lesson->description,
                            'type' => $lesson->type,
                            'position' => $lesson->position,
                            'status' => $lesson->status
                        ];
                        
                        // Додаємо специфічні деталі уроку
                        $this->addLessonTypeData($lessonData, $lesson);
                        
                        $moduleData['lessons'][] = $lessonData;
                    }
                }
                
                $courseData['modules'][] = $moduleData;
            }
        }
        
        return $courseData;
    }

    /**
     * Add lesson type specific data.
     */
    private function addLessonTypeData(array &$lessonData, $lesson): void
    {
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
                        'file_path' => $lesson->extraMaterial->file_path ? 
                            Storage::url($lesson->extraMaterial->file_path) : null,
                        'url' => $lesson->extraMaterial->url
                    ];
                }
                break;
        }
    }
}