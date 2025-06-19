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
        // Спочатку очищуємо існуючі підписки
        CourseEnrollment::truncate();
        
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

        $this->command->info('Створюємо підписки для студентів...');

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
                $enrollmentType = $this->getEnrollmentType($course);
                
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
                    'payment_id' => null,
                    'is_active' => $isActive,
                    'created_at' => $enrolledAt,
                    'updated_at' => $enrolledAt,
                ];
            }
        }

        $this->command->info('Створюємо підписки для викладачів...');

        // Додаємо кілька підписок для викладачів
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
                            'enrollment_type' => 'purchase', // Викладачі "купують" доступ до курсів колег
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
            $this->command->info('Зберігаємо підписки в базу даних...');
            
            // Розбиваємо на частини для уникнення помилок з великими вставками
            $chunks = array_chunk($enrollments, 50);
            
            foreach ($chunks as $chunk) {
                DB::table('course_enrollments')->insert($chunk);
            }
        }

        $this->command->info('Створено ' . count($enrollments) . ' підписок на курси.');
        
        // Виводимо статистику
        $this->outputStatistics();
    }

    /**
     * Отримати тип підписки залежно від ціни курсу
     */
    private function getEnrollmentType(Course $course): string
    {
        if ($course->price == 0) {
            return 'free';
        }

        // Для платних курсів розподіляємо типи підписок
        $rand = rand(1, 100);
        
        if ($rand <= 60) {
            return 'purchase';
        } elseif ($rand <= 85) {
            return 'subscription';
        } else {
            return 'gift';
        }
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
                // Безкоштовні курси мають безстроковий доступ
                return null;
                
            case 'purchase':
                // Покупка дає безстроковий доступ
                return null;
                
            case 'subscription':
                // Підписка обмежена в часі (зазвичай 1 місяць, 3 місяці або 1 рік)
                $periods = [1, 3, 12]; // місяців
                $selectedPeriod = $periods[array_rand($periods)];
                return $enrolledAt->copy()->addMonths($selectedPeriod);
                
            case 'gift':
                // Подарунковий доступ може бути різним (3-12 місяців)
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
            $courseTitle = $stat->course ? $stat->course->title : "Курс ID: {$stat->course_id}";
            $this->command->info("  {$courseTitle}: {$stat->enrollments_count} підписок");
        }
    }
}