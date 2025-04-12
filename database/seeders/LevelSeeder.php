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
        DB::table('levels')->insert([
            ['code' => 'beginner', 'name' => 'Початковий', 'description' => 'Для новачків без попереднього досвіду'],
            ['code' => 'intermediate', 'name' => 'Середній', 'description' => 'Для тих, хто має базові знання'],
            ['code' => 'advanced', 'name' => 'Просунутий', 'description' => 'Для досвідчених користувачів'],
            ['code' => 'all-levels', 'name' => 'Всі рівні', 'description' => 'Підходить для будь-якого рівня знань'],
        ]);
    }
}
