<?php

namespace App\Http\Middleware;

use App\Models\InternalTest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckTestAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Необхідна автентифікація'
            ], 401);
        }

        // Отримуємо ID тесту з маршруту
        $testId = $request->route('testId') ?? $request->route('id');
        
        if (!$testId) {
            return response()->json([
                'success' => false,
                'message' => 'ID тесту не вказано'
            ], 400);
        }

        try {
            $test = InternalTest::with('lesson.module.course')->findOrFail($testId);
            
            // Адміністратори та супер-адміністратори мають повний доступ
            if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
                return $next($request);
            }

            // Викладачі мають доступ до своїх курсів
            if ($user->hasRole('teacher')) {
                $course = $test->lesson->module->course;
                if ($course->instructor_id === $user->id) {
                    return $next($request);
                }
            }

            // Для студентів перевіряємо:
            // 1. Чи тест активний
            // 2. Чи є доступ до курсу
            
            if (!$test->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Тест неактивний'
                ], 403);
            }

            $course = $test->lesson->module->course;
            
            // Перевіряємо, чи користувач підписаний на курс
            $hasAccess = $course->enrolledUsers()
                ->where('user_id', $user->id)
                ->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає доступу до цього курсу'
                ], 403);
            }

            return $next($request);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Тест не знайдено'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при перевірці доступу: ' . $e->getMessage()
            ], 500);
        }
    }
}