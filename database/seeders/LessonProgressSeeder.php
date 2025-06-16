<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LessonProgress;
use App\Models\User;
use App\Models\Course;
use App\Models\Lesson;
use Carbon\Carbon;

class LessonProgressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Отримуємо користувачів-студентів (не адмінів і не викладачів)
        $students = User::whereHas('role', function($query) {
            $query->where('name', 'student');
        })->limit(10)->get();

        // Якщо немає студентів, створюємо кілька тестових
        if ($students->isEmpty()) {
            $students = User::limit(5)->get();
        }

        // Отримуємо курси з уроками
        $courses = Course::with(['modules.lessons'])
            ->where('is_published', true)
            ->limit(3)
            ->get();

        if ($courses->isEmpty()) {
            $this->command->info('Немає опублікованих курсів для створення прогресу');
            return;
        }

        $progressCount = 0;

        foreach ($students as $student) {
            foreach ($courses as $course) {
                // Імітуємо підписку на курс (якщо система підписок реалізована)
                // Тут можна додати логіку створення CourseEnrollment

                // Отримуємо всі уроки курсу
                $allLessons = collect();
                foreach ($course->modules as $module) {
                    $allLessons = $allLessons->concat($module->lessons);
                }

                if ($allLessons->isEmpty()) {
                    continue;
                }

                // Визначаємо, скільки уроків "пройшов" студент (від 0% до 100%)
                $completionRate = rand(0, 100) / 100;
                $lessonsToComplete = (int) ($allLessons->count() * $completionRate);

                // Сортуємо уроки за модулями і позиціями
                $sortedLessons = $allLessons->sortBy(function($lesson) {
                    return $lesson->module->position * 1000 + $lesson->position;
                });

                $startDate = Carbon::now()->subDays(rand(1, 30));

                foreach ($sortedLessons->take($lessonsToComplete) as $index => $lesson) {
                    $isCompleted = $index < $lessonsToComplete;
                    $progressPercentage = $isCompleted ? 100 : rand(10, 90);
                    
                    $lessonStartDate = $startDate->copy()->addHours(rand(1, 72));
                    $timeSpent = rand(300, 3600); // 5-60 хвилин
                    
                    $progressData = [
                        'user_id' => $student->id,
                        'lesson_id' => $lesson->id,
                        'is_completed' => $isCompleted,
                        'progress_percentage' => $progressPercentage,
                        'time_spent' => $timeSpent,
                        'started_at' => $lessonStartDate,
                        'last_accessed_at' => $lessonStartDate->copy()->addMinutes(rand(10, 180)),
                    ];

                    if ($isCompleted) {
                        $progressData['completed_at'] = $lessonStartDate->copy()->addSeconds($timeSpent);
                    }

                    // Додаємо додаткові дані для деяких уроків
                    if (rand(1, 100) <= 30) { // 30% імовірність
                        $progressData['additional_data'] = [
                            'attempts' => rand(1, 3),
                            'last_score' => rand(60, 100),
                            'notes' => 'Автоматично згенерована замітка',
                        ];
                    }

                    LessonProgress::create($progressData);
                    $progressCount++;

                    $startDate->addHours(rand(2, 24)); // Додаємо інтервал між уроками
                }

                // Додаємо кілька незавершених уроків з частковим прогресом
                $partialLessons = $sortedLessons->slice($lessonsToComplete)->take(rand(1, 3));
                foreach ($partialLessons as $lesson) {
                    if (rand(1, 100) <= 70) { // 70% імовірність початку уроку
                        $lessonStartDate = $startDate->copy()->addHours(rand(1, 48));
                        
                        LessonProgress::create([
                            'user_id' => $student->id,
                            'lesson_id' => $lesson->id,
                            'is_completed' => false,
                            'progress_percentage' => rand(5, 50),
                            'time_spent' => rand(120, 1800), // 2-30 хвилин
                            'started_at' => $lessonStartDate,
                            'last_accessed_at' => $lessonStartDate->copy()->addMinutes(rand(5, 90)),
                        ]);
                        $progressCount++;
                    }
                }
            }
        }

        $this->command->info("Створено {$progressCount} записів прогресу уроків");

        // Створюємо кілька записів з особливими сценаріями
        $this->createSpecialScenarios();
    }

    /**
     * Створити спеціальні сценарії для тестування
     */
    private function createSpecialScenarios(): void
    {
        $users = User::limit(2)->get();
        $lessons = Lesson::limit(5)->get();

        if ($users->isEmpty() || $lessons->isEmpty()) {
            return;
        }

        $scenarios = [
            // Швидкий учень (завершує уроки швидко)
            [
                'time_multiplier' => 0.5,
                'completion_rate' => 0.9,
                'name' => 'Швидкий учень'
            ],
            // Повільний учень (витрачає багато часу)
            [
                'time_multiplier' => 2.0,
                'completion_rate' => 0.4,
                'name' => 'Повільний учень'
            ],
        ];

        foreach ($scenarios as $index => $scenario) {
            if (!isset($users[$index])) break;
            
            $user = $users[$index];
            $lessonsToProcess = $lessons->take((int)($lessons->count() * $scenario['completion_rate']));

            foreach ($lessonsToProcess as $lesson) {
                $baseTime = $lesson->getEstimatedDuration() ? $lesson->getEstimatedDuration() * 60 : 1800; // 30 хв за замовчуванням
                $actualTime = (int)($baseTime * $scenario['time_multiplier']);
                
                $startDate = Carbon::now()->subDays(rand(5, 15));
                
                LessonProgress::create([
                    'user_id' => $user->id,
                    'lesson_id' => $lesson->id,
                    'is_completed' => true,
                    'progress_percentage' => 100,
                    'time_spent' => $actualTime,
                    'started_at' => $startDate,
                    'completed_at' => $startDate->copy()->addSeconds($actualTime),
                    'last_accessed_at' => $startDate->copy()->addSeconds($actualTime + rand(0, 3600)),
                    'additional_data' => [
                        'scenario' => $scenario['name'],
                        'learning_style' => $index === 0 ? 'fast_learner' : 'thorough_learner',
                    ]
                ]);
            }
        }

        $this->command->info("Додано спеціальні сценарії прогресу");
    }

    /**
     * Створити прогрес для конкретного користувача та курсу
     */
    public function createProgressForUserAndCourse(int $userId, int $courseId, float $completionRate = 0.5): int
    {
        $course = Course::with(['modules.lessons'])->findOrFail($courseId);
        $user = User::findOrFail($userId);

        $allLessons = collect();
        foreach ($course->modules as $module) {
            $allLessons = $allLessons->concat($module->lessons);
        }

        if ($allLessons->isEmpty()) {
            return 0;
        }

        $lessonsToComplete = (int)($allLessons->count() * $completionRate);
        $sortedLessons = $allLessons->sortBy(function($lesson) {
            return $lesson->module->position * 1000 + $lesson->position;
        });

        $progressCount = 0;
        $startDate = Carbon::now()->subDays(rand(1, 7));

        foreach ($sortedLessons->take($lessonsToComplete) as $lesson) {
            $timeSpent = rand(300, 3600);
            $lessonStartDate = $startDate->copy()->addHours(rand(1, 24));

            $progress = LessonProgress::create([
                'user_id' => $userId,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'progress_percentage' => 100,
                'time_spent' => $timeSpent,
                'started_at' => $lessonStartDate,
                'completed_at' => $lessonStartDate->copy()->addSeconds($timeSpent),
                'last_accessed_at' => $lessonStartDate->copy()->addSeconds($timeSpent + rand(0, 1800)),
            ]);

            $progressCount++;
            $startDate->addHours(rand(2, 12));
        }

        // Додаємо один урок в процесі
        $nextLesson = $sortedLessons->get($lessonsToComplete);
        if ($nextLesson) {
            LessonProgress::create([
                'user_id' => $userId,
                'lesson_id' => $nextLesson->id,
                'is_completed' => false,
                'progress_percentage' => rand(25, 75),
                'time_spent' => rand(300, 1800),
                'started_at' => $startDate->copy()->addHours(rand(1, 6)),
                'last_accessed_at' => Carbon::now()->subHours(rand(0, 2)),
            ]);
            $progressCount++;
        }

        return $progressCount;
    }

    /**
     * Створити реалістичні паттерни навчання
     */
    private function createRealisticLearningPatterns(): void
    {
        $users = User::limit(3)->get();
        $courses = Course::with('modules.lessons')->limit(2)->get();

        if ($users->isEmpty() || $courses->isEmpty()) {
            return;
        }

        $patterns = [
            'regular_learner' => [
                'sessions_per_week' => 3,
                'avg_session_duration' => 45, // хвилин
                'completion_consistency' => 0.8,
                'break_probability' => 0.1
            ],
            'intensive_learner' => [
                'sessions_per_week' => 6,
                'avg_session_duration' => 90,
                'completion_consistency' => 0.95,
                'break_probability' => 0.05
            ],
            'casual_learner' => [
                'sessions_per_week' => 1,
                'avg_session_duration' => 30,
                'completion_consistency' => 0.6,
                'break_probability' => 0.3
            ]
        ];

        foreach ($users as $index => $user) {
            $patternName = array_keys($patterns)[$index % count($patterns)];
            $pattern = $patterns[$patternName];

            foreach ($courses as $course) {
                $this->simulateLearningPattern($user, $course, $pattern, $patternName);
            }
        }

        $this->command->info("Створено реалістичні паттерни навчання");
    }

    /**
     * Симулювати паттерн навчання для користувача
     */
    private function simulateLearningPattern(User $user, Course $course, array $pattern, string $patternName): void
    {
        $allLessons = collect();
        foreach ($course->modules as $module) {
            $allLessons = $allLessons->concat($module->lessons);
        }

        if ($allLessons->isEmpty()) {
            return;
        }

        $sortedLessons = $allLessons->sortBy(function($lesson) {
            return $lesson->module->position * 1000 + $lesson->position;
        });

        $currentDate = Carbon::now()->subWeeks(4);
        $lessonIndex = 0;
        $totalLessons = $sortedLessons->count();

        // Симулюємо навчання протягом 4 тижнів
        for ($week = 0; $week < 4; $week++) {
            $sessionsThisWeek = $pattern['sessions_per_week'];
            
            for ($session = 0; $session < $sessionsThisWeek; $session++) {
                if ($lessonIndex >= $totalLessons) break;

                // Випадковий день тижня для сесії
                $sessionDate = $currentDate->copy()->addDays(rand(0, 6))->addHours(rand(9, 21));
                
                // Тривалість сесії
                $sessionDuration = $pattern['avg_session_duration'] + rand(-15, 15);
                $sessionEnd = $sessionDate->copy()->addMinutes($sessionDuration);
                
                // Кількість уроків в сесії
                $lessonsInSession = rand(1, 3);
                
                for ($i = 0; $i < $lessonsInSession && $lessonIndex < $totalLessons; $i++) {
                    $lesson = $sortedLessons[$lessonIndex];
                    
                    // Визначаємо чи буде урок завершений
                    $willComplete = rand(1, 100) <= ($pattern['completion_consistency'] * 100);
                    
                    // Час на урок
                    $lessonTime = rand(10, 45) * 60; // 10-45 хвилин
                    
                    $progressData = [
                        'user_id' => $user->id,
                        'lesson_id' => $lesson->id,
                        'started_at' => $sessionDate->copy()->addMinutes($i * 15),
                        'last_accessed_at' => $sessionDate->copy()->addMinutes($i * 15 + rand(10, 30)),
                        'time_spent' => $lessonTime,
                        'additional_data' => [
                            'learning_pattern' => $patternName,
                            'week' => $week + 1,
                            'session' => $session + 1,
                        ]
                    ];

                    if ($willComplete) {
                        $progressData['is_completed'] = true;
                        $progressData['progress_percentage'] = 100;
                        $progressData['completed_at'] = $progressData['started_at']->copy()->addSeconds($lessonTime);
                    } else {
                        $progressData['is_completed'] = false;
                        $progressData['progress_percentage'] = rand(20, 80);
                        
                        // Можливість перерви в навчанні
                        if (rand(1, 100) <= ($pattern['break_probability'] * 100)) {
                            break; // Перерва в сесії
                        }
                    }

                    LessonProgress::create($progressData);
                    
                    if ($willComplete) {
                        $lessonIndex++;
                    }
                }
                
                if ($lessonIndex >= $totalLessons) break;
            }
            
            $currentDate->addWeek();
            
            if ($lessonIndex >= $totalLessons) break;
        }
    }
}
    