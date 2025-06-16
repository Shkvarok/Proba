<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Отримаємо кілька користувачів і курсів для тестових оплат
        $userIds = DB::table('users')->pluck('id')->toArray();
        $courseIds = DB::table('courses')->pluck('id')->toArray();

        if (empty($userIds) || empty($courseIds)) {
            Log::warning('PaymentSeeder: No users or courses found. Skipping payments seeding.');
            return;
        }

        $statuses = ['pending', 'completed', 'failed', 'refunded'];
        $methods = ['liqpay', 'manual', 'test'];
        $currency = 'UAH';

        $payments = [];
        for ($i = 0; $i < 30; $i++) {
            $userId = $userIds[array_rand($userIds)];
            $courseId = $courseIds[array_rand($courseIds)];
            $amount = rand(100, 2000);
            $discount = rand(0, 200);
            $original = $amount + $discount;
            $status = $statuses[array_rand($statuses)];
            $method = $methods[array_rand($methods)];
            $now = Carbon::now()->subDays(rand(0, 60));

            $payments[] = [
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $method,
                'payment_status' => $status,
                'transaction_id' => Str::uuid(),
                'entity_type' => 'course',
                'entity_id' => $courseId,
                'discount_amount' => $discount,
                'original_amount' => $original,
                'promo_code_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('payments')->insert($payments);
    }
} 