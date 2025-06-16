<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class ProgressController extends Controller
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Розпочати урок
     * POST /api/lessons/{lessonId}/start
     */
    public function startLesson(int $lessonId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $progress = $this->progressService->startLesson($userId, $lessonId);

            return response()->json([
                'success' => true,
                'message' => 'Урок розпочато',
                'progress' => [
                    'lesson_id' => $progress->lesson_id,
                    'is_completed' => $progress->is_completed,
                    'progress_percentage' => $progress->progress_percentage,
                    'started_at' => $progress->started_at,
                    'last_accessed_at' => $progress->last_accessed_at,
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Помилка при розпочинанні уроку', [
                'lesson_id' => $lessonId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при розпочинанні уроку: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Завершити урок
     * POST /api/lessons/{lessonId}/complete
     */
    public function completeLesson(int $lessonId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $progress = $this->progressService->completeLesson($userId, $lessonId);

            return response()->json([
                'success' => true,
                'message' => 'Урок завершено',
                'progress' => [
                    'lesson_id' => $progress->lesson_id,
                    'is_completed' => $progress->is_completed,
                    'progress_percentage' => $progress->progress_percentage,
                    'completed_at' => $progress->completed_at,
                    'time_spent' => $progress->time_spent,
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Помилка при завершенні уроку', [
                'lesson_id' => $lessonId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при завершенні уроку: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Оновити прогрес уроку
     * PUT /api/lessons/{lessonId}/progress
     */
    public function updateLessonProgress(Request $request, int $lessonId): JsonResponse
    {
        try {
            $request->validate([
                'progress_percentage' => 'required|numeric|min:0|max:100',
                'time_spent' => 'nullable|integer|min:0',
            ]);

            $userId = auth()->id();
            $percentage = $request->input('progress_percentage');
            $timeSpent = $request->input('time_spent', 0);

            $progress = $this->progressService->updateLessonProgress($userId, $lessonId, $percentage, $timeSpent);

            return response()->json([
                'success' => true,
                'message' => 'Прогрес оновлено',
                'progress' => [
                    'lesson_id' => $progress->lesson_id,
                    'is_completed' => $progress->is_completed,
                    'progress_percentage' => $progress->progress_percentage,
                    'time_spent' => $progress->time_spent,
                    'last_accessed_at' => $progress->last_accessed_at,
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Помилка при оновленні прогресу уроку', [
                'lesson_id' => $lessonId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при оновленні прогресу: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Отримати прогрес курсу
     * GET /api/courses/{courseId}/progress
     */
    public function getCourseProgress(int $courseId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $progress = $this->progressService->getCourseProgress($userId, $courseId);

            return response()->json([
                'success' => true,
                'progress' => $progress
            ]);

        } catch (Exception $e) {
            Log::error('Помилка при отриманні прогресу курсу', [
                'course_id' => $courseId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні прогресу курсу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати детальний прогрес курсу з модулями і уроками
     * GET /api/courses/{courseId}/detailed-progress
     */
    public function getDetailedCourseProgress(int $courseId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $progress = $this->progressService->getDetailedCourseProgress($userId, $courseId);

            return response()->json([
                'success' => true,
                'progress' => $progress
            ]);

        } catch (Exception $e) {
            Log::error('Помилка при отриманні детального прогресу курсу', [
                'course_id' => $courseId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні детального прогресу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати прогрес користувача по всіх курсах
     * GET /api/users/my-progress
     */
    public function getMyProgress(): JsonResponse
    {
        try {
            $user = auth()->user();
            $progress = $user->getAllCoursesProgress();

            return response()->json([
                'success' => true,
                'progress' => $progress
            ]);

        } catch (Exception $e) {
            Log::error('Помилка при отриманні прогресу користувача', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні прогресу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати статистику навчання користувача
     * GET /api/users/learning-stats
     */
    public function getLearningStats(): JsonResponse
    {
        try {
            $userId = auth()->id();
            $stats = $this->progressService->getUserLearningStats($userId);

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            Log::error('Помилка при отриманні статистики навчання', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні статистики: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати наступний урок для вивчення
     * GET /api/courses/{courseId}/next-lesson
     */
    public function getNextLesson(int $courseId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $lesson = $this->progressService->getNextLesson($userId, $courseId);

            if (!$lesson) {
                return response()->json([
                    'success' => true,
                    'message' => 'Всі уроки курсу завершені',
                    'next_lesson' => null
                ]);
            }

            return response()->json([
                'success' => true,
                'next_lesson' => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'description' => $lesson->description,
                    'type' => $lesson->type,
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Помилка при отриманні наступного уроку', [
                'course_id' => $courseId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні наступного уроку: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Скинути прогрес уроку
     * DELETE /api/lessons/{lessonId}/progress
     */
    public function resetLessonProgress(int $lessonId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $this->progressService->resetLessonProgress($userId, $lessonId);
            return response()->json([
                'success' => true,
                'message' => 'Прогрес уроку скинуто'
            ]);
        } catch (Exception $e) {
            Log::error('Помилка при скиданні прогресу уроку', [
                'lesson_id' => $lessonId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Помилка при скиданні прогресу уроку: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати прогрес уроку
     * GET /api/lessons/{lessonId}/progress
     */
    public function getLessonProgress(int $lessonId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $progress = $this->progressService->getLessonProgress($userId, $lessonId);
            return response()->json([
                'success' => true,
                'progress' => $progress
            ]);
        } catch (Exception $e) {
            Log::error('Помилка при отриманні прогресу уроку', [
                'lesson_id' => $lessonId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні прогресу уроку: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Перевірити доступ до уроку
     * GET /api/lessons/{lessonId}/access
     */
    public function checkLessonAccess(int $lessonId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $hasAccess = $this->progressService->checkLessonAccess($userId, $lessonId);
            return response()->json([
                'success' => true,
                'has_access' => $hasAccess
            ]);
        } catch (Exception $e) {
            Log::error('Помилка при перевірці доступу до уроку', [
                'lesson_id' => $lessonId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Помилка при перевірці доступу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Скинути прогрес курсу
     * DELETE /api/courses/{courseId}/progress
     */
    public function resetCourseProgress(int $courseId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $this->progressService->resetCourseProgress($userId, $courseId);
            return response()->json([
                'success' => true,
                'message' => 'Прогрес курсу скинуто'
            ]);
        } catch (Exception $e) {
            Log::error('Помилка при скиданні прогресу курсу', [
                'course_id' => $courseId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Помилка при скиданні прогресу курсу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Топ студентів курсу
     * GET /api/courses/{courseId}/top-students
     */
    public function getCourseTopStudents(int $courseId): JsonResponse
    {
        try {
            $topStudents = $this->progressService->getCourseTopStudents($courseId);
            return response()->json([
                'success' => true,
                'top_students' => $topStudents
            ]);
        } catch (Exception $e) {
            Log::error('Помилка при отриманні топ студентів курсу', [
                'course_id' => $courseId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні топ студентів: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Остання активність користувача
     * GET /api/users/recent-activity
     */
    public function getRecentActivity(): JsonResponse
    {
        try {
            $userId = auth()->id();
            $activity = $this->progressService->getRecentActivity($userId);
            return response()->json([
                'success' => true,
                'recent_activity' => $activity
            ]);
        } catch (Exception $e) {
            Log::error('Помилка при отриманні останньої активності', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні останньої активності: ' . $e->getMessage()
            ], 500);
        }
    }
}