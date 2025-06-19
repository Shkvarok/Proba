<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TeacherStudentAccess
{
    /**
     * Перевіряє, чи має вчитель доступ до студента
     */
    private function hasAccessToStudent(int $teacherId, int $studentId, ?int $courseId = null): bool
    {
        $query = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.instructor_id', $teacherId)
            ->where('course_enrollments.user_id', $studentId)
            ->where('course_enrollments.is_active', true);

        // Якщо вказано конкретний курс, додаємо перевірку
        if ($courseId) {
            $query->where('course_enrollments.course_id', $courseId);
        }

        return $query->exists();
    }

    /**
     * Перевіряє, чи має вчитель доступ до уроку
     */
    private function hasAccessToLesson(int $teacherId, int $lessonId): bool
    {
        return DB::table('lessons')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->join('courses', 'modules.course_id', '=', 'courses.id')
            ->where('courses.instructor_id', $teacherId)
            ->where('lessons.id', $lessonId)
            ->exists();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        // Якщо користувач не авторизований
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Необхідна авторизація'
            ], 401);
        }

        // Якщо це супер адмін, дозволяємо доступ до всіх студентів
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Якщо це адмін, також дозволяємо доступ (можна обмежити за потреби)
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Для вчителів перевіряємо доступ до студента
        if ($user->isTeacher()) {
            $studentId = $request->route('studentId');
            $courseId = $request->input('course_id') ?? $request->route('courseId');

            if ($studentId && !$this->hasAccessToStudent($user->id, $studentId, $courseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає доступу до цього студента'
                ], 403);
            }

            return $next($request);
        }

        // Інші ролі не мають доступу
        return response()->json([
            'success' => false,
            'message' => 'У вас немає прав для перегляду інформації про студентів'
        ], 403);
    }
}

