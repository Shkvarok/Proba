<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LessonService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\App;
use App\Models\Lesson;



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
    
    // LessonController.php
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
        'file' => 'nullable|file|max:10240|required_if:type,lecture,content,null',
        'content' => 'nullable|string|required_if:type,lecture,file,null',
        'duration_minutes' => 'nullable|integer|min:1',
        
        // Поля для тестів
        'source_type' => 'nullable|in:url,internal',
        'external_url' => 'nullable|url|required_if:source_type,url',
        'time_limit_minutes' => 'nullable|integer|min:1',
        'passing_score' => 'nullable|integer|min:0',
        
        // Поля для додаткових матеріалів
        'material_type' => 'nullable|in:url,video,file,text,image|required_if:type,extra_material',
        'material_file' => 'nullable|file|max:102400|required_if:material_type,file,image,video',
        'material_url' => 'nullable|url|required_if:material_type,url',
        'material_content' => 'nullable|string|required_if:material_type,text',
    ]);
    
    if ($validator->fails()) {
        return response()->json([
            'message' => 'Помилка валідації даних',
            'errors' => $validator->errors()
        ], 422);
    }
    
    try {
        // Підготовка даних для сервісу
        $data = $request->all();
        
        // Обробка файлу для лекції
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('lessons/lectures', $fileName, 'public');
            $data['file_path'] = $filePath;
            $data['file_type'] = $file->getClientMimeType();
            $data['file_name'] = $file->getClientOriginalName();
        }
        
        // Обробка файлу для додаткового матеріалу
        if ($request->hasFile('material_file')) {
            $file = $request->file('material_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('lessons/materials', $fileName, 'public');
            $data['file_path'] = $filePath;
            $data['file_type'] = $file->getClientMimeType();
            $data['file_name'] = $file->getClientOriginalName();
        }
        
        $lesson = $this->lessonService->createLesson($data);
        
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
    // LessonController.php
public function getFile($lessonId, $type)
{
    $lesson = $this->lessonService->getLessonById($lessonId);
    
    switch ($type) {
        case 'lecture':
            if ($lesson->lecture && $lesson->lecture->file_path) {
                $path = str_replace('public/', '', $lesson->lecture->file_path);
                return Storage::download('public/' . $path, $lesson->lecture->file_name);
            }
            break;
            
        case 'material':
            if ($lesson->extraMaterial && $lesson->extraMaterial->file_path) {
                $path = str_replace('public/', '', $lesson->extraMaterial->file_path);
                return Storage::download('public/' . $path, $lesson->extraMaterial->file_name);
            }
            break;
    }
    
    return response()->json([
        'message' => 'Файл не знайдено'
    ], 404);
}
   /**
 * Оновлення уроку
 *
 * @param \Illuminate\Http\Request $request
 * @param int $id
 * @return \Illuminate\Http\JsonResponse
 */
public function update(Request $request, $id)
{
    $lesson = \App\Models\Lesson::findOrFail($id);
    
    $validator = Validator::make($request->all(), [
        'title' => 'nullable|string|max:255',
        'description' => 'nullable|string',
        'position' => 'nullable|integer|min:0',
        'status' => 'nullable|in:active,disabled',
        
        // Спільні поля для лекцій
        'content' => 'nullable|string',
        'duration_minutes' => 'nullable|integer|min:1',
        'file' => 'nullable|file|max:10240', // Для завантаження файлу лекції
        
        // Поля для тестів
        'source_type' => 'nullable|in:url,internal',
        'external_url' => 'nullable|url',
        'time_limit_minutes' => 'nullable|integer|min:1',
        'passing_score' => 'nullable|integer|min:0',
        
        // Поля для додаткових матеріалів
        'material_type' => 'nullable|in:url,video,file,text,image',
        'material_content' => 'nullable|string|required_if:material_type,text', // Зв'язуємо з material_type, а не type        'material_url' => 'nullable|url',
        'material_file' => 'nullable|file|max:102400', // Для завантаження матеріалів
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
        ], 403);
    }
    
    try {
        // Підготовка даних для оновлення
        $data = $request->except(['file', 'material_file']); // Виключаємо файли з даних
        
        // Обробка файлу для лекції
        if ($request->hasFile('file') && $lesson->type === 'lecture') {
            // Видаляємо старий файл, якщо він є
            if ($lesson->lecture && $lesson->lecture->file_path) {
                \Illuminate\Support\Facades\Storage::delete($lesson->lecture->file_path);
            }
            
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('lessons/lectures', $fileName, 'public');
            
            $data['file_path'] = $filePath;
            $data['file_type'] = $file->getClientMimeType();
            $data['file_name'] = $file->getClientOriginalName();
            $data['content_type'] = 'file'; // Встановлюємо тип контенту як файл
        }
        
        // Обробка файлу для додаткового матеріалу
        if ($request->hasFile('material_file') && $lesson->type === 'extra_material') {
            // Видаляємо старий файл, якщо він є
            if ($lesson->extraMaterial && $lesson->extraMaterial->file_path) {
                \Illuminate\Support\Facades\Storage::delete($lesson->extraMaterial->file_path);
            }
            
            $file = $request->file('material_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            
            // Визначаємо шлях для зберігання залежно від типу матеріалу
            $storagePath = 'public/lessons/materials';
            if (isset($data['material_type'])) {
                switch ($data['material_type']) {
                    case 'video':
                        $storagePath = 'public/lessons/videos';
                        break;
                    case 'image':
                        $storagePath = 'public/lessons/images';
                        break;
                }
            }
            
            $filePath = $file->storeAs($storagePath, $fileName);
            
            $data['file_path'] = $filePath;
            $data['file_type'] = $file->getClientMimeType();
            $data['file_name'] = $file->getClientOriginalName();
        }
        
        // Оновлюємо урок через сервіс
        $lesson = $this->lessonService->updateLesson($id, $data);
        
        return response()->json([
            'success' => true,
            'message' => 'Урок успішно оновлено',
            'lesson' => $lesson
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
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
        'positions.*.position' => 'required|integer|min:1',
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
        $result = $this->lessonService->updateLessonPositions($request->positions);
        
        // Отримуємо module_id для першого уроку, щоб повернути всі уроки модуля
        $lessonId = $request->positions[0]['id'];
        $lesson = Lesson::findOrFail($lessonId);
        $moduleId = $lesson->module_id;
        
        // Отримуємо всі уроки модуля для відображення оновленого порядку
        $allLessons = $this->lessonService->getAllLessonsByModuleId($moduleId);
        
        $response = [
            'success' => true,
            'message' => 'Порядок уроків успішно оновлено',
            'lessons' => $allLessons
        ];
        
        // Додаємо повідомлення про коригування, якщо воно було
        if (is_array($result) && $result['adjusted'] && !empty($result['message'])) {
            $response['warning'] = $result['message'];
            if (isset($result['adjustedPositions'])) {
                $response['adjustedPositions'] = $result['adjustedPositions'];
            }
        }
        
        return response()->json($response);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Помилка при оновленні порядку уроків',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function updatePosition(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'position' => 'required|integer|min:1',
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
            'message' => 'У вас немає прав на зміну порядку цього уроку'
        ], 403);
    }
    
    try {
        // Створюємо масив з одного елемента для існуючого методу
        $positions = [
            [
                'id' => (int)$id,
                'position' => (int)$request->position
            ]
        ];
        
        // Використовуємо існуючий метод для оновлення позиції
        $result = $this->lessonService->updateLessonPositions($positions);
        
        // Отримуємо оновлений урок
        $lesson = Lesson::findOrFail($id);
        
        // Отримуємо всі уроки модуля для відображення оновленого порядку
        $allLessons = $this->lessonService->getAllLessonsByModuleId($lesson->module_id);
        
        $response = [
            'success' => true,
            'message' => 'Позиція уроку успішно оновлена',
            'lesson' => $lesson,
            'lessons' => $allLessons
        ];
        
        // Додаємо повідомлення про коригування, якщо воно було
        if (is_array($result) && $result['adjusted'] && !empty($result['message'])) {
            $response['warning'] = $result['message'];
        }
        
        return response()->json($response);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Помилка при оновленні позиції уроку',
            'error' => $e->getMessage()
        ], 500);
    }
}
}