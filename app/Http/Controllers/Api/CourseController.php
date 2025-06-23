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
use Illuminate\Support\Str;

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
     * Display a listing of the courses (без модулів і уроків).
     */
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        try {
            Log::info('Початок виконання методу index (повна версія)', [
                'user_id' => auth()->id(),
                'params' => $request->all()
            ]);
            
            $perPage = min($request->input('per_page', 15), 100); // Максимум 100
            $onlyPublished = $request->boolean('published', false);
            
            if ($onlyPublished) {
                $courses = $this->courseService->getPublishedCourses($perPage);
            } else {
                $courses = $this->courseService->getAllCourses($perPage);
            }

            // Завантажуємо всі відносини включно з модулями і уроками для повної сумісності
            $courses->load([
                'category', 
                'instructor', 
                'level',
                'modules' => function($query) {
                    $query->orderBy('position');
                },
                'modules.lessons' => function($query) {
                    $query->orderBy('position');
                }
            ]);
            
            Log::info('Курси отримано успішно (повна версія)', ['count' => $courses->count()]);
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
     * Display a listing of the courses (тільки базова інформація, без модулів і уроків).
     */
    public function indexOnly(Request $request): JsonResponse|AnonymousResourceCollection
    {
        try {
            Log::info('Початок виконання методу indexOnly', [
                'user_id' => auth()->id(),
                'params' => $request->all()
            ]);
            
            $perPage = min($request->input('per_page', 15), 100); // Максимум 100
            $onlyPublished = $request->boolean('published', false);
            
            if ($onlyPublished) {
                $courses = $this->courseService->getPublishedCourses($perPage);
            } else {
                $courses = $this->courseService->getAllCourses($perPage);
            }

            // Завантажуємо тільки базові відносини
            $courses->load(['category', 'instructor', 'level']);
            
            Log::info('Курси отримано успішно (тільки базова інформація)', ['count' => $courses->count()]);
            return CourseResource::collection($courses);
            
        } catch (Exception $e) {
            Log::error('Помилка в методі indexOnly', [
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
     * Зберегти курс
     */
    public function store(CourseRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            Log::info('=== СТВОРЕННЯ КУРСУ ===', [
                'user_id' => auth()->id(),
                'request_data' => $request->except(['cover_image']),
                'has_cover_image' => $request->hasFile('cover_image'),
                'content_type' => $request->header('Content-Type')
            ]);
            
            // Отримуємо дані курсу
            $courseData = $request->getCourseData();
            
            // Якщо instructor_id не вказано, використовуємо поточного користувача
            if (!isset($courseData['instructor_id'])) {
                $courseData['instructor_id'] = auth()->id();
            }
            
            $coverImage = $request->hasFile('cover_image') ? $request->file('cover_image') : null;
            
            Log::info('Дані для створення курсу:', [
                'course_data' => $courseData,
                'has_image' => $coverImage !== null
            ]);
            
            // Створюємо курс через сервіс
            $course = $this->courseService->createCourse($courseData, $coverImage);
            
            $course->load(['category', 'instructor', 'level']);
            
            DB::commit();
            
            Log::info('Курс успішно створено', [
                'course_id' => $course->id,
                'title' => $course->title,
                'cover_image' => $course->cover_image
            ]);
            
            return (new CourseResource($course))
                ->response()
                ->setStatusCode(201);
                
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Помилка при створенні курсу', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'request_data' => $request->except(['cover_image'])
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при створенні курсу: ' . $e->getMessage(),
                'errors' => []
            ], 500);
        }
    }

    /**
     * Показати обраний курс(з базовою інформацією).
     */
    public function show(int $id): JsonResponse
    {
        try {
            $course = $this->courseService->getCourseById($id);
            
            // Завантажуємо базові відносини
            $course->load(['category', 'instructor', 'level']);
            
            return response()->json([
                'success' => true,
                'course' => new CourseResource($course)
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
     * Display the specified course з модулями і уроками.
     */
    public function showWithContent(int $id): JsonResponse
    {
        try {
            $course = $this->courseService->getCourseById($id);
            
            // Завантажуємо всі відносини включно з модулями і уроками
            $course->load([
                'category', 
                'instructor', 
                'level',
                'modules' => function($query) {
                    $query->orderBy('position');
                },
                'modules.lessons' => function($query) {
                    $query->orderBy('position');
                }
            ]);
            
            return response()->json([
                'success' => true,
                'course' => new CourseResource($course)
            ]);
            
        } catch (Exception $e) {
            Log::error('Помилка при отриманні курсу з контентом', [
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
    public function update(CourseRequest $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            Log::info('=== ОНОВЛЕННЯ КУРСУ ===', [
                'course_id' => $id,
                'user_id' => auth()->id(),
                'request_data' => $request->except(['cover_image']),
                'has_cover_image' => $request->hasFile('cover_image'),
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type')
            ]);
            
            $course = $this->courseService->getCourseById($id);
            
            // Перевірка прав доступу
            if (!$this->courseService->canUserManageCourse(auth()->id(), $id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для редагування цього курсу'
                ], 403);
            }
            
            $courseData = $request->getCourseData();
            $coverImage = $request->hasFile('cover_image') ? $request->file('cover_image') : null;
            
            Log::info('Дані для оновлення курсу:', [
                'course_data' => $courseData,
                'has_image' => $coverImage !== null
            ]);
            
            // Оновлюємо курс через сервіс
            $updatedCourse = $this->courseService->updateCourse($course, $courseData, $coverImage);
            
            // Завантажуємо відносини для відповіді
            $updatedCourse->load(['category', 'instructor', 'level']);
            
            DB::commit();
            
            Log::info('Курс успішно оновлено', [
                'course_id' => $id,
                'title' => $updatedCourse->title,
                'cover_image' => $updatedCourse->cover_image
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Курс успішно оновлено',
                'course' => new CourseResource($updatedCourse)
            ]);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Помилка при оновленні курсу', [
                'course_id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['cover_image'])
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при оновленні курсу: ' . $e->getMessage(),
                'errors' => []
            ], 500);
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
            
            // Перевірка прав доступу
            if (!$this->courseService->canUserManageCourse(auth()->id(), $id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для видалення цього курсу'
                ], 403);
            }
            
            $this->courseService->deleteCourse($course);
            
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
    public function getMyCourses(Request $request): AnonymousResourceCollection
    {
        $userId = auth()->id();
        $perPage = min($request->input('per_page', 15), 100);
        
        $courses = $this->courseService->getCoursesByInstructorId($userId);
        $courses->load(['category', 'instructor', 'level']);
        
        return CourseResource::collection($courses);
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
                'sort_by' => 'nullable|in:created_at,title,price,updated_at',
                'sort_direction' => 'nullable|in:asc,desc'
            ]);
            
            $query = $request->input('query');
            $perPage = $request->input('per_page', 15);
            $filters = $request->only([
                'category_id', 'level_id', 'price_min', 'price_max', 'is_free',
                'instructor_id', 'language', 'sort_by', 'sort_direction'
            ]);
            
            // Використовуємо метод з фільтрами, якщо є фільтри, інакше простий пошук
            if (!empty(array_filter($filters))) {
                $courses = $this->courseService->searchCoursesWithFilters($query, $perPage, $filters);
            } else {
                $courses = $this->courseService->searchCourses($query, $perPage);
            }

            $courses->load(['category', 'instructor', 'level']);
            
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
            $perPage = min($request->input('per_page', 15), 100);
            $courses = $this->courseService->getCoursesByCategory($categoryId, $perPage);
            $courses->load(['category', 'instructor', 'level']);
            
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
            $perPage = min($request->input('per_page', 15), 100);
            $courses = $this->courseService->getCoursesByLevel($levelId, $perPage);
            $courses->load(['category', 'instructor', 'level']);
            
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
            $perPage = min($request->input('per_page', 15), 100);
            $courses = $this->courseService->getCoursesByInstructor($instructorId, $perPage);
            $courses->load(['category', 'instructor', 'level']);
            
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
            $limit = min($request->input('limit', 10), 50);
            
            $courses = $this->courseService->getPopularCourses($limit);
            $courses->load(['category', 'instructor', 'level']);
            
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
            $limit = min($request->input('limit', 6), 20);
            
            $courses = $this->courseService->getFeaturedCourses($limit);
            $courses->load(['category', 'instructor', 'level']);
            
            return CourseResource::collection($courses);
            
        } catch (Exception $e) {
            Log::error('Помилка при отриманні рекомендованих курсів', [
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
    public function publish(int $id): JsonResponse
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
            $publishedCourse->load(['category', 'instructor', 'level']);
            
            Log::info('Курс опубліковано', [
                'course_id' => $id,
                'published_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Курс успішно опубліковано',
                'course' => new CourseResource($publishedCourse)
            ]);
            
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
    public function unpublish(int $id): JsonResponse
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
            $unpublishedCourse->load(['category', 'instructor', 'level']);
            
            Log::info('Курс знято з публікації', [
                'course_id' => $id,
                'unpublished_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Курс успішно знято з публікації',
                'course' => new CourseResource($unpublishedCourse)
            ]);
            
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
 * Перевірити готовність курсу до публікації
 */
public function checkPublicationReadiness(int $id): JsonResponse
{
    try {
        $course = $this->courseService->getCourseById($id);
        
        // Перевірка прав доступу
        if (!$this->courseService->canUserManageCourse(auth()->id(), $id)) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає прав для перевірки цього курсу'
            ], 403);
        }
        
        $isReady = $course->isReadyForPublication();
        $requirements = $course->getPublicationRequirements();
        
        return response()->json([
            'success' => true,
            'is_ready' => $isReady,
            'requirements' => $requirements,
            'current_status' => $course->is_published ? 'published' : 'draft'
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Помилка при перевірці готовності курсу: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Отримати список курсів з мінімальною інформацією (для селектів)
 */
public function getCoursesSimple(Request $request): JsonResponse
{
    try {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'published_only' => 'nullable|boolean',
            'instructor_id' => 'nullable|integer|exists:users,id',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);
        
        $query = \App\Models\Course::select(['id', 'title', 'is_published', 'instructor_id']);
        
        if ($request->filled('search')) {
            $query->where('title', 'LIKE', '%' . $request->search . '%');
        }
        
        if ($request->boolean('published_only')) {
            $query->published();
        }
        
        if ($request->filled('instructor_id')) {
            $query->where('instructor_id', $request->instructor_id);
        }
        
        $limit = min($request->input('limit', 50), 100);
        $courses = $query->limit($limit)->get();
        
        return response()->json([
            'success' => true,
            'courses' => $courses->map(function($course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'is_published' => $course->is_published,
                    'instructor_id' => $course->instructor_id
                ];
            })
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Помилка при отриманні списку курсів: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Клонувати курс
 */
public function cloneCourse(int $id): JsonResponse
{
    DB::beginTransaction();
    
    try {
        $originalCourse = $this->courseService->getCourseById($id);
        
        // Перевірка прав доступу
        if (!$this->courseService->canUserManageCourse(auth()->id(), $id)) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає прав для клонування цього курсу'
            ], 403);
        }
        
        // Створюємо копію курсу
        $clonedCourse = $originalCourse->replicate();
        $clonedCourse->title = $originalCourse->title . ' (Копія)';
        $clonedCourse->is_published = false;
        $clonedCourse->instructor_id = auth()->id();
        $clonedCourse->cover_image = null; // Обкладинку не копіюємо
        $clonedCourse->save();
        
        // Копіюємо модулі
        $originalCourse->load('modules.lessons');
        foreach ($originalCourse->modules as $module) {
            $clonedModule = $module->replicate();
            $clonedModule->course_id = $clonedCourse->id;
            $clonedModule->save();
            
            // Копіюємо уроки
            foreach ($module->lessons as $lesson) {
                $clonedLesson = $lesson->replicate();
                $clonedLesson->module_id = $clonedModule->id;
                $clonedLesson->save();
                
                // Копіюємо специфічний контент уроків
                if ($lesson->type === 'lecture' && $lesson->lecture) {
                    $clonedLecture = $lesson->lecture->replicate();
                    $clonedLecture->lesson_id = $clonedLesson->id;
                    $clonedLecture->save();
                }
                
                if ($lesson->type === 'test' && $lesson->test) {
                    $clonedTest = $lesson->test->replicate();
                    $clonedTest->lesson_id = $clonedLesson->id;
                    $clonedTest->save();
                }
                
                if ($lesson->type === 'extra_material' && $lesson->extraMaterial) {
                    $clonedMaterial = $lesson->extraMaterial->replicate();
                    $clonedMaterial->lesson_id = $clonedLesson->id;
                    $clonedMaterial->file_path = null; // Файли не копіюємо
                    $clonedMaterial->save();
                }
            }
        }
        
        $clonedCourse->load(['category', 'instructor', 'level']);
        
        DB::commit();
        
        Log::info('Курс успішно клоновано', [
            'original_course_id' => $id,
            'cloned_course_id' => $clonedCourse->id,
            'cloned_by' => auth()->id()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Курс успішно клоновано',
            'course' => new CourseResource($clonedCourse)
        ], 201);
        
    } catch (Exception $e) {
        DB::rollBack();
        
        Log::error('Помилка при клонуванні курсу', [
            'course_id' => $id,
            'message' => $e->getMessage(),
            'user_id' => auth()->id()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Помилка при клонуванні курсу: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Отримати курси за кількома категоріями
 */
public function getByCategories(Request $request): JsonResponse|AnonymousResourceCollection
{
    try {
        $request->validate([
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:categories,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'published_only' => 'nullable|boolean'
        ]);
        
        $categoryIds = $request->input('category_ids');
        $perPage = min($request->input('per_page', 15), 100);
        $publishedOnly = $request->boolean('published_only', true);
        
        $query = \App\Models\Course::whereIn('category_id', $categoryIds);
        
        if ($publishedOnly) {
            $query->published();
        }
        
        $courses = $query->with(['category', 'instructor', 'level'])
                        ->paginate($perPage);
        
        return CourseResource::collection($courses);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Помилка при отриманні курсів за категоріями: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Отримати курси в межах цінового діапазону
 */
public function getByPriceRange(Request $request): JsonResponse|AnonymousResourceCollection
{
    try {
        $request->validate([
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'include_free' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);
        
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $includeFree = $request->boolean('include_free', true);
        $perPage = min($request->input('per_page', 15), 100);
        
        $query = \App\Models\Course::published();
        
        if ($minPrice !== null || $maxPrice !== null) {
            $query->where(function($q) use ($minPrice, $maxPrice, $includeFree) {
                if ($includeFree) {
                    $q->where('price', '=', 0);
                }
                
                if ($minPrice !== null && $maxPrice !== null) {
                    $q->orWhereBetween('price', [$minPrice, $maxPrice]);
                } elseif ($minPrice !== null) {
                    $q->orWhere('price', '>=', $minPrice);
                } elseif ($maxPrice !== null) {
                    $q->orWhere('price', '<=', $maxPrice);
                }
            });
        }
        
        $courses = $query->with(['category', 'instructor', 'level'])
                        ->paginate($perPage);
        
        return CourseResource::collection($courses);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Помилка при отриманні курсів за ціновим діапазоном: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Отримати безкоштовні курси
 */
public function getFreeCourses(Request $request): AnonymousResourceCollection
{
    try {
        $perPage = min($request->input('per_page', 15), 100);
        
        $courses = \App\Models\Course::published()
                    ->where('price', 0)
                    ->with(['category', 'instructor', 'level'])
                    ->paginate($perPage);
        
        return CourseResource::collection($courses);
        
    } catch (Exception $e) {
        Log::error('Помилка при отриманні безкоштовних курсів', [
            'message' => $e->getMessage()
        ]);
        
        return CourseResource::collection(collect([]));
    }
}

/**
 * Отримати новітні курси
 */
public function getLatestCourses(Request $request): AnonymousResourceCollection
{
    try {
        $limit = min($request->input('limit', 10), 50);
        
        $courses = \App\Models\Course::published()
                    ->orderBy('created_at', 'desc')
                    ->with(['category', 'instructor', 'level'])
                    ->limit($limit)
                    ->get();
        
        return CourseResource::collection($courses);
        
    } catch (Exception $e) {
        Log::error('Помилка при отриманні новітніх курсів', [
            'message' => $e->getMessage()
        ]);
        
        return CourseResource::collection(collect([]));
    }
}

/**
 * Перевірити доступ користувача до курсу
 */
public function checkUserAccess(int $id): JsonResponse
{
    try {
        $course = $this->courseService->getCourseById($id);
        $userId = auth()->id();
        
        $hasAccess = $course->hasUserAccess($userId);
        
        $accessDetails = [
            'has_access' => $hasAccess,
            'is_instructor' => $course->instructor_id === $userId,
            'is_enrolled' => false,
            'enrollment_expires_at' => null
        ];
        
        if ($userId) {
            $enrollment = $course->enrollments()
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->first();
                
            if ($enrollment) {
                $accessDetails['is_enrolled'] = true;
                $accessDetails['enrollment_expires_at'] = $enrollment->expires_at;
            }
        }
        
        return response()->json([
            'success' => true,
            'access' => $accessDetails
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Помилка при перевірці доступу до курсу: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Оновити обкладинку курсу
 */
public function updateCover(Request $request, int $id): JsonResponse
{
    try {
        $request->validate([
            'cover_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB
        ]);

        $course = $this->courseService->getCourseById($id);
        
        // Перевірка прав доступу
        if (!$this->courseService->canUserManageCourse(auth()->id(), $id)) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає прав для редагування цього курсу'
            ], 403);
        }

        // Видаляємо стару обкладинку, якщо вона існує
        if ($course->cover_image) {
            $this->courseService->deleteCoverImage($course->cover_image);
        }

        // Завантажуємо нову обкладинку
        $coverImage = $request->file('cover_image');
        $path = $this->courseService->uploadCoverImage($coverImage);

        // Оновлюємо курс
        $course->update(['cover_image' => $path]);

        Log::info('Обкладинку курсу оновлено', [
            'course_id' => $id,
            'new_path' => $path
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Обкладинку курсу успішно оновлено',
            'cover_image_url' => Storage::disk('public')->url($path)
        ]);

    } catch (Exception $e) {
        Log::error('Помилка при оновленні обкладинки курсу', [
            'course_id' => $id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Помилка при оновленні обкладинки курсу: ' . $e->getMessage()
        ], 500);
    }
}
}