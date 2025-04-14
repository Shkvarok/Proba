<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Перевіряємо, чи вже є категорії в базі даних
        $permissionsCount = DB::table('permissions')->count();
        
        if ($permissionsCount > 0) {
            $this->command->info('Таблиця категорій вже містить дані. Пропускаємо наповнення.');
            return;
        }
        $permissions = [
            // Дозволи для управління користувачами
            [
                'name' => 'Перегляд користувачів',
                'slug' => 'view-users',
                'description' => 'Дозволяє переглядати список користувачів'
            ],
            [
                'name' => 'Створення користувачів',
                'slug' => 'create-users',
                'description' => 'Дозволяє створювати нових користувачів'
            ],
            [
                'name' => 'Редагування користувачів',
                'slug' => 'edit-users',
                'description' => 'Дозволяє редагувати інформацію про користувачів'
            ],
            [
                'name' => 'Видалення користувачів',
                'slug' => 'delete-users',
                'description' => 'Дозволяє видаляти користувачів'
            ],
            
            // Дозволи для управління курсами
            [
                'name' => 'Перегляд курсів',
                'slug' => 'view-courses',
                'description' => 'Дозволяє переглядати список курсів'
            ],
            [
                'name' => 'Створення курсів',
                'slug' => 'create-courses',
                'description' => 'Дозволяє створювати нові курси'
            ],
            [
                'name' => 'Редагування курсів',
                'slug' => 'edit-courses',
                'description' => 'Дозволяє редагувати інформацію про курси'
            ],
            [
                'name' => 'Видалення курсів',
                'slug' => 'delete-courses',
                'description' => 'Дозволяє видаляти курси'
            ],
            
            // Дозволи для управління ролями
            [
                'name' => 'Управління ролями',
                'slug' => 'manage-roles',
                'description' => 'Дозволяє управляти ролями та їх дозволами'
            ],
            
            // Дозволи для управління системою
            [
                'name' => 'Доступ до системних налаштувань',
                'slug' => 'system-settings',
                'description' => 'Дозволяє змінювати налаштування системи'
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}