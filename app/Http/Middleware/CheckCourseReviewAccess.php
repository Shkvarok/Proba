<?php

namespace App\Http\Middleware;

use App\Models\CourseEnrollment;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCourseReviewAccess
{
    /**
     * Перевіряє, чи має користувач доступ до курсу для залишення відгуку
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        // Отримання ID курсу з маршруту
        $courseId = $request->route('courseId');
        
        if (!$courseId) {
            return response()->json([
                'message' => 'Курс не знайдено'
            ], 404);
        }
        
        // Пропускаємо перевірку для адміністраторів
        if ($user->isAdmin()) {
            return $next($request);
        }
        
        // Перевірка наявності активної підписки на курс
        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();
            
        if (!$enrollment) {
            return response()->json([
                'message' => 'Ви не маєте доступу до цього курсу або ваша підписка закінчилася'
            ], 403);
        }
        
        return $next($request);
    }
}