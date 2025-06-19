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

        // Реалістичні назви курсів та їх описи
        $courseTemplates = [
            [
                'title' => 'Основи веб-розробки',
                'description' => 'Комплексний курс для початківців, де ви вивчите HTML, CSS та JavaScript. Дізнаєтесь як створювати сучасні адаптивні веб-сайти з нуля.',
                'requirements' => ["Базові знання комп'ютера", "Доступ до Інтернету", "Встановлений текстовий редактор"],
                'what_you_learn' => ["Створення HTML структури", "Стилізація з CSS", "Інтерактивність з JavaScript", "Адаптивний дизайн"],
            ],
            [
                'title' => 'Python для початківців',
                'description' => 'Вивчіть програмування на Python з нуля. Курс охоплює основи синтаксису, структури даних та принципи об\'єктно-орієнтованого програмування.',
                'requirements' => ["Базові знання математики", "Доступ до комп'ютера"],
                'what_you_learn' => ["Синтаксис Python", "Структури даних", "Функції та модулі", "ООП в Python"],
            ],
            [
                'title' => 'Графічний дизайн у Photoshop',
                'description' => 'Навчіться створювати професійні графічні дизайни використовуючи Adobe Photoshop. Від основ до складних технік ретушування.',
                'requirements' => ["Adobe Photoshop", "Базові знання комп'ютера"],
                'what_you_learn' => ["Інтерфейс Photoshop", "Робота з шарами", "Ретушування фото", "Створення графіки"],
            ],
            [
                'title' => 'Цифровий маркетинг',
                'description' => 'Повний курс цифрового маркетингу: SEO, контекстна реклама, SMM та email-маркетинг. Практичні кейси та інструменти.',
                'requirements' => ["Базові знання Інтернету", "Доступ до соціальних мереж"],
                'what_you_learn' => ["SEO оптимізація", "Google Ads", "Facebook реклама", "Email кампанії"],
            ],
            [
                'title' => 'React.js розробка',
                'description' => 'Сучасна розробка інтерфейсів з React.js. Компоненти, хуки, стан додатку та інтеграція з API.',
                'requirements' => ["Знання JavaScript", "Базові знання HTML/CSS", "Node.js"],
                'what_you_learn' => ["React компоненти", "JSX синтаксис", "React hooks", "Управління станом"],
            ],
            [
                'title' => 'Data Science з Python',
                'description' => 'Аналіз даних та машинне навчання з використанням Python, pandas, NumPy та scikit-learn. Практичні проєкти з реальними даними.',
                'requirements' => ["Знання Python", "Базові знання математики", "Jupyter Notebook"],
                'what_you_learn' => ["Pandas для аналізу", "Візуалізація даних", "Машинне навчання", "Статистичний аналіз"],
            ],
            [
                'title' => 'UI/UX дизайн у Figma',
                'description' => 'Створення користувацьких інтерфейсів та досвіду взаємодії. Принципи UX дизайну та практичне використання Figma.',
                'requirements' => ["Figma акаунт", "Базові знання дизайну"],
                'what_you_learn' => ["Принципи UX", "Прототипування", "Figma інструменти", "Тестування інтерфейсів"],
            ],
            [
                'title' => 'Мобільна розробка з Flutter',
                'description' => 'Розробка крос-платформних мобільних додатків з Flutter. Від створення UI до публікації в App Store та Google Play.',
                'requirements' => ["Базові знання програмування", "Android Studio або VS Code", "Dart SDK"],
                'what_you_learn' => ["Flutter віджети", "Dart мова", "Навігація", "Публікація додатків"],
            ],
            [
                'title' => 'Кібербезпека для початківців',
                'description' => 'Основи інформаційної безпеки: захист персональних даних, безпека мереж та виявлення кіберзагроз.',
                'requirements' => ["Базові знання комп'ютерних мереж", "Розуміння Інтернету"],
                'what_you_learn' => ["Типи кіберзагроз", "Захист паролів", "Безпека мереж", "Етичний хакінг"],
            ],
            [
                'title' => 'WordPress розробка',
                'description' => 'Створення професійних веб-сайтів на WordPress. Теми, плагіни, кастомізація та SEO оптимізація.',
                'requirements' => ["Базові знання HTML/CSS", "FTP клієнт", "Локальний сервер"],
                'what_you_learn' => ["Структура WordPress", "Створення тем", "Робота з плагінами", "Безпека WordPress"],
            ],
            [
                'title' => 'Основи бухгалтерського обліку',
                'description' => 'Вивчіть основи бухгалтерського обліку та фінансової звітності. Практичні навички ведення обліку для малого бізнесу.',
                'requirements' => ["Базові знання математики", "Microsoft Excel"],
                'what_you_learn' => ["Подвійний запис", "Баланс підприємства", "Податкова звітність", "1С:Бухгалтерія"],
            ],
            [
                'title' => 'Відеомонтаж у Adobe Premiere',
                'description' => 'Професійний відеомонтаж з Adobe Premiere Pro. Від базового монтажу до колірної корекції та спецефектів.',
                'requirements' => ["Adobe Premiere Pro", "Потужний комп'ютер", "Відеоматеріали"],
                'what_you_learn' => ["Інтерфейс Premiere", "Монтаж відео", "Звукова доріжка", "Експорт відео"],
            ],
            [
                'title' => 'Інтернет-маркетинг для e-commerce',
                'description' => 'Стратегії просування інтернет-магазинів: від налаштування Google Analytics до створення воронок продажів.',
                'requirements' => ["Власний сайт або магазин", "Google Analytics", "Базові знання маркетингу"],
                'what_you_learn' => ["Веб-аналітика", "Конверсійна оптимізація", "Email автоматизація", "Ретаргетинг"],
            ],
            [
                'title' => 'Node.js серверна розробка',
                'description' => 'Розробка серверних додатків з Node.js та Express. API, бази даних, автентифікація та деплой.',
                'requirements' => ["Знання JavaScript", "Node.js та npm", "Базові знання HTTP"],
                'what_you_learn' => ["Express.js фреймворк", "REST API", "MongoDB/PostgreSQL", "Автентифікація JWT"],
            ],
            [
                'title' => 'Машинне навчання з TensorFlow',
                'description' => 'Глибоке навчання та нейронні мережі з TensorFlow. Практичні проєкти з розпізнавання зображень та NLP.',
                'requirements' => ["Python продвинутий рівень", "Знання математики", "GPU рекомендується"],
                'what_you_learn' => ["Нейронні мережі", "Згорткові мережі", "RNN та LSTM", "Transfer Learning"],
            ],
            [
                'title' => 'DevOps з Docker та Kubernetes',
                'description' => 'Контейнеризація додатків з Docker та оркестрація з Kubernetes. CI/CD пайплайни та моніторинг.',
                'requirements' => ["Linux командний рядок", "Базові знання мереж", "Git"],
                'what_you_learn' => ["Docker контейнери", "Kubernetes кластери", "CI/CD Jenkins", "Моніторинг Prometheus"],
            ],
            [
                'title' => 'iOS розробка зі Swift',
                'description' => 'Створення нативних iOS додатків з Swift та Xcode. UIKit, SwiftUI та публікація в App Store.',
                'requirements' => ["macOS", "Xcode", "Apple Developer акаунт"],
                'what_you_learn' => ["Swift мова", "UIKit фреймворк", "SwiftUI", "App Store Connect"],
            ],
            [
                'title' => 'Blockchain та криптовалюти',
                'description' => 'Технологія блокчейн, смарт-контракти та створення власної криптовалюти. Solidity та Ethereum.',
                'requirements' => ["Базові знання програмування", "MetaMask гаманець", "Ethereum testnet"],
                'what_you_learn' => ["Принципи блокчейн", "Solidity мова", "Смарт-контракти", "DeFi протоколи"],
            ],
            [
                'title' => 'Тестування програмного забезпечення',
                'description' => 'Ручне та автоматизоване тестування ПЗ. Методології тестування, інструменти та практичні кейси.',
                'requirements' => ["Базові знання програмування", "Логічне мислення"],
                'what_you_learn' => ["Типи тестування", "Test cases", "Selenium WebDriver", "API тестування"],
            ],
            [
                'title' => 'Копірайтинг та контент-маркетинг',
                'description' => 'Створення продавального контенту для різних каналів. SMM-тексти, email-розсилки та лендинги.',
                'requirements' => ["Грамотність української мови", "Базові знання маркетингу"],
                'what_you_learn' => ["Структура тексту", "Заголовки та хуки", "Email копірайтинг", "Контент-стратегія"],
            ]
        ];

        // Створимо курси з реалістичними даними
        $courses = [];
        $now = now();
        $languages = ['українська', 'англійська', 'польська', 'німецька'];

        // Перемішуємо шаблони та беремо 20
        $selectedTemplates = collect($courseTemplates)->shuffle()->take(20);

        foreach ($selectedTemplates as $template) {
            $price = rand(0, 9999);
            $hasDiscount = (bool)rand(0, 1);
            $discountPrice = $hasDiscount ? $price * (rand(50, 90) / 100) : null;
            $discountExpiresAt = $hasDiscount ? $now->copy()->addDays(rand(5, 30)) : null;
            $isPublished = (bool)rand(0, 1);
            $language = $languages[array_rand($languages)];
            
            $categoryId = $categoryIds[array_rand($categoryIds)];
            $levelId = $levelIds[array_rand($levelIds)];
            $instructorId = $instructorIds[array_rand($instructorIds)];

            $requirementsText = implode("\n", $template['requirements']);
            $learnText = implode("\n", $template['what_you_learn']);

            $courses[] = [
                'title' => $template['title'],
                'description' => $template['description'],
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
                'meta_title' => $template['title'],
                'meta_description' => Str::limit($template['description'], 160),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        // Вставляємо курси в базу даних
        DB::table('courses')->insert($courses);

        $this->command->info('Додано ' . count($courses) . ' реалістичних курсів до бази даних.');
    }
}