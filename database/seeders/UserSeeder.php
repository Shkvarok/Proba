<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Отримуємо ролі
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $adminRole = Role::where('name', 'admin')->first();
        $teacherRole = Role::where('name', 'teacher')->first();
        $studentRole = Role::where('name', 'student')->first();
        
        // Створюємо Super Admin користувача
        User::create([
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'role_id' => $superAdminRole->id,
            'email_verified_at' => now(),
        ]);
        
        // Створюємо Admin користувача
        User::create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role_id' => $adminRole->id,
            'email_verified_at' => now(),
        ]);
        
        // Створюємо Teacher користувачів
        User::create([
            'email' => 'teacher1@example.com',
            'password' => Hash::make('password'),
            'first_name' => 'Іван',
            'last_name' => 'Петренко',
            'role_id' => $teacherRole->id,
            'email_verified_at' => now(),
        ]);
        
        User::create([
            'email' => 'teacher2@example.com',
            'password' => Hash::make('password'),
            'first_name' => 'Олена',
            'last_name' => 'Коваленко',
            'role_id' => $teacherRole->id,
            'email_verified_at' => now(),
        ]);
        
        // Створюємо Student користувачів
        for ($i = 1; $i <= 5; $i++) {
            User::create([
                'email' => "student{$i}@example.com",
                'password' => Hash::make('password'),
                'first_name' => "Студент{$i}",
                'last_name' => "Прізвище{$i}",
                'role_id' => $studentRole->id,
                'email_verified_at' => now(),
            ]);
        }
    }
}