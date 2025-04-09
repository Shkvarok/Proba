<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Отримуємо всі ролі
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $adminRole = Role::where('name', 'admin')->first();
        $teacherRole = Role::where('name', 'teacher')->first();
        $studentRole = Role::where('name', 'student')->first();
        
        // Отримуємо всі дозволи
        $permissions = Permission::all();
        
        // Призначаємо дозволи ролям
        
        // Super Admin отримує всі дозволи
        $superAdminRole->permissions()->sync($permissions->pluck('id')->toArray());
        
        // Admin отримує всі дозволи, крім системних налаштувань і управління ролями
        $adminPermissions = $permissions->filter(function ($permission) {
            return !in_array($permission->slug, ['system-settings', 'manage-roles']);
        });
        $adminRole->permissions()->sync($adminPermissions->pluck('id')->toArray());
        
        // Teacher отримує дозволи для роботи з курсами і перегляду користувачів
        $teacherPermissions = $permissions->filter(function ($permission) {
            return in_array($permission->slug, [
                'view-users', 'view-courses', 'create-courses', 'edit-courses'
            ]);
        });
        $teacherRole->permissions()->sync($teacherPermissions->pluck('id')->toArray());
        
        // Student отримує дозвіл лише на перегляд курсів
        $studentPermissions = $permissions->filter(function ($permission) {
            return in_array($permission->slug, ['view-courses']);
        });
        $studentRole->permissions()->sync($studentPermissions->pluck('id')->toArray());
    }
}