<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            CountrySeeder::class,
            StatusSeeder::class,
            UserSeeder::class,
            LevelSeeder::class,
            CategorySeeder::class,
            CourseSeeder::class,

        ]);
    }
}

