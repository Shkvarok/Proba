<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Перевіряємо, чи є курси в базі даних
        $coursesCount = Course::count();
        
        if ($coursesCount === 0) {
            $this->command->info('Немає курсів для наповнення модулями. Спершу виконайте CourseSeeder.');
            return;
        }
        
        // Отримуємо всі курси
        $courses = Course::all();
        
        foreach ($courses as $course) {
            // Створюємо випадкову кількість модулів для кожного курсу (від 3 до 8)
            $modulesCount = rand(3, 8);
            
            for ($i = 1; $i <= $modulesCount; $i++) {
                Module::create([
                    'course_id' => $course->id,
                    'title' => $this->getRandomModuleTitle(),
                    'position' => $i,
                ]);
            }
        }
        
        $this->command->info('Модулі успішно створені для всіх курсів.');
    }
    
    /**
     * Отримати випадкову назву модуля
     */
    private function getRandomModuleTitle(): string
    {
        $titles = [
            'Вступ до курсу',
            'Основні концепції',
            'Практичні навички',
            'Розширені техніки',
            'Робота над проектами',
            'Аналіз і оптимізація',
            'Підсумки та перспективи',
            'Додаткові матеріали',
            'Теорія і практика',
            'Професійні інструменти',
            'Експертні підходи',
            'Вирішення складних завдань',
            'Огляд кращих практик',
            'Технічні аспекти',
            'Методологія і підходи',
        ];
        
        return $titles[array_rand($titles)];
    }
}