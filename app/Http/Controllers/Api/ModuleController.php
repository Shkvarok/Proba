<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ModuleController extends Controller
{
     protected $moduleService;
    
    public function __construct(ModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }
    
    public function index(Request $request)
    {
        $courseId = $request->input('course_id');
        
        if (!$courseId) {
            return response()->json([
                'message' => 'Необхідно вказати ID курсу'
            ], 400);
        }
        
        $modules = $this->moduleService->getAllModulesByCourseId($courseId);
        
        return response()->json([
            'modules' => $modules
        ]);
    }
    
    public function show($id)
    {
        try {
            $module = $this->moduleService->getModuleById($id);
            
            return response()->json([
                'module' => $module
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Модуль не знайдено'
            ], 404);
        }
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'position' => 'nullable|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Перевіряємо права доступу
        $courseId = $request->input('course_id');
        $course = \App\Models\Course::findOrFail($courseId);
        
        // Чи може користувач управляти цим курсом
        if (!$this->moduleService->canUserManageCourse(auth()->id(), $courseId)) {
            return response()->json([
                'message' => 'У вас немає прав на створення модулів для цього курсу'
            ], 403);
        }
        
        try {
            $module = $this->moduleService->createModule($request->all());
            
            return response()->json([
                'message' => 'Модуль успішно створено',
                'module' => $module
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Помилка при створенні модуля',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'position' => 'nullable|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Перевіряємо права доступу
        if (!$this->moduleService->canUserManageModule(auth()->id(), $id)) {
            return response()->json([
                'message' => 'У вас немає прав на редагування цього модуля'
            ], 403);
        }
        
        try {
            $module = $this->moduleService->updateModule($id, $request->all());
            
            return response()->json([
                'message' => 'Модуль успішно оновлено',
                'module' => $module
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Помилка при оновленні модуля',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function destroy($id)
    {
        // Перевіряємо права доступу
        if (!$this->moduleService->canUserManageModule(auth()->id(), $id)) {
            return response()->json([
                'message' => 'У вас немає прав на видалення цього модуля'
            ], 403);
        }
        
        try {
            $this->moduleService->deleteModule($id);
            
            return response()->json([
                'message' => 'Модуль успішно видалено'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Помилка при видаленні модуля',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function updatePositions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'positions' => 'required|array',
            'positions.*.id' => 'required|exists:modules,id',
            'positions.*.position' => 'required|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Перевіряємо права доступу для кожного модуля
        foreach ($request->positions as $position) {
            if (!$this->moduleService->canUserManageModule(auth()->id(), $position['id'])) {
                return response()->json([
                    'message' => 'У вас немає прав на зміну порядку деяких модулів'
                ], 403);
            }
        }
        
        try {
            $this->moduleService->updateModulePositions($request->positions);
            
            return response()->json([
                'message' => 'Порядок модулів успішно оновлено'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Помилка при оновленні порядку модулів',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}