<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'description' => 'Має повний доступ до всіх функцій системи',
            ],
            [
                'name' => 'admin',
                'description' => 'Адміністратор з обмеженими можливостями',
            ],
            [
                'name' => 'teacher',
                'description' => 'Викладач курсів',
            ],
            [
                'name' => 'student',
                'description' => 'Студент, який проходить курси',
            ],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}