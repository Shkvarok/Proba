<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SafePaymentSeeder - безпечне створення платежів без дублікатів підписок
 */
class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔒 Безпечне створення платежів...');
        
        // Отримуємо необхідні дані
        $courses = Course::where('price', '>', 0)->get();
        $studentRole = Role::where('name', 'student')->first();
        $teacherRole = Role::where('name', 'teacher')->first();
        
        if ($courses->isEmpty()) {
            $this->command->error('Платні курси відсутні в базі даних.');
            return;
        }

        $users = collect();
        
        if ($studentRole) {
            $students = User::where('role_id', $studentRole->id)->get();
            $users = $users->merge($students);
        }
        
        if ($teacherRole) {
            $teachers = User::where('role_id', $teacherRole->id)->get();
            $users = $users->merge($teachers);
        }

        if ($users->isEmpty()) {
            $this->command->error('Користувачі для платежів відсутні в базі даних.');
            return;
        }

        // Створюємо унікальні комбінації користувач-курс
        $uniqueCombinations = $this->generateUniqueCombinations($users, $courses, 100);
        
        $this->command->info("Створюємо {$uniqueCombinations->count()} унікальних платежів...");

        $payments = [];
        $now = now();
        $startDate = $now->copy()->subMonths(6);

        foreach ($uniqueCombinations as $combination) {
            $paymentDate = $this->getRandomPaymentDate($startDate, $now);
            $status = $this->getRandomPaymentStatus();
            $paymentMethod = $this->getRandomPaymentMethod();
            $amount = $this->calculatePaymentAmount($combination['course'], $paymentDate);
            
            $transactionId = null;
            if ($status === 'completed') {
                $transactionId = $this->generateTransactionId($paymentMethod, $paymentDate);
            }

            $payments[] = [
                'user_id' => $combination['user']->id,
                'amount' => $amount,
                'currency' => 'UAH',
                'payment_method' => $paymentMethod,
                'payment_status' => $status,
                'transaction_id' => $transactionId,
                'entity_type' => 'course',
                'entity_id' => $combination['course']->id,
                'created_at' => $paymentDate,
                'updated_at' => $paymentDate,
            ];
        }

        // Вставляємо платежі
        $this->command->info('Зберігаємо платежі в базу даних...');
        $chunks = array_chunk($payments, 50);
        foreach ($chunks as $chunk) {
            DB::table('payments')->insert($chunk);
        }

        // Створюємо підписки для успішних платежів
        $this->createEnrollmentsForSuccessfulPayments();

        $this->command->info('Створено ' . count($payments) . ' платежів.');
        $this->outputStatistics();
    }

    /**
     * Генерувати унікальні комбінації користувач-курс
     */
    private function generateUniqueCombinations($users, $courses, int $maxCount)
    {
        // Отримуємо існуючі комбінації
        $existingCombinations = DB::table('course_enrollments')
            ->select('user_id', 'course_id')
            ->get()
            ->map(function($item) {
                return $item->user_id . '_' . $item->course_id;
            })
            ->flip()
            ->toArray();

        $combinations = collect();
        $attempts = 0;
        $maxAttempts = $maxCount * 3; // Обмежуємо кількість спроб

        while ($combinations->count() < $maxCount && $attempts < $maxAttempts) {
            $user = $users->random();
            $course = $courses->random();
            $key = $user->id . '_' . $course->id;

            // Перевіряємо, чи ця комбінація вже існує
            if (!isset($existingCombinations[$key]) && !$combinations->has($key)) {
                $combinations->put($key, [
                    'user' => $user,
                    'course' => $course
                ]);
                
                // Додаємо до існуючих, щоб уникнути дублікатів в межах цього сідера
                $existingCombinations[$key] = true;
            }
            
            $attempts++;
        }

        $this->command->info("Згенеровано {$combinations->count()} унікальних комбінацій з {$attempts} спроб.");
        
        return $combinations;
    }

    /**
     * Створити підписки для успішних платежів використовуючи INSERT IGNORE
     */
    private function createEnrollmentsForSuccessfulPayments(): void
    {
        $this->command->info('Створюємо підписки для успішних платежів...');
        
        $successfulPayments = Payment::where('payment_status', 'completed')->get();
        
        foreach ($successfulPayments as $payment) {
            // Використовуємо INSERT IGNORE для безпечної вставки
            DB::statement("
                INSERT IGNORE INTO course_enrollments 
                (user_id, course_id, enrolled_at, expires_at, enrollment_type, payment_id, is_active, created_at, updated_at)
                VALUES (?, ?, ?, NULL, 'purchase', ?, 1, ?, ?)
            ", [
                $payment->user_id,
                $payment->entity_id,
                $payment->created_at,
                $payment->id,
                $payment->created_at,
                $payment->created_at
            ]);
        }
        
        $enrollmentsCount = CourseEnrollment::whereNotNull('payment_id')->count();
        $this->command->info("Створено/оновлено {$enrollmentsCount} підписок.");
    }

    // Копіюємо допоміжні методи з основного PaymentSeeder
    private function getRandomPaymentDate(Carbon $startDate, Carbon $endDate): Carbon
    {
        $randomTimestamp = rand($startDate->timestamp, $endDate->timestamp);
        return Carbon::createFromTimestamp($randomTimestamp);
    }

    private function getRandomPaymentStatus(): string
    {
        $rand = rand(1, 100);
        
        if ($rand <= 85) {
            return 'completed';
        } elseif ($rand <= 92) {
            return 'failed';
        } elseif ($rand <= 97) {
            return 'pending';
        } else {
            return 'refunded';
        }
    }

    private function getRandomPaymentMethod(): string
    {
        $methods = ['liqpay', 'card', 'paypal', 'bank_transfer'];
        $weights = [50, 30, 15, 5];
        
        $totalWeight = array_sum($weights);
        $random = rand(1, $totalWeight);
        
        $currentWeight = 0;
        for ($i = 0; $i < count($methods); $i++) {
            $currentWeight += $weights[$i];
            if ($random <= $currentWeight) {
                return $methods[$i];
            }
        }
        
        return $methods[0];
    }

    private function calculatePaymentAmount(Course $course, Carbon $paymentDate): float
    {
        $basePrice = $course->price;
        
        if ($course->discount_price && 
            ($course->discount_expires_at === null || $paymentDate->lte($course->discount_expires_at))) {
            return $course->discount_price;
        }
        
        if (rand(1, 100) <= 15) {
            $discountPercent = rand(10, 30);
            return $basePrice * (1 - $discountPercent / 100);
        }
        
        return $basePrice;
    }

    private function generateTransactionId(string $paymentMethod, Carbon $paymentDate): string
    {
        $prefix = [
            'liqpay' => 'LQ',
            'card' => 'CD',
            'paypal' => 'PP',
            'bank_transfer' => 'BT'
        ][$paymentMethod] ?? 'TX';
        
        return $prefix . $paymentDate->format('Ymd') . rand(100000, 999999);
    }

    private function outputStatistics(): void
    {
        $total = Payment::count();
        $byStatus = Payment::select('payment_status', DB::raw('count(*) as count'))
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status')
            ->toArray();
            
        $totalRevenue = Payment::where('payment_status', 'completed')->sum('amount');

        $this->command->info("\n=== СТАТИСТИКА ПЛАТЕЖІВ ===");
        $this->command->info("Загальна кількість: {$total}");
        
        foreach ($byStatus as $status => $count) {
            $this->command->info("  {$status}: {$count}");
        }
        
        $this->command->info("Загальний дохід: " . number_format($totalRevenue, 2) . " UAH");
    }
}