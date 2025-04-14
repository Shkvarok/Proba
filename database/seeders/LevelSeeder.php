<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Перевіряємо, чи вже є рівні в базі даних
        $levelsCount = DB::table('levels')->count();
        
        if ($levelsCount > 0) {
            $this->command->info('Таблиця рівнів вже містить дані. Пропускаємо наповнення.');
            return;
        }

        // Рівні складності
        $levels = [
            [
                'code' => 'beginner',
                'name' => 'Початковий',
                'description' => 'Для новачків без попереднього досвіду',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'intermediate',
                'name' => 'Середній',
                'description' => 'Для тих, хто має базові знання',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'advanced',
                'name' => 'Просунутий',
                'description' => 'Для досвідчених користувачів',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'all-levels',
                'name' => 'Всі рівні',
                'description' => 'Підходить для будь-якого рівня знань',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Вставляємо рівні в базу даних
        DB::table('levels')->insert($levels);

        $this->command->info('Наповнення таблиці рівнів завершено.');
    }
}