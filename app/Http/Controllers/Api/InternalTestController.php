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
            return response()->json([
                'success' => false,
                'message' => 'Помилка при видаленні тесту: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Додати питання до тесту
     */
    public function addQuestion(Request $request, $testId)
    {
        $validator = Validator::make($request->all(), [
            'question_text' => 'required|string',
            'question_type' => 'required|in:single_choice,multiple_choice,text_input',
            'points' => 'integer|min:1|max:100',
            'explanation' => 'nullable|string',
            'is_required' => 'boolean',
            'question_media' => 'nullable|file|max:51200', // 50MB
            'answers' => 'required|array|min:1',
            'answers.*.text' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
            'answers.*.media' => 'nullable|file|max:51200', // 50MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка валідації',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $test = InternalTest::findOrFail($testId);

            // Перевіряємо права доступу
            if (!$this->canUserManageTest($test)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для додавання питань до цього тесту'
                ], 403);
            }

            DB::beginTransaction();

            // Отримуємо наступну позицію
            $nextPosition = $test->questions()->max('position') + 1;

            $questionData = [
                'internal_test_id' => $test->id,
                'question_text' => $request->question_text,
                'question_type' => $request->question_type,
                'position' => $nextPosition,
                'points' => $request->points ?? 1,
                'explanation' => $request->explanation,
                'is_required' => $request->is_required ?? true,
            ];

            // Обробка медіафайлу для питання
            if ($request->hasFile('question_media')) {
                $mediaData = $this->mediaService->uploadQuestionMedia($request->file('question_media'));
                $questionData = array_merge($questionData, $mediaData);
            }

            $question = TestQuestion::create($questionData);

            // Додаємо відповіді
            foreach ($request->answers as $index => $answerData) {
                $answerInfo = [
                    'test_question_id' => $question->id,
                    'answer_text' => $answerData['text'],
                    'is_correct' => $answerData['is_correct'],
                    'position' => $index + 1,
                ];

                // Обробка медіафайлу для відповіді
                if (isset($answerData['media']) && $answerData['media'] instanceof \Illuminate\Http\UploadedFile) {
                    $answerMediaData = $this->mediaService->uploadAnswerMedia($answerData['media']);
                    $answerInfo = array_merge($answerInfo, $answerMediaData);
                }

                QuestionAnswer::create($answerInfo);
            }

            // Валідація питання
            if (!$question->validateQuestionType()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Невірна конфігурація питання. Перевірте правильні відповіді.'
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Питання успішно додано',
                'question' => new TestQuestionResource($question->load('answers'))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Помилка при додаванні питання: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Оновити питання
     */
    public function updateQuestion(Request $request, $testId, $questionId)
    {
        $validator = Validator::make($request->all(), [
            'question_text' => 'string',
            'points' => 'integer|min:1|max:100',
            'explanation' => 'nullable|string',
            'is_required' => 'boolean',
            'question_media' => 'nullable|file|max:51200',
            'remove_question_media' => 'boolean',
            'answers' => 'array',
            'answers.*.id' => 'nullable|exists:question_answers,id',
            'answers.*.text' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
            'answers.*.media' => 'nullable|file|max:51200',
            'answers.*.remove_media' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка валідації',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $test = InternalTest::findOrFail($testId);
            $question = TestQuestion::where('internal_test_id', $testId)
                ->findOrFail($questionId);

            // Перевіряємо права доступу
            if (!$this->canUserManageTest($test)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для редагування питань цього тесту'
                ], 403);
            }

            DB::beginTransaction();

            // Оновлюємо питання
            $updateData = $request->only([
                'question_text', 'points', 'explanation', 'is_required'
            ]);

            // Обробка медіафайлу питання
            if ($request->boolean('remove_question_media')) {
                $question->deleteMedia();
            } elseif ($request->hasFile('question_media')) {
                // Видаляємо старий медіафайл
                $question->deleteMedia();
                // Завантажуємо новий
                $mediaData = $this->mediaService->uploadQuestionMedia($request->file('question_media'));
                $updateData = array_merge($updateData, $mediaData);
            }

            $question->update($updateData);

            // Оновлюємо відповіді, якщо вони передані
            if ($request->has('answers')) {
                // Видаляємо старі відповіді
                foreach ($question->answers as $oldAnswer) {
                    $oldAnswer->deleteMedia();
                }
                $question->answers()->delete();

                // Додаємо нові відповіді
                foreach ($request->answers as $index => $answerData) {
                    $answerInfo = [
                        'test_question_id' => $question->id,
                        'answer_text' => $answerData['text'],
                        'is_correct' => $answerData['is_correct'],
                        'position' => $index + 1,
                    ];

                    // Обробка медіафайлу для відповіді
                    if (isset($answerData['media']) && $answerData['media'] instanceof \Illuminate\Http\UploadedFile) {
                        $answerMediaData = $this->mediaService->uploadAnswerMedia($answerData['media']);
                        $answerInfo = array_merge($answerInfo, $answerMediaData);
                    }

                    QuestionAnswer::create($answerInfo);
                }

                // Валідація питання
                if (!$question->validateQuestionType()) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Невірна конфігурація питання. Перевірте правильні відповіді.'
                    ], 422);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Питання успішно оновлено',
                'question' => new TestQuestionResource($question->load('answers'))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Помилка при оновленні питання: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Видалити питання
     */
    public function deleteQuestion($testId, $questionId)
    {
        try {
            $test = InternalTest::findOrFail($testId);
            $question = TestQuestion::where('internal_test_id', $testId)
                ->findOrFail($questionId);

            // Перевіряємо права доступу
            if (!$this->canUserManageTest($test)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для видалення питань цього тесту'
                ], 403);
            }

            DB::beginTransaction();

            // Видаляємо медіафайли
            $question->deleteMedia();
            foreach ($question->answers as $answer) {
                $answer->deleteMedia();
            }

            $question->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Питання успішно видалено'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Помилка при видаленні питання: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Почати проходження тесту
     */
    public function startAttempt($testId)
    {
        try {
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
            
            // Підготовуємо питання для збереження (без правильних відповідей)
            $questionsData = $questions->map(function ($question) {
                $questionData = [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'points' => $question->points,
                    'explanation' => $question->explanation,
                    'answers' => $question->getAnswersForDisplay()
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

            return response()->json([
                'success' => true,
                'message' => 'Тест розпочато',
                'attempt' => new TestAttemptResource($attempt),
                'questions' => $questionsData,
                'time_limit_minutes' => $test->time_limit_minutes,
                'passing_score' => $test->passing_score
            ]);

        } catch (\Exception $e) {
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

            // Перевіряємо відповідь
            $answerResult = $question->checkAnswer($request->answer);

            // Зберігаємо відповідь
            $response = TestResponse::create([
                'test_attempt_id' => $attemptId,
                'test_question_id' => $question->id,
                'selected_answers' => $question->question_type !== 'text_input' 
                    ? (is_array($request->answer) ? $request->answer : [$request->answer])
                    : null,
                'text_answer' => $question->question_type === 'text_input' ? $request->answer : null,
                'is_correct' => $answerResult['is_correct'],
                'points_earned' => $answerResult['points_earned'],
            ]);

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
            return response()->json([
                'success' => false,
                'message' => 'Помилка при збереженні відповіді: ' . $e->getMessage()
            ], 500);
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
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні поточної спроби: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати аналітику тесту (для викладачів та адміністраторів)
     */
    public function getAnalytics($testId)
    {
        try {
            $test = InternalTest::findOrFail($testId);

            // Перевіряємо права доступу
            if (!$this->canUserManageTest($test)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для перегляду аналітики цього тесту'
                ], 403);
            }

            // Ensure $test is a single model, not a collection
            if ($test instanceof \Illuminate\Database\Eloquent\Collection) {
                $test = $test->first();
            }

            $analytics = [
                'test_info' => [
                    'title' => $test->title,
                    'total_questions' => $test->questions()->count(),
                    'max_score' => $test->getMaxScore(),
                    'passing_score' => $test->passing_score,
                ],
                'attempts_stats' => [
                    'total_attempts' => $test->attempts()->count(),
                    'completed_attempts' => $test->attempts()->where('status', 'completed')->count(),
                    'in_progress_attempts' => $test->attempts()->where('status', 'in_progress')->count(),
                    'abandoned_attempts' => $test->attempts()->where('status', 'abandoned')->count(),
                ],
                'performance_stats' => [
                    'average_score' => $test->attempts()->where('status', 'completed')->avg('percentage') ?? 0,
                    'highest_score' => $test->attempts()->where('status', 'completed')->max('percentage') ?? 0,
                    'lowest_score' => $test->attempts()->where('status', 'completed')->min('percentage') ?? 0,
                    'pass_rate' => $this->calculatePassRate($test),
                ],
                'question_analytics' => $this->getQuestionAnalytics($test),
                'media_stats' => $this->getTestMediaStats($test),
            ];

            return response()->json([
                'success' => true,
                'analytics' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні аналітики: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати всі спроби тесту (для викладачів та адміністраторів)
     */
    public function getAllAttempts($testId)
    {
        try {
            $test = InternalTest::findOrFail($testId);

            // Перевіряємо права доступу
            if (!$this->canUserManageTest($test)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для перегляду спроб цього тесту'
                ], 403);
            }

            $attempts = TestAttempt::where('internal_test_id', $testId)
                ->with('user:id,name,email')
                ->orderByDesc('created_at')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'attempts' => TestAttemptResource::collection($attempts)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні списку спроб: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Дублювати тест
     */
    public function duplicateTest($testId)
    {
        try {
            $originalTest = InternalTest::with('questions.answers')->findOrFail($testId);

            // Перевіряємо права доступу
            if (!$this->canUserManageTest($originalTest)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає прав для дублювання цього тесту'
                ], 403);
            }

            DB::beginTransaction();

            // Створюємо копію тесту
            $newTest = InternalTest::create([
                'lesson_id' => $originalTest->lesson_id,
                'title' => $originalTest->title . ' (Копія)',
                'description' => $originalTest->description,
                'passing_score' => $originalTest->passing_score,
                'time_limit_minutes' => $originalTest->time_limit_minutes,
                'status' => 'draft', // Нові тести завжди в чернетці
                'randomize_questions' => $originalTest->randomize_questions,
                'questions_to_show' => $originalTest->questions_to_show,
                'max_attempts' => $originalTest->max_attempts,
                'show_results_immediately' => $originalTest->show_results_immediately,
            ]);

            // Копіюємо питання та відповіді з медіафайлами
            foreach ($originalTest->questions as $originalQuestion) {
                $newQuestionData = [
                    'internal_test_id' => $newTest->id,
                    'question_text' => $originalQuestion->question_text,
                    'question_type' => $originalQuestion->question_type,
                    'position' => $originalQuestion->position,
                    'points' => $originalQuestion->points,
                    'explanation' => $originalQuestion->explanation,
                    'is_required' => $originalQuestion->is_required,
                ];

                // Копіюємо медіафайл питання
                if ($originalQuestion->hasMedia()) {
                    $copiedMedia = $this->mediaService->copyMedia(
                        $originalQuestion->media_path,
                        "tests/questions/{$originalQuestion->media_type}s/" . date('Y/m')
                    );
                    if ($copiedMedia) {
                        $newQuestionData['media_type'] = $originalQuestion->media_type;
                        $newQuestionData['media_path'] = $copiedMedia['media_path'];
                        $newQuestionData['media_original_name'] = $originalQuestion->media_original_name;
                        $newQuestionData['media_size'] = $copiedMedia['media_size'];
                        $newQuestionData['media_mime_type'] = $originalQuestion->media_mime_type;
                    }
                }

                $newQuestion = TestQuestion::create($newQuestionData);

                foreach ($originalQuestion->answers as $originalAnswer) {
                    $newAnswerData = [
                        'test_question_id' => $newQuestion->id,
                        'answer_text' => $originalAnswer->answer_text,
                        'is_correct' => $originalAnswer->is_correct,
                        'position' => $originalAnswer->position,
                    ];

                    // Копіюємо медіафайл відповіді
                    if ($originalAnswer->hasMedia()) {
                        $copiedMedia = $this->mediaService->copyMedia(
                            $originalAnswer->media_path,
                            "tests/answers/{$originalAnswer->media_type}s/" . date('Y/m')
                        );
                        if ($copiedMedia) {
                            $newAnswerData['media_type'] = $originalAnswer->media_type;
                            $newAnswerData['media_path'] = $copiedMedia['media_path'];
                            $newAnswerData['media_original_name'] = $originalAnswer->media_original_name;
                            $newAnswerData['media_size'] = $copiedMedia['media_size'];
                            $newAnswerData['media_mime_type'] = $originalAnswer->media_mime_type;
                        }
                    }

                    QuestionAnswer::create($newAnswerData);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Тест успішно дубльовано',
                'test' => new InternalTestResource($newTest->load('questions.answers'))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Помилка при дублюванні тесту: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Видалити медіафайли тесту
     */
    private function deleteTestMediaFiles(InternalTest $test): void
    {
        foreach ($test->questions as $question) {
            $question->deleteMedia();
            foreach ($question->answers as $answer) {
                $answer->deleteMedia();
            }
        }
    }

    /**
     * Отримати статистику медіафайлів тесту
     */
    private function getTestMediaStats(InternalTest $test): array
    {
        $questionMediaCount = $test->questions()->whereNotNull('media_path')->count();
        $answerMediaCount = QuestionAnswer::whereHas('testQuestion', function($query) use ($test) {
            $query->where('internal_test_id', $test->id);
        })->whereNotNull('media_path')->count();

        $totalSize = $test->questions()->whereNotNull('media_path')->sum('media_size') +
                    QuestionAnswer::whereHas('testQuestion', function($query) use ($test) {
                        $query->where('internal_test_id', $test->id);
                    })->whereNotNull('media_path')->sum('media_size');

        return [
            'question_media_count' => $questionMediaCount,
            'answer_media_count' => $answerMediaCount,
            'total_media_count' => $questionMediaCount + $answerMediaCount,
            'total_size_mb' => round($totalSize / (1024 * 1024), 2),
        ];
    }

    /**
     * Підрахувати відсоток проходження тесту
     */
    private function calculatePassRate($test): float
    {
        $completedAttempts = $test->attempts()->where('status', 'completed')->count();
        
        if ($completedAttempts === 0) {
            return 0;
        }

        $passedAttempts = $test->attempts()
            ->where('status', 'completed')
            ->where('is_passed', true)
            ->count();

        return ($passedAttempts / $completedAttempts) * 100;
    }

    /**
     * Отримати аналітику по питаннях
     */
    private function getQuestionAnalytics($test): array
    {
        $questions = $test->questions()->withCount([
            'responses',
            'responses as correct_responses_count' => function ($query) {
                $query->where('is_correct', true);
            }
        ])->get();

        return $questions->map(function ($question) {
            $totalResponses = $question->responses_count;
            $correctResponses = $question->correct_responses_count;
            
            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'total_responses' => $totalResponses,
                'correct_responses' => $correctResponses,
                'difficulty_rate' => $totalResponses > 0 ? ($correctResponses / $totalResponses) * 100 : 0,
                'points' => $question->points,
                'has_media' => $question->hasMedia(),
            ];
        })->toArray();
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