<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseEnrollmentController extends Controller
{
    /**
     * Отримати всі підписки поточного користувача
     */
    public function index()
    {
        $enrollments = Auth::user()->courseEnrollments()
            ->with('course')
            ->get();
            
        return response()->json(['enrollments' => $enrollments]);
    }
    
    /**
     * Перевірити, чи має користувач доступ до курсу
     */
    public function checkAccess($courseId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();
            
        return response()->json([
            'has_access' => $enrollment !== null,
            'enrollment' => $enrollment
        ]);
    }
    
    /**
     * Надати безкоштовний доступ до курсу
     */
    public function enrollFree(Request $request, $courseId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Перевірка, чи курс безкоштовний
        if (!$course->is_free) {
            return response()->json([
                'message' => 'Цей курс не є безкоштовним'
            ], 403);
        }
        
        // Перевірка, чи вже є підписка
        $existingEnrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();
            
        if ($existingEnrollment) {
            return response()->json([
                'message' => 'Ви вже підписані на цей курс',
                'enrollment' => $existingEnrollment
            ]);
        }
        
        // Створення нової підписки
        $enrollment = CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrollment_type' => 'free',
            'is_active' => true,
        ]);
        
        return response()->json([
            'message' => 'Ви успішно підписалися на курс',
            'enrollment' => $enrollment
        ], 201);
    }
    
    // Інші методи для управління підписками...
}