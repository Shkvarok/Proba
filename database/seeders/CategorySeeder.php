<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Основні категорії
        DB::table('categories')->insert([
            [
                'name' => 'Програмування',
                'slug' => Str::slug('Програмування'),
                'description' => 'Курси з програмування',
                'icon' => 'code-icon.svg',
                'parent_id' => null,
                'position' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Дизайн',
                'slug' => Str::slug('Дизайн'),
                'description' => 'Курси з дизайну',
                'icon' => 'design-icon.svg',
                'parent_id' => null,
                'position' => 2,
                'is_active' => true,
            ],
        ]);

        // Підкатегорії
        $programmingId = DB::table('categories')->where('slug', Str::slug('Програмування'))->value('id');
        $designId = DB::table('categories')->where('slug', Str::slug('Дизайн'))->value('id');

        DB::table('categories')->insert([
            [
                'name' => 'Frontend',
                'slug' => Str::slug('Frontend'),
                'description' => 'HTML, CSS, JavaScript',
                'icon' => 'frontend-icon.svg',
                'parent_id' => $programmingId,
                'position' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'UX/UI',
                'slug' => Str::slug('UX/UI'),
                'description' => 'Користувацький досвід та інтерфейси',
                'icon' => 'uxui-icon.svg',
                'parent_id' => $designId,
                'position' => 1,
                'is_active' => true,
            ],
        ]);
    }
}
