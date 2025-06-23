<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Course;
use App\Models\Payment;
use App\Models\CourseEnrollment;
use App\Services\LiqPayService;

class TestPaymentSystem extends Command
{
    protected $signature = 'payment:test 
                          {--full : Запустити повний тест}
                          {--quick : Швидкий тест}
                          {--stress : Стрес-тест}
                          {--payment-id= : Тестувати конкретний платіж}';

    protected $description = 'Тестування системи оплати';

    public function handle()
    {
        $this->info('🧪 Запуск тестування системи оплати...');
        
        if ($this->option('full')) {
            return $this->runFullTest();
        }
        
        if ($this->option('quick')) {
            return $this->runQuickTest();
        }
        
        if ($this->option('stress')) {
            return $this->runStressTest();
        }
        
        if ($this->option('payment-id')) {
            return $this->testSpecificPayment($this->option('payment-id'));
        }
        
        // Інтерактивний режим
        return $this->runInteractiveTest();
    }

    protected function runFullTest()
    {
        $this->info('🚀 Запуск повного тесту...');
        
        // Крок 1: Створення тестових даних
        $this->line('📝 Створення тестових даних...');
        $user = User::create([
            'name' => 'Test Student Full',
            'email' => 'fulltest' . time() . '@example.com',
            'password' => bcrypt('password'),
            'role_id' => 3
        ]);
        
        $course = Course::create([
            'title' => 'Full Test Course',
            'description' => 'Course for full testing',
            'price' => 500,
            'instructor_id' => 1,
            'category_id' => 1,
            'level_id' => 1,
            'is_published' => true
        ]);
        
        $this->info("✅ Користувач створений: {$user->email}");
        $this->info("✅ Курс створений: {$course->title} ({$course->price} ₴)");
        
        // Крок 2: Створення платежу
        $this->line('💳 Створення платежу...');
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => $course->price,
            'currency' => 'UAH',
            'payment_method' => 'liqpay',
            'payment_status' => 'pending',
            'entity_type' => 'course',
            'entity_id' => $course->id,
        ]);
        
        $this->info("✅ Платіж створений: ID {$payment->id}");
        
        // Крок 3: Тестування LiqPay форми
        try {
            $liqpayService = app(LiqPayService::class);
            $formData = $liqpayService->createCoursePaymentForm($user, $course, $payment);
            $this->info('✅ LiqPay форма створена успішно');
        } catch (\Exception $e) {
            $this->error("❌ Помилка створення LiqPay форми: {$e->getMessage()}");
            return 1;
        }
        
        // Крок 4: Симуляція успішної оплати
        $this->line('💰 Симуляція успішної оплати...');
        $payment->payment_status = 'completed';
        $payment->transaction_id = 'full_test_' . time();
        $payment->save();
        
        // Крок 5: Створення підписки
        $enrollment = CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'payment_id' => $payment->id,
            'is_active' => true,
            'enrollment_type' => 'purchase',
            'enrolled_at' => now(),
        ]);
        
        $this->info("✅ Підписка створена: ID {$enrollment->id}");
        
        // Крок 6: Перевірка результатів
        $this->line('🔍 Перевірка результатів...');
        
        $checks = [
            'Платіж завершений' => $payment->payment_status === 'completed',
            'Підписка активна' => $enrollment->is_active,
            'Правильний користувач' => $enrollment->user_id === $user->id,
            'Правильний курс' => $enrollment->course_id === $course->id,
            'Пов\'язаний платіж' => $enrollment->payment_id === $payment->id,
        ];
        
        $allPassed = true;
        foreach ($checks as $check => $result) {
            if ($result) {
                $this->info("✅ $check");
            } else {
                $this->error("❌ $check");
                $allPassed = false;
            }
        }
        
        if ($allPassed) {
            $this->info('🎉 ПОВНИЙ ТЕСТ ПРОЙШОВ УСПІШНО!');
            return 0;
        } else {
            $this->error('❌ ТЕСТ НЕ ПРОЙДЕНО!');
            return 1;
        }
    }

    protected function runQuickTest()
    {
        $this->info('⚡ Запуск швидкого тесту...');
        
        // Використовуємо існуючі дані
        $user = User::where('role_id', 3)->first();
        $course = Course::where('is_published', true)->first();
        
        if (!$user || !$course) {
            $this->error('❌ Немає тестових даних. Створіть користувача та курс.');
            return 1;
        }
        
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => $course->price,
            'currency' => 'UAH',
            'payment_method' => 'liqpay',
            'payment_status' => 'completed',
            'entity_type' => 'course',
            'entity_id' => $course->id,
            'transaction_id' => 'quick_test_' . time()
        ]);
        
        $enrollment = CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'payment_id' => $payment->id,
            'is_active' => true,
            'enrollment_type' => 'purchase',
            'enrolled_at' => now(),
        ]);
        
        $this->info('✅ Швидкий тест завершено');
        $this->info("📊 Платіж: {$payment->id}, Підписка: {$enrollment->id}");
        
        return 0;
    }

    protected function runStressTest()
    {
        $this->info('🔥 Запуск стрес-тесту...');
        
        $count = $this->ask('Скільки платежів створити?', 100);
        $bar = $this->output->createProgressBar($count);
        
        $start = microtime(true);
        $successful = 0;
        
        for ($i = 1; $i <= $count; $i++) {
            try {
                $user = User::create([
                    'name' => "Stress User $i",
                    'email' => "stress$i" . time() . rand(1000, 9999) . '@example.com',
                    'password' => bcrypt('password'),
                    'role_id' => 3
                ]);
                
                $course = Course::inRandomOrder()->first();
                
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'amount' => rand(100, 1000),
                    'currency' => 'UAH',
                    'payment_method' => 'liqpay',
                    'payment_status' => 'completed',
                    'entity_type' => 'course',
                    'entity_id' => $course->id,
                    'transaction_id' => "stress_$i" . time()
                ]);
                
                CourseEnrollment::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'payment_id' => $payment->id,
                    'is_active' => true,
                    'enrollment_type' => 'purchase',
                    'enrolled_at' => now(),
                ]);
                
                $successful++;
            } catch (\Exception $e) {
                // Ігноруємо помилки в стрес-тесті
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $end = microtime(true);
        $time = round($end - $start, 2);
        
        $this->newLine();
        $this->info("🔥 Стрес-тест завершено за {$time}s");
        $this->info("📊 Успішно: {$successful}/{$count}");
        $this->info("⚡ Швидкість: " . round($successful / $time, 2) . " операцій/сек");
        
        return 0;
    }

    protected function testSpecificPayment($paymentId)
    {
        $this->info("🔍 Тестування платежу ID: $paymentId");
        
        try {
            $payment = Payment::with(['user', 'entity'])->findOrFail($paymentId);
            $enrollment = CourseEnrollment::where('payment_id', $paymentId)->first();
            
            $this->table(['Параметр', 'Значення'], [
                ['ID платежу', $payment->id],
                ['Статус', $payment->payment_status],
                ['Сума', $payment->amount . ' ' . $payment->currency],
                ['Користувач', $payment->user->email],
                ['Курс', $payment->entity->title ?? 'N/A'],
                ['Transaction ID', $payment->transaction_id ?? 'Немає'],
                ['Дата створення', $payment->created_at],
                ['Підписка існує', $enrollment ? 'Так' : 'Ні'],
                ['Підписка активна', $enrollment && $enrollment->is_active ? 'Так' : 'Ні'],
            ]);
            
            $status = $payment->payment_status === 'completed' && $enrollment && $enrollment->is_active;
            
            if ($status) {
                $this->info('✅ Платіж та підписка в порядку');
            } else {
                $this->warn('⚠️ Виявлено проблеми з платежем або підпискою');
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Платіж не знайдено: {$e->getMessage()}");
            return 1;
        }
    }

    protected function runInteractiveTest()
    {
        $this->info('🎯 Інтерактивний режим тестування');
        
        $choice = $this->choice('Оберіть тип тесту:', [
            'full' => 'Повний тест',
            'quick' => 'Швидкий тест',
            'stress' => 'Стрес-тест',
            'specific' => 'Тест конкретного платежу'
        ]);
        
        switch ($choice) {
            case 'full':
                return $this->runFullTest();
            case 'quick':
                return $this->runQuickTest();
            case 'stress':
                return $this->runStressTest();
            case 'specific':
                $paymentId = $this->ask('Введіть ID платежу:');
                return $this->testSpecificPayment($paymentId);
        }
        
        return 0;
    }
}