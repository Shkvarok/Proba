<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\Level;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Перевіряємо наявність необхідних даних
        $categoriesCount = Category::count();
        $levelsCount = Level::count();
        $usersCount = User::count();

        if ($categoriesCount === 0) {
            $this->command->error('Категорії відсутні в базі даних. Спочатку запустіть CategorySeeder.');
            return;
        }

        if ($levelsCount === 0) {
            $this->command->error('Рівні відсутні в базі даних. Спочатку запустіть LevelSeeder або накатіть міграції з вихідними даними.');
            return;
        }

        if ($usersCount === 0) {
            $this->command->error('Користувачі відсутні в базі даних. Спочатку запустіть UserSeeder.');
            return;
        }

        // Отримуємо ID категорій, рівнів та користувачів для використання в курсах
        $categoryIds = Category::pluck('id')->toArray();
        $levelIds = Level::pluck('id')->toArray();
        $instructorIds = User::inRandomOrder()->limit(5)->pluck('id')->toArray();

        // Створимо 20 тестових курсів
        $courses = [];
        $now = now();

        $languages = ['українська', 'англійська', 'польська', 'німецька'];
        $requirements = [
            'Базові знання комп\'ютера',
            'Доступ до Інтернету',
            'Встановлений текстовий редактор',
            'Базові знання програмування',
            'Базові знання математики',
            'Відсутні попередні вимоги'
        ];

        $whatYouLearns = [
            'Основи програмування', 
            'Розробка веб-сайтів', 
            'Робота з даними', 
            'Створення мобільних додатків',
            'Розробка UI/UX',
            'Робота з клієнтами',
            'Управління проєктами',
            'Аналіз даних та візуалізація'
        ];

        for ($i = 0; $i < 20; $i++) {
            $title = 'Курс ' . ($i + 1) . ': ' . Str::random(20);
            $description = 'Це опис курсу ' . ($i + 1) . '. ' . Str::random(200);
            $price = rand(0, 9999);
            $hasDiscount = (bool)rand(0, 1);
            $discountPrice = $hasDiscount ? $price * (rand(50, 90) / 100) : null;
            $discountExpiresAt = $hasDiscount ? $now->copy()->addDays(rand(5, 30)) : null;
            $isPublished = (bool)rand(0, 1);
            $language = $languages[array_rand($languages)];
            
            $categoryId = $categoryIds[array_rand($categoryIds)];
            $levelId = $levelIds[array_rand($levelIds)];
            $instructorId = $instructorIds[array_rand($instructorIds)];

            $randomRequirements = [];
            for ($j = 0; $j < rand(1, 3); $j++) {
                $randomRequirements[] = $requirements[array_rand($requirements)];
            }
            $requirementsText = implode("\n", array_unique($randomRequirements));

            $randomLearn = [];
            for ($j = 0; $j < rand(3, 6); $j++) {
                $randomLearn[] = $whatYouLearns[array_rand($whatYouLearns)];
            }
            $learnText = implode("\n", array_unique($randomLearn));

            $courses[] = [
                'title' => $title,
                'description' => $description,
                'category_id' => $categoryId,
                'instructor_id' => $instructorId,
                'price' => $price,
                'discount_price' => $discountPrice,
                'discount_expires_at' => $discountExpiresAt,
                'level_id' => $levelId,
                'language' => $language,
                'cover_image' => null, // Залишаємо null, оскільки це було б файлове завантаження
                'promo_video_url' => 'https://www.youtube.com/watch?v=' . Str::random(11),
                'requirements' => $requirementsText,
                'what_you_learn' => $learnText,
                'is_published' => $isPublished,
                'meta_title' => $title,
                'meta_description' => Str::limit($description, 160),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        // Вставляємо курси в базу даних
        DB::table('courses')->insert($courses);

        $this->command->info('Додано 20 тестових курсів до бази даних.');
    }
}