<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

/**
 * TestPaymentScenariosSeeder - створює специфічні сценарії для тестування
 * 
 * Створює:
 * - Платежі для конкретних тестових користувачів
 * - Різні сценарії платежів (успішні, невдалі, повернені)
 * - Тестові дані для перевірки API ендпоінтів
 */
class TestPaymentScenariosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Створюємо тестові сценарії платежів...');

        // Отримуємо тестових користувачів
        $testUsers = $this->getTestUsers();
        $testCourses = $this->getTestCourses();

        if (empty($testUsers) || empty($testCourses)) {
            $this->command->error('Недостатньо даних для створення тестових сценаріїв.');
            return;
        }

        // Видаляємо існуючі тестові платежі щоб уникнути конфліктів
        $this->command->info('Очищуємо існуючі тестові платежі...');
        
        // Видаляємо платежі з ID > 100 (тестові)
        Payment::where('id', '>', 100)->delete();
        
        // Видаляємо відповідні підписки
        CourseEnrollment::where('payment_id', '>', 100)->delete();

        // Сценарій 1: Успішні покупки курсів
        $this->createSuccessfulPurchaseScenarios($testUsers, $testCourses);

        // Сценарій 2: Невдалі платежі
        $this->createFailedPaymentScenarios($testUsers, $testCourses);

        // Сценарій 3: Платежі в очікуванні
        $this->createPendingPaymentScenarios($testUsers, $testCourses);

        // Сценарій 4: Повернені платежі
        $this->createRefundedPaymentScenarios($testUsers, $testCourses);

        // Сценарій 5: Множинні покупки одним користувачем
        $this->createMultiplePurchaseScenarios($testUsers, $testCourses);

        // Сценарій 6: Платежі з різними методами оплати
        $this->createDifferentPaymentMethodScenarios($testUsers, $testCourses);

        $this->command->info('Тестові сценарії створено успішно!');
    }

    /**
     * Отримати тестових користувачів
     */
    private function getTestUsers(): array
    {
        $studentRole = Role::where('name', 'student')->first();
        $teacherRole = Role::where('name', 'teacher')->first();
        
        $users = [];
        
        if ($studentRole) {
            $students = User::where('role_id', $studentRole->id)->limit(5)->get();
            $users = array_merge($users, $students->toArray());
        }
        
        if ($teacherRole) {
            $teachers = User::where('role_id', $teacherRole->id)->limit(2)->get();
            $users = array_merge($users, $teachers->toArray());
        }
        
        return $users;
    }

    /**
     * Отримати тестові курси
     */
    private function getTestCourses(): array
    {
        return Course::where('price', '>', 0)
            ->where('is_published', true)
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Сценарій 1: Успішні покупки курсів
     */
    private function createSuccessfulPurchaseScenarios(array $users, array $courses): void
    {
        $this->command->info('Створюємо сценарій успішних покупок...');
        
        $usedCombinations = [];
        
        foreach (array_slice($users, 0, 3) as $user) {
            foreach (array_slice($courses, 0, 2) as $course) {
                $combinationKey = $user['id'] . '_' . $course['id'];
                
                // Перевіряємо, чи ця комбінація вже використана
                if (isset($usedCombinations[$combinationKey])) {
                    continue;
                }
                
                // Перевіряємо, чи вже існує підписка в базі
                $existingEnrollment = CourseEnrollment::where('user_id', $user['id'])
                    ->where('course_id', $course['id'])
                    ->exists();
                    
                if ($existingEnrollment) {
                    $this->command->info("Пропускаємо існуючу підписку: користувач {$user['id']}, курс {$course['id']}");
                    continue;
                }
                
                $payment = $this->createPayment([
                    'user_id' => $user['id'],
                    'entity_id' => $course['id'],
                    'amount' => $course['price'],
                    'payment_status' => 'completed',
                    'payment_method' => 'liqpay',
                    'transaction_id' => 'LQ' . date('Ymd') . rand(100000, 999999),
                    'created_at' => now()->subDays(rand(1, 30)),
                ]);

                // Створюємо підписку
                $this->createEnrollment($payment, $course);
                
                // Запам'ятовуємо комбінацію
                $usedCombinations[$combinationKey] = true;
            }
        }
    }

    /**
     * Сценарій 2: Невдалі платежі
     */
    private function createFailedPaymentScenarios(array $users, array $courses): void
    {
        $this->command->info('Створюємо сценарій невдалих платежів...');
        
        foreach (array_slice($users, 0, 2) as $user) {
            // Вибираємо курс, на який користувач ще не підписаний
            $availableCourses = array_filter($courses, function($course) use ($user) {
                return !CourseEnrollment::where('user_id', $user['id'])
                    ->where('course_id', $course['id'])
                    ->exists();
            });
            
            if (empty($availableCourses)) {
                continue;
            }
            
            $course = $availableCourses[array_rand($availableCourses)];
            
            $this->createPayment([
                'user_id' => $user['id'],
                'entity_id' => $course['id'],
                'amount' => $course['price'],
                'payment_status' => 'failed',
                'payment_method' => 'card',
                'transaction_id' => null,
                'created_at' => now()->subDays(rand(1, 7)),
            ]);
        }
    }

    /**
     * Сценарій 3: Платежі в очікуванні
     */
    private function createPendingPaymentScenarios(array $users, array $courses): void
    {
        $this->command->info('Створюємо сценарій платежів в очікуванні...');
        
        foreach (array_slice($users, 0, 2) as $user) {
            $course = $courses[array_rand($courses)];
            
            $this->createPayment([
                'user_id' => $user['id'],
                'entity_id' => $course['id'],
                'amount' => $course['price'],
                'payment_status' => 'pending',
                'payment_method' => 'bank_transfer',
                'transaction_id' => null,
                'created_at' => now()->subHours(rand(1, 24)),
            ]);
        }
    }

    /**
     * Сценарій 4: Повернені платежі
     */
    private function createRefundedPaymentScenarios(array $users, array $courses): void
    {
        $this->command->info('Створюємо сценарій повернених платежів...');
        
        $user = $users[0];
        $course = $courses[0];
        
        $payment = $this->createPayment([
            'user_id' => $user['id'],
            'entity_id' => $course['id'],
            'amount' => $course['price'],
            'payment_status' => 'refunded',
            'payment_method' => 'paypal',
            'transaction_id' => 'PP' . date('Ymd') . rand(100000, 999999),
            'created_at' => now()->subDays(rand(5, 15)),
        ]);

        // Створюємо підписку, але деактивуємо її
        $enrollment = $this->createEnrollment($payment, $course);
        $enrollment->is_active = false;
        $enrollment->save();
    }

    /**
     * Сценарій 5: Множинні покупки одним користувачем
     */
    private function createMultiplePurchaseScenarios(array $users, array $courses): void
    {
        $this->command->info('Створюємо сценарій множинних покупок...');
        
        $powerUser = $users[0]; // Активний користувач
        
        // Створюємо 5 покупок цього користувача за різні періоди
        $dates = [
            now()->subMonths(3),
            now()->subMonths(2),
            now()->subMonth(),
            now()->subWeeks(2),
            now()->subWeek(),
        ];
        
        $usedCourses = [];
        
        foreach ($dates as $index => $date) {
            // Вибираємо курс, який цей користувач ще не купував
            $availableCourses = array_filter($courses, function($course) use ($powerUser, $usedCourses) {
                if (in_array($course['id'], $usedCourses)) {
                    return false;
                }
                
                return !CourseEnrollment::where('user_id', $powerUser['id'])
                    ->where('course_id', $course['id'])
                    ->exists();
            });
            
            if (empty($availableCourses)) {
                $this->command->info("Немає доступних курсів для користувача {$powerUser['id']}");
                break;
            }
            
            $course = $availableCourses[array_rand($availableCourses)];
            $usedCourses[] = $course['id'];
            
            $payment = $this->createPayment([
                'user_id' => $powerUser['id'],
                'entity_id' => $course['id'],
                'amount' => $course['price'],
                'payment_status' => 'completed',
                'payment_method' => ['liqpay', 'card', 'paypal'][array_rand(['liqpay', 'card', 'paypal'])],
                'transaction_id' => 'MU' . $date->format('Ymd') . rand(100000, 999999),
                'created_at' => $date,
            ]);

            $this->createEnrollment($payment, $course);
        }
    }

    /**
     * Сценарій 6: Платежі з різними методами оплати
     */
    private function createDifferentPaymentMethodScenarios(array $users, array $courses): void
    {
        $this->command->info('Створюємо сценарій різних методів оплати...');
        
        $paymentMethods = ['liqpay', 'card', 'paypal', 'bank_transfer'];
        
        foreach ($paymentMethods as $index => $method) {
            $user = $users[$index % count($users)];
            $course = $courses[$index % count($courses)];
            
            $this->createPayment([
                'user_id' => $user['id'],
                'entity_id' => $course['id'],
                'amount' => $course['price'],
                'payment_status' => 'completed',
                'payment_method' => $method,
                'transaction_id' => strtoupper(substr($method, 0, 2)) . date('Ymd') . rand(100000, 999999),
                'created_at' => now()->subDays(rand(1, 10)),
            ]);
        }
    }

    /**
     * Створити платіж
     */
    private function createPayment(array $data): Payment
    {
        $defaultData = [
            'currency' => 'UAH',
            'entity_type' => 'course',
            'updated_at' => $data['created_at'] ?? now(),
        ];
        
        return Payment::create(array_merge($defaultData, $data));
    }

    /**
     * Створити підписку
     */
    private function createEnrollment(Payment $payment, array $course): CourseEnrollment
    {
        // Спочатку перевіряємо, чи вже існує підписка для цього користувача на цей курс
        $existingEnrollment = CourseEnrollment::where('user_id', $payment->user_id)
            ->where('course_id', $payment->entity_id)
            ->first();
            
        if ($existingEnrollment) {
            // Якщо підписка існує, оновлюємо її
            $existingEnrollment->update([
                'payment_id' => $payment->id,
                'enrollment_type' => 'purchase',
                'is_active' => true,
                'expires_at' => null,
            ]);
            
            return $existingEnrollment;
        }
        
        // Якщо підписки немає, створюємо нову
        return CourseEnrollment::create([
            'user_id' => $payment->user_id,
            'course_id' => $payment->entity_id,
            'enrolled_at' => $payment->created_at,
            'expires_at' => null, // Покупка дає безстроковий доступ
            'enrollment_type' => 'purchase',
            'payment_id' => $payment->id,
            'is_active' => true,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
        ]);
    }
}