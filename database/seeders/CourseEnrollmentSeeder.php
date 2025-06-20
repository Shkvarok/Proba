<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * CourseEnrollmentSeeder - створює реалістичні підписки на курси
 * 
 * ВАЖЛИВО: Поле enrollment_type підтримує ці значення:
 * - 'purchase' (одноразова покупка курсу)
 * - 'subscription' (підписка на курс)
 * - 'free' (безкоштовний доступ)
 * - 'gift' (подарунковий доступ)
 */
class CourseEnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Очищаємо таблицю перед сидуванням, щоб уникнути дублювань
        DB::table('course_enrollments')->truncate();

        // Перевіряємо наявність необхідних даних
        $coursesCount = Course::count();
        $usersCount = User::count();

        if ($coursesCount === 0) {
            $this->command->error('Курси відсутні в базі даних. Спочатку запустіть CourseSeeder.');
            return;
        }

        if ($usersCount === 0) {
            $this->command->error('Користувачі відсутні в базі даних. Спочатку запустіть UserSeeder.');
            return;
        }

        // Отримуємо курси та студентів
        $courses = Course::where('is_published', true)->get();
        $studentRole = Role::where('name', 'student')->first();
        
        if (!$studentRole) {
            $this->command->error('Роль "student" не знайдена в базі даних.');
            return;
        }

        $students = User::where('role_id', $studentRole->id)->get();
        
        if ($students->isEmpty()) {
            $this->command->error('Студенти відсутні в базі даних.');
            return;
        }

        $enrollments = [];
        $now = now();

        // Створюємо підписки для кожного студента
        foreach ($students as $student) {
            // Кожен студент підписується на випадкову кількість курсів (1-5)
            $coursesToEnroll = $courses->random(rand(1, min(5, $courses->count())));
            
            foreach ($coursesToEnroll as $course) {
                // Перевіряємо, чи немає вже підписки на цей курс
                $existingEnrollment = CourseEnrollment::where('user_id', $student->id)
                    ->where('course_id', $course->id)
                    ->exists();
                
                if ($existingEnrollment) {
                    continue; // Пропускаємо, якщо вже є підписка
                }

                // Визначаємо тип підписки
                $enrollmentType = $this->getRandomEnrollmentType($course);
                
                // Визначаємо дати
                $enrolledAt = $this->getRandomEnrollmentDate();
                $expiresAt = $this->getExpirationDate($enrollmentType, $enrolledAt);
                
                // Визначаємо активність підписки
                $isActive = $this->determineIfActive($expiresAt);

                $enrollments[] = [
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'enrolled_at' => $enrolledAt,
                    'expires_at' => $expiresAt,
                    'enrollment_type' => $enrollmentType,
                    'payment_id' => null, // Поки не створюємо платежі
                    'is_active' => $isActive,
                    'created_at' => $enrolledAt,
                    'updated_at' => $enrolledAt,
                ];
            }
        }

        // Додаємо кілька підписок для викладачів (щоб вони мали доступ до курсів інших викладачів)
        $teacherRole = Role::where('name', 'teacher')->first();
        if ($teacherRole) {
            $teachers = User::where('role_id', $teacherRole->id)->get();
            
            foreach ($teachers as $teacher) {
                // Кожен викладач підписується на 1-2 курси інших викладачів
                $otherTeachersCourses = $courses->where('instructor_id', '!=', $teacher->id);
                
                if ($otherTeachersCourses->isNotEmpty()) {
                    $coursesToEnroll = $otherTeachersCourses->random(rand(1, min(2, $otherTeachersCourses->count())));
                    
                    foreach ($coursesToEnroll as $course) {
                        // Перевіряємо, чи немає вже підписки
                        $existingEnrollment = CourseEnrollment::where('user_id', $teacher->id)
                            ->where('course_id', $course->id)
                            ->exists();
                        
                        if ($existingEnrollment) {
                            continue;
                        }

                        $enrolledAt = $this->getRandomEnrollmentDate();
                        
                        $enrollments[] = [
                            'user_id' => $teacher->id,
                            'course_id' => $course->id,
                            'enrolled_at' => $enrolledAt,
                            'expires_at' => null, // Викладачі мають безстроковий доступ
                            'enrollment_type' => 'purchase', // Використовуємо дозволене значення
                            'payment_id' => null,
                            'is_active' => true,
                            'created_at' => $enrolledAt,
                            'updated_at' => $enrolledAt,
                        ];
                    }
                }
            }
        }

        // Вставляємо підписки в базу даних
        if (!empty($enrollments)) {
            // Розбиваємо на частини для уникнення помилок з великими вставками
            $chunks = array_chunk($enrollments, 100);
            
            foreach ($chunks as $chunk) {
                DB::table('course_enrollments')->insert($chunk);
            }
        }

        $this->command->info('Створено ' . count($enrollments) . ' підписок на курси.');
        
        // Виводимо статистику
        $this->outputStatistics();
    }

    /**
     * Отримати випадковий тип підписки залежно від ціни курсу
     * Використовуємо дозволені значення: 'purchase', 'subscription', 'free', 'gift'
     */
    private function getRandomEnrollmentType(Course $course): string
    {
        if ($course->price == 0) {
            return 'free';
        }
        // Для платних курсів розподіляємо типи підписок
        $types = ['purchase', 'subscription', 'gift'];
        $weights = [60, 30, 10]; // 60% purchase, 30% subscription, 10% gift
        return $this->getWeightedRandom($types, $weights);
    }

    /**
     * Отримати випадкову дату підписки (останні 6 місяців)
     */
    private function getRandomEnrollmentDate(): Carbon
    {
        $start = now()->subMonths(6);
        $end = now();
        
        $randomTimestamp = rand($start->timestamp, $end->timestamp);
        
        return Carbon::createFromTimestamp($randomTimestamp);
    }

    /**
     * Отримати дату закінчення підписки залежно від типу
     */
    private function getExpirationDate(string $enrollmentType, Carbon $enrolledAt): ?Carbon
    {
        switch ($enrollmentType) {
            case 'free':
                return null;
            case 'purchase':
                return null;
            case 'subscription':
                $periods = [1, 3, 12]; // місяців
                $selectedPeriod = $periods[array_rand($periods)];
                return $enrolledAt->copy()->addMonths($selectedPeriod);
            case 'gift':
                return $enrolledAt->copy()->addMonths(rand(3, 12));
            default:
                return $enrolledAt->copy()->addYear();
        }
    }

    /**
     * Визначити, чи активна підписка
     */
    private function determineIfActive(?Carbon $expiresAt): bool
    {
        if ($expiresAt === null) {
            return true; // Безстрокові підписки завжди активні
        }

        // 90% підписок активні, 10% деактивовані
        if (rand(1, 100) <= 10) {
            return false;
        }

        // Якщо підписка не закінчилася, вона активна
        return $expiresAt->isFuture();
    }

    /**
     * Вибрати випадковий елемент з урахуванням ваг
     */
    private function getWeightedRandom(array $items, array $weights): mixed
    {
        $totalWeight = array_sum($weights);
        $random = rand(1, $totalWeight);
        
        $currentWeight = 0;
        for ($i = 0; $i < count($items); $i++) {
            $currentWeight += $weights[$i];
            if ($random <= $currentWeight) {
                return $items[$i];
            }
        }
        
        return $items[0]; // Fallback
    }

    /**
     * Вивести статистику створених підписок
     */
    private function outputStatistics(): void
    {
        $total = CourseEnrollment::count();
        $active = CourseEnrollment::where('is_active', true)->count();
        $inactive = $total - $active;
        
        $byType = CourseEnrollment::select('enrollment_type', DB::raw('count(*) as count'))
            ->groupBy('enrollment_type')
            ->pluck('count', 'enrollment_type')
            ->toArray();

        $this->command->info("\n=== СТАТИСТИКА ПІДПИСОК ===");
        $this->command->info("Загальна кількість: {$total}");
        $this->command->info("Активних: {$active}");
        $this->command->info("Неактивних: {$inactive}");
        
        $this->command->info("\nПо типах підписок:");
        foreach ($byType as $type => $count) {
            $this->command->info("  {$type}: {$count}");
        }
        
        // Статистика по курсах
        $courseStats = CourseEnrollment::select('course_id', DB::raw('count(*) as enrollments_count'))
            ->with('course:id,title')
            ->groupBy('course_id')
            ->orderByDesc('enrollments_count')
            ->limit(5)
            ->get();
            
        $this->command->info("\nТоп 5 курсів за кількістю підписок:");
        foreach ($courseStats as $stat) {
            $this->command->info("  {$stat->course->title}: {$stat->enrollments_count} підписок");
        }
    }
}