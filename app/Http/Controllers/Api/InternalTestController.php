<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InternalTestRequest;
use App\Http\Requests\TestQuestionRequest;
use App\Http\Resources\InternalTestResource;
use App\Http\Resources\TestAttemptResource;
use App\Http\Resources\TestQuestionResource;
use App\Http\Resources\TestResponseResource;
use App\Models\InternalTest;
use App\Models\TestQuestion;
use App\Models\QuestionAnswer;
use App\Models\TestAttempt;
use App\Models\TestResponse;
use App\Models\Lesson;
use App\Services\TestMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InternalTestController extends Controller
{
    protected TestMediaService $mediaService;

    public function __construct(TestMediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Створити новий внутрішній тест
     */
    public function store(InternalTestRequest $request)
    {
        try {
            DB::beginTransaction();

            // Перевіряємо права доступу
            $lesson = Lesson::findOrFail($request->lesson_id);
            if (!$this->canUserManageLesson($lesson)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для створення тесту для цього уроку'
                ], 403);
            }

            $test = InternalTest::create($request->getTestData());

            // Оновлюємо LessonTest для підтримки внутрішнього тесту
            $lesson->test()->updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'source_type' => 'internal',
                    'time_limit_minutes' => $request->time_limit_minutes,
                    'passing_score' => $request->passing_score ?? 70,
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Тест успішно створено',
                'test' => new InternalTestResource($test->load('questions.answers'))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating internal test', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при створенні тесту: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати тест з питаннями
     */
    public function show($id)
    {
        try {
            $test = InternalTest::with('questions.answers', 'lesson')
                ->findOrFail($id);

            // Перевіряємо права доступу
            if (!$this->canUserViewTest($test)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає доступу до цього тесту'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'test' => new InternalTestResource($test)
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing internal test', [
                'test_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Тест не знайдено'
            ], 404);
        }
    }

    /**
     * Оновити тест
     */
    public function update(InternalTestRequest $request, $id)
    {
        try {
            $test = InternalTest::findOrFail($id);

            // Перевіряємо права доступу
            if (!$this->canUserManageTest($test)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для редагування цього тесту'
                ], 403);
            }

            $test->update($request->getTestData());

            // Оновлюємо також LessonTest
            if ($request->has('time_limit_minutes') || $request->has('passing_score')) {
                $test->lesson->test()->update([
                    'time_limit_minutes' => $request->time_limit_minutes ?? $test->time_limit_minutes,
                    'passing_score' => $request->passing_score ?? $test->passing_score,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Тест успішно оновлено',
                'test' => new InternalTestResource($test->load('questions.answers'))
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating internal test', [
                'test_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при оновленні тесту: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Видалити тест
     */
    public function destroy($id)
    {
        try {
            $test = InternalTest::findOrFail($id);

            // Перевіряємо права доступу
            if (!$this->canUserManageTest($test)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для видалення цього тесту'
                ], 403);
            }

            DB::beginTransaction();

            // Видаляємо медіафайли питань та відповідей
            if ($test instanceof \Illuminate\Database\Eloquent\Collection) {
                foreach ($test as $singleTest) {
                    $this->deleteTestMediaFiles($singleTest);
                }
            } else {
                $this->deleteTestMediaFiles($test);
            }

            // Видаляємо LessonTest
            $lessonTest = $test->lesson->test;
            if ($lessonTest) {
                $lessonTest->delete();
            }
            
            // Видаляємо сам тест (каскадно видаляться питання, відповіді та спроби)
            $test->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Тест успішно видалено'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting internal test', [
                'test_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при видаленні тесту: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Почати проходження тесту
     */
    public function startAttempt($testId)
    {
        try {
            Log::info('Starting test attempt', ['test_id' => $testId, 'user_id' => Auth::id()]);
            
            $test = InternalTest::findOrFail($testId);
            $userId = Auth::id();

            // Перевіряємо, чи може користувач проходити тест
            if (!$test->canUserAttempt($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ви не можете проходити цей тест. Можливо, вичерпано кількість спроб або тест неактивний.'
                ], 403);
            }

            // Перевіряємо, чи немає активної спроби
            $activeAttempt = TestAttempt::where('internal_test_id', $testId)
                ->where('user_id', $userId)
                ->where('status', 'in_progress')
                ->first();

            if ($activeAttempt) {
                return response()->json([
                    'success' => true,
                    'message' => 'У вас уже є активна спроба проходження тесту',
                    'attempt' => new TestAttemptResource($activeAttempt),
                    'questions' => $activeAttempt->questions_data
                ]);
            }

            // Отримуємо питання для проходження
            $questions = $test->getQuestionsForAttempt();
            
            Log::info('Questions retrieved for attempt', [
                'test_id' => $testId,
                'questions_count' => $questions->count()
            ]);
            
            // Підготовуємо питання для збереження (без правильних відповідей)
            $questionsData = $questions->map(function ($question) {
                Log::info('Processing question for attempt', [
                    'question_id' => $question->id,
                    'answers_count' => $question->answers->count()
                ]);
                
                $questionData = [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'points' => $question->points,
                    'explanation' => $question->explanation,
                    'answers' => $this->getAnswersForDisplay($question)
                ];

                // Додаємо медіафайл питання
                if ($question->hasMedia()) {
                    $questionData['media_url'] = $question->getMediaUrl();
                    $questionData['media_type'] = $question->media_type;
                }

                return $questionData;
            });

            // Створюємо нову спробу
            $attempt = TestAttempt::create([
                'internal_test_id' => $testId,
                'user_id' => $userId,
                'started_at' => Carbon::now(),
                'status' => 'in_progress',
                'questions_data' => $questionsData->toArray(),
                'max_score' => $questions->sum('points'),
            ]);

            Log::info('Test attempt created successfully', [
                'attempt_id' => $attempt->id,
                'test_id' => $testId,
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Тест розпочато',
                'attempt' => new TestAttemptResource($attempt),
                'questions' => $questionsData,
                'time_limit_minutes' => $test->time_limit_minutes,
                'passing_score' => $test->passing_score
            ]);

        } catch (\Exception $e) {
            Log::error('Error starting test attempt', [
                'test_id' => $testId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при початку тесту: ' . $e->getMessage()
            ], 500);
        }
    }

   /**
     * Відповісти на питання
     */
    public function submitAnswer(Request $request, $testId, $attemptId)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|exists:test_questions,id',
            'answer' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка валідації',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attempt = TestAttempt::where('internal_test_id', $testId)
                ->where('user_id', Auth::id())
                ->findOrFail($attemptId);

            // Перевіряємо, чи можна продовжити тест
            if (!$attempt->canContinue()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Час тесту закінчився або тест уже завершено'
                ], 403);
            }

            $question = TestQuestion::findOrFail($request->question_id);

            // Перевіряємо, чи користувач уже відповідав на це питання
            $existingResponse = TestResponse::where('test_attempt_id', $attemptId)
                ->where('test_question_id', $question->id)
                ->first();

            if ($existingResponse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ви вже відповіли на це питання'
                ], 403);
            }

            // Логуємо отриману відповідь для діагностики
            Log::info('Received answer for question', [
                'question_id' => $question->id,
                'question_type' => $question->question_type,
                'user_answer' => $request->answer,
                'answer_type' => gettype($request->answer)
            ]);

            // Підготовляємо відповідь в залежності від типу питання
            $userAnswer = $this->prepareUserAnswer($request->answer, $question->question_type);
            
            Log::info('Prepared answer for checking', [
                'question_id' => $question->id,
                'prepared_answer' => $userAnswer,
                'prepared_type' => gettype($userAnswer)
            ]);

            // Перевіряємо відповідь
            $answerResult = $question->checkAnswer($userAnswer);
            
            Log::info('Answer check result', [
                'question_id' => $question->id,
                'result' => $answerResult
            ]);

            // Зберігаємо відповідь
            $responseData = [
                'test_attempt_id' => $attemptId,
                'test_question_id' => $question->id,
                'is_correct' => $answerResult['is_correct'],
                'points_earned' => $answerResult['points_earned'],
            ];

            // Зберігаємо відповідь в залежності від типу питання
            if ($question->question_type === 'text_input') {
                $responseData['text_answer'] = is_array($userAnswer) ? implode(' ', $userAnswer) : (string)$userAnswer;
                $responseData['selected_answers'] = null;
            } else {
                $responseData['selected_answers'] = is_array($userAnswer) ? $userAnswer : [$userAnswer];
                $responseData['text_answer'] = null;
            }

            $response = TestResponse::create($responseData);

            // Перевіряємо, чи всі питання відповіджені
            $progress = $attempt->getProgress();
            $isCompleted = $progress['remaining_questions'] <= 1;

            if ($isCompleted || $attempt->isTimeUp()) {
                $attempt->complete();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Тест завершено',
                    'is_completed' => true,
                    'final_result' => [
                        'score' => $attempt->score,
                        'max_score' => $attempt->max_score,
                        'percentage' => $attempt->percentage,
                        'is_passed' => $attempt->is_passed,
                    ],
                    'show_results' => $attempt->internalTest->show_results_immediately
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Відповідь збережено',
                'is_completed' => false,
                'progress' => $progress,
                'show_answer_result' => $attempt->internalTest->show_results_immediately ? $answerResult : null
            ]);

        } catch (\Exception $e) {
            Log::error('Error submitting test answer', [
                'test_id' => $testId,
                'attempt_id' => $attemptId,
                'user_id' => Auth::id(),
                'question_id' => $request->question_id ?? null,
                'answer' => $request->answer ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при збереженні відповіді: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Підготувати відповідь користувача в залежності від типу питання
     */
     /**
     * Підготувати відповідь користувача в залежності від типу питання
     */
    private function prepareUserAnswer($answer, $questionType)
    {
        switch ($questionType) {
            case 'single_choice':
                // Для одиночного вибору очікуємо ID відповіді
                return is_array($answer) ? (int)$answer[0] : (int)$answer;
                
            case 'multiple_choice':
                // Для множинного вибору очікуємо масив ID відповідей
                if (is_array($answer)) {
                    return array_map('intval', $answer);
                } else {
                    return [(int)$answer];
                }
                
            case 'text_input':
                // Для текстового введення очікуємо рядок
                return is_array($answer) ? implode(' ', $answer) : (string)$answer;
                
            default:
                return $answer;
        }
    }

    

    /**
     * Завершити тест
     */
    public function finishAttempt($testId, $attemptId)
    {
        try {
            $attempt = TestAttempt::where('internal_test_id', $testId)
                ->where('user_id', Auth::id())
                ->findOrFail($attemptId);

            if ($attempt->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Тест уже завершено'
                ], 403);
            }

            $attempt->complete();

            $result = [
                'success' => true,
                'message' => 'Тест завершено',
                'result' => [
                    'score' => $attempt->score,
                    'max_score' => $attempt->max_score,
                    'percentage' => $attempt->percentage,
                    'is_passed' => $attempt->is_passed,
                    'completed_at' => $attempt->completed_at,
                ]
            ];

            // Додаємо детальні результати, якщо дозволено
            if ($attempt->internalTest->show_results_immediately) {
                $responses = $attempt->responses()->with('testQuestion')->get();
                $result['detailed_results'] = $responses->map(function ($response) {
                    return [
                        'question' => $response->testQuestion->question_text,
                        'user_answer' => $response->getUserAnswerText(),
                        'correct_answer' => $response->getCorrectAnswerText(),
                        'is_correct' => $response->is_correct,
                        'points_earned' => $response->points_earned,
                        'explanation' => $response->testQuestion->explanation,
                    ];
                });
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error finishing test attempt', [
                'test_id' => $testId,
                'attempt_id' => $attemptId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при завершенні тесту: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати результати тесту
     */
    public function getResults($testId, $attemptId)
    {
        try {
            $attempt = TestAttempt::where('internal_test_id', $testId)
                ->where('user_id', Auth::id())
                ->with('internalTest')
                ->findOrFail($attemptId);

            if ($attempt->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Тест ще не завершено'
                ], 403);
            }

            $result = [
                'success' => true,
                'result' => [
                    'score' => $attempt->score,
                    'max_score' => $attempt->max_score,
                    'percentage' => $attempt->percentage,
                    'is_passed' => $attempt->is_passed,
                    'completed_at' => $attempt->completed_at,
                    'duration_minutes' => $attempt->started_at->diffInMinutes($attempt->completed_at),
                ]
            ];

            // Додаємо детальні результати, якщо дозволено
            if ($attempt->internalTest->show_results_immediately) {
                $responses = $attempt->responses()->with('testQuestion')->get();
                $result['detailed_results'] = $responses->map(function ($response) {
                    return [
                        'question' => $response->testQuestion->question_text,
                        'user_answer' => $response->getUserAnswerText(),
                        'correct_answer' => $response->getCorrectAnswerText(),
                        'is_correct' => $response->is_correct,
                        'points_earned' => $response->points_earned,
                        'explanation' => $response->testQuestion->explanation,
                    ];
                });
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error getting test results', [
                'test_id' => $testId,
                'attempt_id' => $attemptId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні результатів: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати історію спроб користувача
     */
    public function getUserAttempts($testId)
    {
        try {
            $test = InternalTest::findOrFail($testId);
            $userId = Auth::id();

            $attempts = TestAttempt::where('internal_test_id', $testId)
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'attempts' => TestAttemptResource::collection($attempts),
                'can_attempt_again' => $test->canUserAttempt($userId),
                'max_attempts' => $test->max_attempts
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting user attempts', [
                'test_id' => $testId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні історії спроб: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати поточну активну спробу користувача
     */
    public function getCurrentAttempt($testId)
    {
        try {
            $userId = Auth::id();
            
            $attempt = TestAttempt::where('internal_test_id', $testId)
                ->where('user_id', $userId)
                ->where('status', 'in_progress')
                ->with('internalTest')
                ->first();

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Немає активної спроби проходження тесту'
                ], 404);
            }

            // Перевіряємо, чи не закінчився час
            if ($attempt->isTimeUp()) {
                $attempt->status = 'abandoned';
                $attempt->save();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Час тесту закінчився'
                ], 403);
            }

            $progress = $attempt->getProgress();
            
            return response()->json([
                'success' => true,
                'attempt' => new TestAttemptResource($attempt),
                'questions' => $attempt->questions_data,
                'progress' => $progress,
                'remaining_time_minutes' => $attempt->getRemainingTimeMinutes(),
                'answered_questions' => $attempt->responses()->pluck('test_question_id')->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting current attempt', [
                'test_id' => $testId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні поточної спроби: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати відповіді для відображення (без правильних відповідей)
     */
    private function getAnswersForDisplay($question)
    {
        return $question->answers->map(function ($answer) {
            $answerData = [
                'id' => $answer->id,
                'answer_text' => $answer->answer_text,
                'position' => $answer->position,
            ];

            // Додаємо медіафайл відповіді, якщо є
            if ($answer->hasMedia()) {
                $answerData['media_url'] = $answer->getMediaUrl();
                $answerData['media_type'] = $answer->media_type;
                $answerData['media_original_name'] = $answer->media_original_name;
                $answerData['has_media'] = true;
            } else {
                $answerData['has_media'] = false;
            }

            return $answerData;
        });
    }

    // Решта методів залишаються без змін...
    // (addQuestion, updateQuestion, deleteQuestion, getAnalytics, getAllAttempts, duplicateTest)

    /**
     * Видалити медіафайли тесту
     */
    private function deleteTestMediaFiles(InternalTest $test): void
    {
        foreach ($test->questions as $question) {
            if (method_exists($question, 'deleteMedia')) {
                $question->deleteMedia();
            }
            foreach ($question->answers as $answer) {
                if (method_exists($answer, 'deleteMedia')) {
                    $answer->deleteMedia();
                }
            }
        }
    }

    /**
     * Перевірити права доступу до уроку
     */
    private function canUserManageLesson($lesson): bool
    {
        $user = Auth::user();
        
        // Адміністратори та супер-адміністратори можуть все
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        // Викладачі можуть управляти своїми курсами
        if ($user->hasRole('teacher')) {
            return $lesson->module->course->instructor_id === $user->id;
        }

        return false;
    }

    /**
     * Перевірити права доступу до тесту
     */
    private function canUserManageTest($test): bool
    {
        return $this->canUserManageLesson($test->lesson);
    }

    /**
     * Перевірити права доступу до перегляду тесту
     */
    private function canUserViewTest($test): bool
    {
        $user = Auth::user();
        
        // Адміністратори та викладачі можуть переглядати
        if ($this->canUserManageTest($test)) {
            return true;
        }

        // Студенти можуть переглядати активні тести
        return $test->isActive();
    }
}