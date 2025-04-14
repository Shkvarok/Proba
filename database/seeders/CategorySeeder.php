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
        // Перевіряємо, чи вже є категорії в базі даних
        $categoriesCount = DB::table('categories')->count();
        
        if ($categoriesCount > 0) {
            $this->command->info('Таблиця категорій вже містить дані. Пропускаємо наповнення.');
            return;
        }

        // Головні категорії
        $mainCategories = [
            [
                'name' => 'Програмування',
                'slug' => 'programming',
                'description' => 'Курси з програмування та розробки ПЗ',
                'icon' => 'code',
                'parent_id' => null,
                'position' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Дизайн',
                'slug' => 'design',
                'description' => 'Курси з графічного та веб-дизайну',
                'icon' => 'palette',
                'parent_id' => null,
                'position' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Бізнес',
                'slug' => 'business',
                'description' => 'Курси з бізнесу, підприємництва та маркетингу',
                'icon' => 'briefcase',
                'parent_id' => null,
                'position' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Мови',
                'slug' => 'languages',
                'description' => 'Курси з вивчення іноземних мов',
                'icon' => 'language',
                'parent_id' => null,
                'position' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Вставляємо головні категорії
        DB::table('categories')->insert($mainCategories);

        // Отримуємо ID головних категорій
        $programming = DB::table('categories')->where('slug', 'programming')->first()->id;
        $design = DB::table('categories')->where('slug', 'design')->first()->id;
        $business = DB::table('categories')->where('slug', 'business')->first()->id;
        $languages = DB::table('categories')->where('slug', 'languages')->first()->id;

        // Підкатегорії для програмування
        $programmingSubcategories = [
            [
                'name' => 'Веб-розробка',
                'slug' => 'web-development',
                'description' => 'Створення веб-сайтів та веб-додатків',
                'icon' => 'globe',
                'parent_id' => $programming,
                'position' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Мобільна розробка',
                'slug' => 'mobile-development',
                'description' => 'Розробка додатків для iOS та Android',
                'icon' => 'smartphone',
                'parent_id' => $programming,
                'position' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Бази даних',
                'slug' => 'databases',
                'description' => 'Робота з базами даних та SQL',
                'icon' => 'database',
                'parent_id' => $programming,
                'position' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Підкатегорії для дизайну
        $designSubcategories = [
            [
                'name' => 'Графічний дизайн',
                'slug' => 'graphic-design',
                'description' => 'Створення візуальних матеріалів',
                'icon' => 'image',
                'parent_id' => $design,
                'position' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UI/UX Дизайн',
                'slug' => 'ui-ux-design',
                'description' => 'Дизайн інтерфейсів та користувацького досвіду',
                'icon' => 'layout',
                'parent_id' => $design,
                'position' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Підкатегорії для бізнесу
        $businessSubcategories = [
            [
                'name' => 'Маркетинг',
                'slug' => 'marketing',
                'description' => 'Стратегії просування та реклами',
                'icon' => 'trending-up',
                'parent_id' => $business,
                'position' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Фінанси',
                'slug' => 'finance',
                'description' => 'Управління фінансами та інвестиції',
                'icon' => 'dollar-sign',
                'parent_id' => $business,
                'position' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Підприємництво',
                'slug' => 'entrepreneurship',
                'description' => 'Створення та розвиток власного бізнесу',
                'icon' => 'target',
                'parent_id' => $business,
                'position' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Підкатегорії для мов
        $languagesSubcategories = [
            [
                'name' => 'Англійська',
                'slug' => 'english',
                'description' => 'Вивчення англійської мови',
                'icon' => 'globe',
                'parent_id' => $languages,
                'position' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Німецька',
                'slug' => 'german',
                'description' => 'Вивчення німецької мови',
                'icon' => 'globe',
                'parent_id' => $languages,
                'position' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Польська',
                'slug' => 'polish',
                'description' => 'Вивчення польської мови',
                'icon' => 'globe',
                'parent_id' => $languages,
                'position' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Вставляємо всі підкатегорії
        DB::table('categories')->insert(array_merge(
            $programmingSubcategories,
            $designSubcategories,
            $businessSubcategories,
            $languagesSubcategories
        ));

        $this->command->info('Наповнення таблиці категорій завершено.');
    }
}