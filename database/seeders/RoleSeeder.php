<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Перевіряємо, чи вже є ролі в базі даних
        $rolesCount = DB::table('roles')->count();
        
        if ($rolesCount > 0) {
            $this->command->info('Таблиця ролей вже містить дані. Пропускаємо наповнення.');
            return;
        }

        // Основні ролі
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Супер Адміністратор',
                'description' => 'Повний доступ до всієї системи',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'admin',
                'display_name' => 'Адміністратор',
                'description' => 'Управління контентом та користувачами',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'instructor',
                'display_name' => 'Інструктор',
                'description' => 'Створення та управління курсами',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'user',
                'display_name' => 'Користувач',
                'description' => 'Стандартний користувач системи',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Вставляємо ролі
        DB::table('roles')->insert($roles);

        $this->command->info('Наповнення таблиці ролей завершено.');
    }
}