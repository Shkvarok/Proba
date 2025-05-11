<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CourseService;
use App\Services\LessonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Module;
use Illuminate\Support\Facades\App;

class LessonController extends Controller
{
    protected $lessonService;
    
    public function __construct(LessonService $lessonService)
    {
        $this->lessonService = $lessonService;
    }
    
    public function index(Request $request, $moduleId = null)
{
    if (!$moduleId) {
        $moduleId = $request->input('module_id');
        
        if (!$moduleId) {
            return response()->json([
                'message' => 'Необхідно вказати ID модуля'
            ], 400);
        }
    }
    
    $lessons = $this->lessonService->getAllLessonsByModuleId($moduleId);
    
    return response()->json([
        'lessons' => $lessons
    ]);
}
    public function show($id)
    {
        try {
            $lesson = $this->lessonService->getLessonById($id);
            
            return response()->json([
                'lesson' => $lesson
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Урок не знайдено'
            ], 404);
        }
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:lecture,test,extra_material',
            'position' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,disabled',
            
            // Поля для лекцій
            'content' => 'nullable|string|required_if:type,lecture',
            'duration_minutes' => 'nullable|integer|min:1',
            
            // Поля для тестів
            'source_type' => 'nullable|in:url,internal',
            'external_url' => 'nullable|url|required_if:source_type,url',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'passing_score' => 'nullable|integer|min:0',
            
            // Поля для додаткових матеріалів
            'material_type' => 'nullable|in:url,video,file,text|required_if:type,extra_material',
            'file_path' => 'nullable|string',
            'url' => 'nullable|url|required_if:material_type,url,video',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
      $moduleId = $request->input('module_id');
        $module = Module::with('course')->findOrFail($moduleId);
        $course = $module->course;
        
        // Отримуємо CourseService через сервіс-контейнер
        $courseService = App::make(CourseService::class);
        
        // Використовуємо CourseService для перевірки доступу
        if (!$courseService->canUserManageCourse(auth()->id(), $course->id)) {
            return response()->json([
                'message' => 'У вас немає прав на створення уроків для цього курсу'
            ], 403);
        }
        
        try {
            $lesson = $this->lessonService->createLesson($request->all());
            
            return response()->json([
                'message' => 'Урок успішно створено',
                'lesson' => $lesson
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Помилка при створенні уроку',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request, $id)
    {
        $lesson = \App\Models\Lesson::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'position' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,disabled',
            
            // Поля залежно від типу уроку
            'content' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
            'source_type' => 'nullable|in:url,internal',
            'external_url' => 'nullable|url',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'passing_score' => 'nullable|integer|min:0',
            'material_type' => 'nullable|in:url,video,file,text',
            'file_path' => 'nullable|string',
            'url' => 'nullable|url',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Перевіряємо права доступу
        if (!$this->lessonService->canUserManageLesson(auth()->id(), $id)) {
            return response()->json([
                'message' => 'У вас немає прав на редагування цього уроку'
           // Продовження LessonController - метод update(...)
            ], 403);
        }
        
        try {
            $lesson = $this->lessonService->updateLesson($id, $request->all());
            
            return response()->json([
                'message' => 'Урок успішно оновлено',
                'lesson' => $lesson
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Помилка при оновленні уроку',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function destroy($id)
    {
        // Перевіряємо права доступу
        if (!$this->lessonService->canUserManageLesson(auth()->id(), $id)) {
            return response()->json([
                'message' => 'У вас немає прав на видалення цього уроку'
            ], 403);
        }
        
        try {
            $this->lessonService->deleteLesson($id);
            
            return response()->json([
                'message' => 'Урок успішно видалено'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Помилка при видаленні уроку',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function updatePositions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'positions' => 'required|array',
            'positions.*.id' => 'required|exists:lessons,id',
            'positions.*.position' => 'required|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Перевіряємо права доступу для кожного уроку
        foreach ($request->positions as $position) {
            if (!$this->lessonService->canUserManageLesson(auth()->id(), $position['id'])) {
                return response()->json([
                    'message' => 'У вас немає прав на зміну порядку деяких уроків'
                ], 403);
            }
        }
        
        try {
            $this->lessonService->updateLessonPositions($request->positions);
            
            return response()->json([
                'message' => 'Порядок уроків успішно оновлено'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Помилка при оновленні порядку уроків',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}