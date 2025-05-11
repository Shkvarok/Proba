<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Lesson;
use App\Models\LessonLecture;
use App\Models\LessonTest;
use App\Models\LessonExtraMaterial;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Перевіряємо, чи є модулі в базі даних
        $modulesCount = Module::count();
        
        if ($modulesCount === 0) {
            $this->command->info('Немає модулів для наповнення уроками. Спершу виконайте ModuleSeeder.');
            return;
        }
        
        // Отримуємо всі модулі
        $modules = Module::all();
        
        foreach ($modules as $module) {
            // Створюємо випадкову кількість уроків для кожного модуля (від 3 до 10)
            $lessonsCount = rand(3, 10);
            
            for ($i = 1; $i <= $lessonsCount; $i++) {
                // Визначаємо тип уроку (з пріоритетом для лекцій)
                $types = ['lecture', 'lecture', 'lecture', 'test', 'extra_material'];
                $type = $types[array_rand($types)];
                
                // Створюємо урок
                $lesson = Lesson::create([
                    'module_id' => $module->id,
                    'title' => $this->getLessonTitle($type, $i),
                    'description' => $this->getRandomDescription(),
                    'type' => $type,
                    'position' => $i,
                    'status' => 'active',
                ]);
                
                // Створюємо деталі в залежності від типу уроку
                switch ($type) {
                    case 'lecture':
                        LessonLecture::create([
                            'lesson_id' => $lesson->id,
                            'content' => $this->getRandomLectureContent(),
                            'duration_minutes' => rand(10, 90),
                        ]);
                        break;
                    
                    case 'test':
                        LessonTest::create([
                            'lesson_id' => $lesson->id,
                            'source_type' => 'internal',
                            'time_limit_minutes' => rand(10, 60),
                            'passing_score' => rand(60, 80),
                        ]);
                        break;
                    
                    case 'extra_material':
                        $materialTypes = ['text', 'url', 'video'];
                        $materialType = $materialTypes[array_rand($materialTypes)];
                        
                        $data = [
                            'lesson_id' => $lesson->id,
                            'material_type' => $materialType,
                        ];
                        
                        if ($materialType === 'text') {
                            $data['content'] = $this->getRandomLectureContent();
                        } elseif ($materialType === 'url') {
                            $data['url'] = 'https://example.com/resource/' . rand(1000, 9999);
                        } elseif ($materialType === 'video') {
                            $data['url'] = 'https://youtu.be/' . substr(md5(rand()), 0, 11);
                        }
                        
                        LessonExtraMaterial::create($data);
                        break;
                }
            }
        }
        
        $this->command->info('Уроки успішно створені для всіх модулів.');
    }
    
    /**
     * Отримати назву уроку відповідно до типу
     */
    private function getLessonTitle(string $type, int $position): string
    {
        $prefixes = [
            'lecture' => ['Лекція', 'Тема', 'Розділ', 'Презентація'],
            'test' => ['Тест', 'Перевірка знань', 'Контрольна робота', 'Опитування'],
            'extra_material' => ['Додаткові матеріали', 'Корисні посилання', 'Розширений огляд', 'Практичні ресурси'],
        ];
        
        $selectedPrefix = $prefixes[$type][array_rand($prefixes[$type])];
        
        $titles = [
            'lecture' => [
                'Вступ до теми',
                'Основні принципи',
                'Ключові концепції',
                'Теоретичні основи',
                'Практичне застосування',
                'Покрокове пояснення',
                'Огляд технологій',
                'Робота з інструментами',
                'Аналіз прикладів',
                'Вирішення проблем',
            ],
            'test' => [
                'за темою модуля',
                'основних понять',
                'практичних навичок',
                'теоретичних знань',
                'здобутих навичок',
            ],
            'extra_material' => [
                'для поглибленого вивчення',
                'для самостійної роботи',
                'цікаві факти',
                'корисні ресурси',
                'додаткові інструменти',
                'приклади з практики',
            ],
        ];
        
        $selectedTitle = $titles[$type][array_rand($titles[$type])];
        
        return "{$selectedPrefix} {$position}: {$selectedTitle}";
    }
    
    /**
     * Отримати випадковий опис
     */
    private function getRandomDescription(): string
    {
        $descriptions = [
            'У цьому уроці ви дізнаєтесь про основні принципи та концепції, які допоможуть вам краще зрозуміти тему.',
            'Детальний огляд важливих аспектів, які необхідно знати для успішного освоєння матеріалу.',
            'Практичний підхід до вивчення теми з конкретними прикладами та поясненнями.',
            'Цей матеріал допоможе вам розібратися в складних питаннях та підготуватися до практичного застосування знань.',
            'Ключові поняття та методи, які є фундаментом для подальшого вивчення теми.',
            'Розширений огляд теми з акцентом на практичному застосуванні в реальних проектах.',
        ];
        
        return $descriptions[array_rand($descriptions)];
    }
    
    /**
     * Отримати випадковий контент для лекції
     */
    private function getRandomLectureContent(): string
    {
        $paragraphs = [
            "# Вступ до теми\n\nУ цьому розділі ми розглянемо основні концепції та принципи, які є фундаментом для розуміння всього курсу. Важливо приділити особливу увагу теоретичним основам, оскільки вони формують базу для практичних навичок.\n\n## Ключові поняття\n\n- Перше важливе поняття та його значення\n- Друге ключове поняття та його застосування\n- Взаємозв'язок між основними елементами\n\nРозуміння цих концепцій дозволить вам ефективно застосовувати знання на практиці та вирішувати реальні завдання.",
            
            "# Практичне застосування\n\nТеорія є важливою, але справжня цінність знань проявляється в їх практичному застосуванні. У цьому розділі ми розглянемо конкретні приклади та сценарії використання вивчених концепцій.\n\n## Приклад 1\n\nРозглянемо типову ситуацію, коли необхідно вирішити наступну проблему... [опис проблеми]\n\nРішення:\n1. Перший крок - аналіз вимог\n2. Другий крок - розробка підходу\n3. Третій крок - впровадження рішення\n\n## Приклад 2\n\n[інший практичний приклад з рішенням]",
            
            "# Аналіз та оптимізація\n\nПісля вивчення основних концепцій та їх практичного застосування, важливо навчитися аналізувати результати та оптимізувати процеси для досягнення кращих результатів.\n\n## Методи аналізу\n\n1. Кількісна оцінка результатів\n2. Якісний аналіз ефективності\n3. Порівняльний аналіз різних підходів\n\n## Стратегії оптимізації\n\n- Виявлення вузьких місць та їх усунення\n- Підвищення ефективності ключових процесів\n- Автоматизація рутинних операцій",
        ];
        
        // Об'єднуємо 2-3 випадкових параграфи
        $count = rand(2, 3);
        $keys = array_rand($paragraphs, $count);
        
        $content = '';
        if (is_array($keys)) {
            foreach ($keys as $key) {
                $content .= $paragraphs[$key] . "\n\n";
            }
        } else {
            $content = $paragraphs[$keys];
        }
        
        return $content;
    }
}