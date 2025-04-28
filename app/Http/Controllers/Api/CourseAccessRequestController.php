<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseAccessRequest;
use App\Models\StudentCourse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourseAccessRequestController extends Controller
{
    /**
     * Створення запиту на доступ до курсу
     */
    public function requestAccess(Request $request)
    {
        // Перевіряємо, чи авторизований користувач
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        
        // Валідація даних
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|integer|exists:courses,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $courseId = $request->input('course_id');
        $userId = $request->user()->id;
        
        // Перевірка, чи вже є доступ до курсу
        $hasAccess = StudentCourse::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('is_active', true)
            ->exists();
            
        if ($hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Ви вже маєте доступ до цього курсу'
            ], 400);
        }
        
        // Перевірка, чи вже є невирішений запит
        $existingRequest = CourseAccessRequest::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('status', CourseAccessRequest::STATUS_PENDING)
            ->first();
            
        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Ви вже подали запит на доступ до цього курсу. Будь ласка, очікуйте на його розгляд.',
                'request_id' => $existingRequest->id
            ], 400);
        }
        
        // Створення запиту
        $accessRequest = CourseAccessRequest::create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'status' => CourseAccessRequest::STATUS_PENDING
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Запит на доступ до курсу успішно створено. Очікуйте на його розгляд адміністратором.',
            'data' => $accessRequest
        ], 201);
    }
    
    /**
     * Отримання списку запитів на доступ до курсів
     */
    public function index(Request $request)
    {
        // Перевіряємо, чи має користувач доступ до перегляду запитів
        if (!$request->user() || !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }
        
        // Фільтрація за статусом
        $status = $request->input('status');
        $query = CourseAccessRequest::with(['user', 'course', 'processor']);
        
        if ($status) {
            $query->where('status', $status);
        }
        
        // Фільтрація за курсом
        if ($request->has('course_id')) {
            $query->where('course_id', $request->input('course_id'));
        }
        
        // Фільтрація за користувачем
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        
        // Сортування та пагінація
        $requests = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));
        
        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }
    
    /**
     * Отримання інформації про конкретний запит
     */
    public function show(Request $request, $id)
    {
        // Перевіряємо, чи має користувач доступ до перегляду запитів
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        
        $accessRequest = CourseAccessRequest::with(['user', 'course', 'processor'])->findOrFail($id);
        
        // Перевіряємо права доступу
        $isAdmin = $request->user()->hasAnyRole(['admin', 'super_admin']);
        $isUserRequest = $request->user()->id === $accessRequest->user_id;
        
        if (!$isAdmin && !$isUserRequest) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього запиту'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $accessRequest
        ]);
    }
    
    /**
     * Затвердження запиту на доступ до курсу
     */
    public function approve(Request $request, $id)
    {
        // Перевіряємо, чи має користувач доступ до затвердження запитів
        if (!$request->user() || !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'comment' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:today',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $accessRequest = CourseAccessRequest::findOrFail($id);
        
        // Перевіряємо, чи запит ще не оброблений
        if ($accessRequest->status !== CourseAccessRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Цей запит вже був оброблений'
            ], 400);
        }
        
        // Оновлюємо статус запиту
        $accessRequest->update([
            'status' => CourseAccessRequest::STATUS_APPROVED,
            'comment' => $request->input('comment'),
            'processed_by' => $request->user()->id,
            'processed_at' => now()
        ]);
        
        // Надаємо доступ до курсу
        StudentCourse::create([
            'user_id' => $accessRequest->user_id,
            'course_id' => $accessRequest->course_id,
            'access_granted_at' => now(),
            'expires_at' => $request->input('expires_at'),
            'is_active' => true,
            'granted_by' => $request->user()->id
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Запит на доступ до курсу успішно затверджено',
            'data' => $accessRequest->fresh(['user', 'course', 'processor'])
        ]);
    }
    
    /**
     * Відхилення запиту на доступ до курсу
     */
    public function reject(Request $request, $id)
    {
        // Перевіряємо, чи має користувач доступ до відхилення запитів
        if (!$request->user() || !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'comment' => 'nullable|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $accessRequest = CourseAccessRequest::findOrFail($id);
        
        // Перевіряємо, чи запит ще не оброблений
        if ($accessRequest->status !== CourseAccessRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Цей запит вже був оброблений'
            ], 400);
        }
        
        // Оновлюємо статус запиту
        $accessRequest->update([
            'status' => CourseAccessRequest::STATUS_REJECTED,
            'comment' => $request->input('comment'),
            'processed_by' => $request->user()->id,
            'processed_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Запит на доступ до курсу відхилено',
            'data' => $accessRequest->fresh(['user', 'course', 'processor'])
        ]);
    }
}