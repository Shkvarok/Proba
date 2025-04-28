<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\StudentCourse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentCourseController extends Controller
{
    /**
     * Отримання списку курсів студента
     */
    public function myCourses(Request $request)
    {
        // Перевіряємо, чи авторизований користувач
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        
        $userId = $request->user()->id;
        
        // Отримуємо активні доступи до курсів
        $courses = StudentCourse::where('user_id', $userId)
            ->active()
            ->with('course')
            ->get()
            ->pluck('course');
        
        return response()->json([
            'success' => true,
            'data' => $courses
        ]);
    }
    
    /**
     * Отримання списку студентів, які мають доступ до курсу
     */
    public function courseStudents(Request $request, $courseId)
    {
        // Перевіряємо, чи має користувач доступ до перегляду студентів курсу
        if (!$request->user() || !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            $course = Course::findOrFail($courseId);
            
            // Перевіряємо, чи поточний користувач є інструктором цього курсу
            if ($request->user()->id !== $course->instructor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає доступу до цього ресурсу'
                ], 403);
            }
        }
        
        // Перевіряємо, чи існує курс
        Course::findOrFail($courseId);
        
        // Отримуємо студентів, які мають доступ до курсу
        $query = StudentCourse::where('course_id', $courseId)
            ->with('student');
        
        // Фільтрація за активністю
        if ($request->has('active')) {
            $isActive = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }
        
        $students = $query->paginate($request->input('per_page', 15));
        
        return response()->json([
            'success' => true,
            'data' => $students
        ]);
    }
    
    /**
     * Надання доступу до курсу (для адміністраторів)
     */
    public function grantAccess(Request $request)
    {
        // Перевіряємо, чи має користувач доступ до надання доступу до курсів
        if (!$request->user() || !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }
        
        // Валідація даних
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'expires_at' => 'nullable|date|after:today',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $userId = $request->input('user_id');
        $courseId = $request->input('course_id');
        
        // Перевірка, чи вже є доступ
        $existingAccess = StudentCourse::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();
            
        if ($existingAccess) {
            // Оновлюємо існуючий доступ
            $existingAccess->update([
                'is_active' => true,
                'expires_at' => $request->input('expires_at'),
                'granted_by' => $request->user()->id,
                'access_granted_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Доступ до курсу успішно оновлено',
                'data' => $existingAccess->fresh()
            ]);
        }
        
        // Створюємо новий доступ
        $access = StudentCourse::create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'access_granted_at' => now(),
            'expires_at' => $request->input('expires_at'),
            'is_active' => true,
            'granted_by' => $request->user()->id
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Доступ до курсу успішно надано',
            'data' => $access
        ], 201);
    }
    
    /**
     * Відкликання доступу до курсу
     */
    public function revokeAccess(Request $request, $id)
    {
        // Перевіряємо, чи має користувач доступ до відкликання доступу до курсів
        if (!$request->user() || !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }
        
        $access = StudentCourse::findOrFail($id);
        
        // Деактивуємо доступ
        $access->update([
            'is_active' => false
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Доступ до курсу успішно відкликано',
            'data' => $access->fresh()
        ]);
    }
    
    /**
     * Перевірка доступу студента до конкретного курсу
     */
    public function checkAccess(Request $request, $courseId)
    {
        // Перевіряємо, чи авторизований користувач
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        
        $userId = $request->user()->id;
        
        // Перевірка, чи має користувач адміністративний доступ
        $isAdmin = $request->user()->hasAnyRole(['admin', 'super_admin']);
        
        // Отримуємо курс
        $course = Course::findOrFail($courseId);
        
        // Перевірка, чи є користувач інструктором курсу
        $isInstructor = $course->instructor_id === $userId;
        
        if ($isAdmin || $isInstructor) {
            return response()->json([
                'success' => true,
                'has_access' => true,
                'access_type' => $isAdmin ? 'admin' : 'instructor'
            ]);
        }
        
        // Перевірка доступу для звичайного студента
        $access = StudentCourse::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->active()
            ->first();
        
        if ($access) {
            return response()->json([
                'success' => true,
                'has_access' => true,
                'access_type' => 'student',
                'access_details' => [
                    'granted_at' => $access->access_granted_at,
                    'expires_at' => $access->expires_at
                ]
            ]);
        }
        
        // Перевірка, чи курс безкоштовний
        if ($course->price == 0) {
            return response()->json([
                'success' => true,
                'has_access' => true,
                'access_type' => 'free_course'
            ]);
        }
        
        return response()->json([
            'success' => true,
            'has_access' => false,
            'message' => 'У вас немає доступу до цього курсу'
        ]);
    }
}