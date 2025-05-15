<?php

namespace App\Http\Middleware;

use App\Models\CourseEnrollment;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckCourseAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $courseId = $request->route('courseId');
        
        if (!$courseId) {
            return response()->json([
                'message' => 'ID курсу не вказано'
            ], 400);
        }
        
        // Перевірка, чи є у користувача доступ до курсу
        $enrollment = CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $courseId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();
            
        if (!$enrollment) {
            return response()->json([
                'message' => 'Доступ до курсу заборонено',
                'required_enrollment' => true
            ], 403);
        }
        
        return $next($request);
    }
}