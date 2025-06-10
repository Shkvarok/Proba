<?php

namespace App\Http\Middleware;

use App\Models\CourseEnrollment;
use App\Models\InternalTest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckCourseAccess
{
    /**
     * Handle an incoming request.
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

        // Спробуємо отримати courseId з різних джерел
        $courseId = $this->getCourseId($request);
        
        if (!$courseId) {
            return response()->json([
                'success' => false,
                'message' => 'ID курсу не вказано або не знайдено'
            ], 400);
        }

        // Адміністратори та супер-адміністратори мають повний доступ
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return $next($request);
        }

        // Викладачі мають доступ до своїх курсів
        if ($user->hasRole('teacher')) {
            $course = Course::find($courseId);
            if ($course && $course->instructor_id === $user->id) {
                return $next($request);
            }
        }

        // Перевірка, чи є у користувача доступ до курсу через підписку
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
                'success' => false,
                'message' => 'Доступ до курсу заборонено',
                'required_enrollment' => true,
                'course_id' => $courseId
            ], 403);
        }
        
        return $next($request);
    }

    /**
     * Отримати ID курсу з запиту
     */
    private function getCourseId(Request $request): ?int
    {
        // Спочатку перевіряємо прямий courseId
        $courseId = $request->route('courseId');
        if ($courseId) {
            return (int) $courseId;
        }

        // Якщо є testId, отримуємо courseId через тест
        $testId = $request->route('testId');
        if ($testId) {
            try {
                $test = InternalTest::with('lesson.module.course')->find($testId);
                if ($test && $test->lesson && $test->lesson->module && $test->lesson->module->course) {
                    return $test->lesson->module->course->id;
                }
            } catch (\Exception $e) {
                \Log::warning('Помилка при отриманні курсу через testId: ' . $e->getMessage());
            }
        }

        // Якщо є lessonId, отримуємо courseId через урок
        $lessonId = $request->route('lessonId');
        if ($lessonId) {
            try {
                $lesson = Lesson::with('module.course')->find($lessonId);
                if ($lesson && $lesson->module && $lesson->module->course) {
                    return $lesson->module->course->id;
                }
            } catch (\Exception $e) {
                \Log::warning('Помилка при отриманні курсу через lessonId: ' . $e->getMessage());
            }
        }

        // Якщо є moduleId, отримуємо courseId через модуль
        $moduleId = $request->route('moduleId');
        if ($moduleId) {
            try {
                $module = Module::with('course')->find($moduleId);
                if ($module && $module->course) {
                    return $module->course->id;
                }
            } catch (\Exception $e) {
                \Log::warning('Помилка при отриманні курсу через moduleId: ' . $e->getMessage());
            }
        }

        // Перевіряємо attemptId (може бути в маршрутах тестів)
        $attemptId = $request->route('attemptId');
        if ($attemptId) {
            try {
                $attempt = \App\Models\TestAttempt::with('internalTest.lesson.module.course')->find($attemptId);
                if ($attempt && $attempt->internalTest && $attempt->internalTest->lesson && 
                    $attempt->internalTest->lesson->module && $attempt->internalTest->lesson->module->course) {
                    return $attempt->internalTest->lesson->module->course->id;
                }
            } catch (\Exception $e) {
                \Log::warning('Помилка при отриманні курсу через attemptId: ' . $e->getMessage());
            }
        }

        // Перевіряємо questionId (для медіафайлів)
        $questionId = $request->route('questionId');
        if ($questionId) {
            try {
                $question = \App\Models\TestQuestion::with('internalTest.lesson.module.course')->find($questionId);
                if ($question && $question->internalTest && $question->internalTest->lesson && 
                    $question->internalTest->lesson->module && $question->internalTest->lesson->module->course) {
                    return $question->internalTest->lesson->module->course->id;
                }
            } catch (\Exception $e) {
                \Log::warning('Помилка при отриманні курсу через questionId: ' . $e->getMessage());
            }
        }

        // Перевіряємо answerId (для медіафайлів відповідей)
        $answerId = $request->route('answerId');
        if ($answerId) {
            try {
                $answer = \App\Models\QuestionAnswer::with('testQuestion.internalTest.lesson.module.course')->find($answerId);
                if ($answer && $answer->testQuestion && $answer->testQuestion->internalTest && 
                    $answer->testQuestion->internalTest->lesson && $answer->testQuestion->internalTest->lesson->module && 
                    $answer->testQuestion->internalTest->lesson->module->course) {
                    return $answer->testQuestion->internalTest->lesson->module->course->id;
                }
            } catch (\Exception $e) {
                \Log::warning('Помилка при отриманні курсу через answerId: ' . $e->getMessage());
            }
        }

        return null;
    }
}