<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestQuestion;
use App\Models\QuestionAnswer;
use App\Services\TestMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TestMediaController extends Controller
{
    protected TestMediaService $mediaService;

    public function __construct(TestMediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Отримати медіафайл питання
     */
    public function getQuestionMedia($questionId)
    {
        try {
            $question = TestQuestion::findOrFail($questionId);
            
            // Перевіряємо права доступу до тесту
            if (!$this->canUserAccessTest($question->internalTest)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає доступу до цього контенту'
                ], 403);
            }

            if (!$question->hasMedia()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Медіафайл не знайдено'
                ], 404);
            }

            // Перевіряємо, чи існує файл
            if (!$this->mediaService->fileExists($question->media_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Файл не існує на сервері'
                ], 404);
            }

            // Повертаємо інформацію про файл
            return response()->json([
                'success' => true,
                'media' => [
                    'type' => $question->media_type,
                    'url' => $question->getMediaUrl(),
                    'original_name' => $question->media_original_name,
                    'size' => $question->media_size,
                    'mime_type' => $question->media_mime_type,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні медіафайлу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати медіафайл відповіді
     */
    public function getAnswerMedia($answerId)
    {
        try {
            $answer = QuestionAnswer::with('testQuestion.internalTest')->findOrFail($answerId);
            
            // Перевіряємо права доступу до тесту
            if (!$this->canUserAccessTest($answer->testQuestion->internalTest)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає доступу до цього контенту'
                ], 403);
            }

            if (!$answer->hasMedia()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Медіафайл не знайдено'
                ], 404);
            }

            // Перевіряємо, чи існує файл
            if (!$this->mediaService->fileExists($answer->media_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Файл не існує на сервері'
                ], 404);
            }

            // Повертаємо інформацію про файл
            return response()->json([
                'success' => true,
                'media' => [
                    'type' => $answer->media_type,
                    'url' => $answer->getMediaUrl(),
                    'original_name' => $answer->media_original_name,
                    'size' => $answer->media_size,
                    'mime_type' => $answer->media_mime_type,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні медіафайлу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Видалити медіафайл питання
     */
    public function deleteQuestionMedia($questionId)
    {
        try {
            $question = TestQuestion::findOrFail($questionId);
            
            // Перевіряємо права доступу до управління тестом
            if (!$this->canUserManageTest($question->internalTest)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для видалення медіафайлів цього тесту'
                ], 403);
            }

            if (!$question->hasMedia()) {
                return response()->json([
                    'success' => false,
                    'message' => 'У питання немає медіафайлу'
                ], 404);
            }

            $question->deleteMedia();

            return response()->json([
                'success' => true,
                'message' => 'Медіафайл питання успішно видалено'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при видаленні медіафайлу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Видалити медіафайл відповіді
     */
    public function deleteAnswerMedia($answerId)
    {
        try {
            $answer = QuestionAnswer::with('testQuestion.internalTest')->findOrFail($answerId);
            
            // Перевіряємо права доступу до управління тестом
            if (!$this->canUserManageTest($answer->testQuestion->internalTest)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для видалення медіафайлів цього тесту'
                ], 403);
            }

            if (!$answer->hasMedia()) {
                return response()->json([
                    'success' => false,
                    'message' => 'У відповіді немає медіафайлу'
                ], 404);
            }

            $answer->deleteMedia();

            return response()->json([
                'success' => true,
                'message' => 'Медіафайл відповіді успішно видалено'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при видаленні медіафайлу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати статистику медіафайлів
     */
    public function getMediaStats()
    {
        try {
            $user = Auth::user();
            
            // Тільки адміністратори можуть переглядати загальну статистику
            if (!$user->hasRole('admin') && !$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для перегляду статистики'
                ], 403);
            }

            $stats = $this->mediaService->getMediaStats();

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні статистики: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Очистити сирітські файли (файли без відповідних записів у БД)
     */
    public function cleanupOrphanedFiles()
    {
        try {
            $user = Auth::user();
            
            // Тільки адміністратори можуть виконувати очищення
            if (!$user->hasRole('admin') && !$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для виконання очищення'
                ], 403);
            }

            $deletedCount = 0;
            $deletedSize = 0;

            // Отримуємо всі файли в директоріях тестів
            $questionFiles = Storage::disk('public')->allFiles('tests/questions');
            $answerFiles = Storage::disk('public')->allFiles('tests/answers');
            
            // Отримуємо всі шляхи файлів з БД
            $questionPaths = TestQuestion::whereNotNull('media_path')->pluck('media_path')->toArray();
            $answerPaths = QuestionAnswer::whereNotNull('media_path')->pluck('media_path')->toArray();
            
            $allDbPaths = array_merge($questionPaths, $answerPaths);

            // Перевіряємо файли питань
            foreach ($questionFiles as $file) {
                if (!in_array($file, $allDbPaths)) {
                    $size = Storage::disk('public')->size($file);
                    Storage::disk('public')->delete($file);
                    $deletedCount++;
                    $deletedSize += $size;
                }
            }

            // Перевіряємо файли відповідей
            foreach ($answerFiles as $file) {
                if (!in_array($file, $allDbPaths)) {
                    $size = Storage::disk('public')->size($file);
                    Storage::disk('public')->delete($file);
                    $deletedCount++;
                    $deletedSize += $size;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Очищення завершено',
                'deleted_files' => $deletedCount,
                'freed_space_mb' => round($deletedSize / (1024 * 1024), 2)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при очищенні файлів: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Завантажити медіафайл для питання
     */
    public function uploadQuestionMedia(Request $request, $questionId)
    {
        $request->validate([
            'media' => 'required|file|max:51200', // 50MB
        ]);

        try {
            $question = TestQuestion::findOrFail($questionId);
            
            // Перевіряємо права доступу
            if (!$this->canUserManageTest($question->internalTest)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для завантаження медіафайлів до цього тесту'
                ], 403);
            }

            // Видаляємо старий медіафайл, якщо є
            if ($question->hasMedia()) {
                $question->deleteMedia();
            }

            // Завантажуємо новий медіафайл
            $mediaData = $this->mediaService->uploadQuestionMedia($request->file('media'));
            $question->update($mediaData);

            return response()->json([
                'success' => true,
                'message' => 'Медіафайл успішно завантажено',
                'media' => [
                    'type' => $question->media_type,
                    'url' => $question->getMediaUrl(),
                    'original_name' => $question->media_original_name,
                    'size' => $question->media_size,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при завантаженні медіафайлу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Завантажити медіафайл для відповіді
     */
    public function uploadAnswerMedia(Request $request, $answerId)
    {
        $request->validate([
            'media' => 'required|file|max:51200', // 50MB
        ]);

        try {
            $answer = QuestionAnswer::with('testQuestion.internalTest')->findOrFail($answerId);
            
            // Перевіряємо права доступу
            if (!$this->canUserManageTest($answer->testQuestion->internalTest)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для завантаження медіафайлів до цього тесту'
                ], 403);
            }

            // Видаляємо старий медіафайл, якщо є
            if ($answer->hasMedia()) {
                $answer->deleteMedia();
            }

            // Завантажуємо новий медіафайл
            $mediaData = $this->mediaService->uploadAnswerMedia($request->file('media'));
            $answer->update($mediaData);

            return response()->json([
                'success' => true,
                'message' => 'Медіафайл успішно завантажено',
                'media' => [
                    'type' => $answer->media_type,
                    'url' => $answer->getMediaUrl(),
                    'original_name' => $answer->media_original_name,
                    'size' => $answer->media_size,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при завантаженні медіафайлу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Перевірити права доступу до тесту
     */
    private function canUserAccessTest($test): bool
    {
        $user = Auth::user();
        
        // Адміністратори та викладачі можуть переглядати все
        if ($this->canUserManageTest($test)) {
            return true;
        }

        // Студенти можуть переглядати активні тести з доступом до курсу
        return $test->isActive();
    }

    /**
     * Перевірити права доступу до управління тестом
     */
    private function canUserManageTest($test): bool
    {
        $user = Auth::user();
        
        // Адміністратори та супер-адміністратори можуть все
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        // Викладачі можуть управляти своїми курсами
        if ($user->hasRole('teacher')) {
            return $test->lesson->module->course->instructor_id === $user->id;
        }

        return false;
    }
}