<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SafeDatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Початок безпечного заповнення бази даних...');
        
        // Вимикаємо перевірку foreign key constraints для MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
        
        try {
            $this->call([
                // 1. Базові довідники та ролі
                RoleSeeder::class,
                CountrySeeder::class,
                LevelSeeder::class,
                CategorySeeder::class,
                
                // 2. Користувачі
                UserSeeder::class,
                
                // 3. Курси та навчальний контент
                CourseSeeder::class,
                ModuleSeeder::class,
                LessonSeeder::class,
                InternalTestSeeder::class,
                
                // 4. Підписки (тільки безкоштовні)
                CourseEnrollmentSeeder::class,
                
                // 5. Платежі (створюють платні підписки)
                PaymentSeeder::class,
                TestPaymentScenariosSeeder::class,
            ]);
            
        } finally {
            // Включаємо назад перевірку foreign key constraints
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }
        
        $this->command->info('✅ База даних безпечно заповнена!');
        $this->outputSummary();
    }
    
    /**
     * Вивести підсумкову статистику
     */
    private function outputSummary(): void
    {
        $this->command->info('');
        $this->command->info('📊 ПІДСУМКОВА СТАТИСТИКА:');
        $this->command->info('');

        // Користувачі
        $totalUsers = \App\Models\User::count();
        $this->command->info("👥 Користувачі: {$totalUsers}");

        // Курси
        $totalCourses = \App\Models\Course::count();
        $publishedCourses = \App\Models\Course::where('is_published', true)->count();
        $this->command->info("📚 Курси: {$totalCourses} (опубліковано: {$publishedCourses})");

        // Підписки
        $totalEnrollments = \App\Models\CourseEnrollment::count();
        $freeEnrollments = \App\Models\CourseEnrollment::whereNull('payment_id')->count();
        $paidEnrollments = \App\Models\CourseEnrollment::whereNotNull('payment_id')->count();
        
        $this->command->info("🎓 Підписки: {$totalEnrollments}");
        $this->command->info("   - Безкоштовні: {$freeEnrollments}");
        $this->command->info("   - Платні: {$paidEnrollments}");

        // Платежі
        $totalPayments = \App\Models\Payment::count();
        $completedPayments = \App\Models\Payment::where('payment_status', 'completed')->count();
        $totalRevenue = \App\Models\Payment::where('payment_status', 'completed')->sum('amount');
        
        $this->command->info("💰 Платежі: {$totalPayments}");
        $this->command->info("   - Успішні: {$completedPayments}");
        $this->command->info("   - Дохід: " . number_format($totalRevenue, 2) . " UAH");
        
        $this->command->info('');
        $this->command->info("🧪 ТЕСТОВІ АКАУНТИ (пароль: password):");
        $this->command->info("   - superadmin@example.com (Супер-адмін)");
        $this->command->info("   - admin@example.com (Адмін)");
        $this->command->info("   - teacher1@example.com (Викладач)");
        $this->command->info("   - student1@example.com (Студент)");
        $this->command->info('');
        $this->command->info("🚀 Платформа готова до роботи!");
    }
}