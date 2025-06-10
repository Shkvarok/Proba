<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\LessonTest;
use App\Models\InternalTest;
use App\Models\TestQuestion;
use App\Models\QuestionAnswer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InternalTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Перевіряємо, чи є уроки типу 'test' в базі даних
        $testLessons = Lesson::where('type', 'test')->get();
        
        if ($testLessons->count() === 0) {
            $this->command->info('Немає уроків типу "test" для наповнення внутрішніми тестами. Спершу виконайте LessonSeeder.');
            return;
        }
        
        $this->command->info('Знайдено ' . $testLessons->count() . ' уроків типу "test".');
        
        foreach ($testLessons as $lesson) {
            // Перевіряємо, чи урок має LessonTest з source_type = 'internal'
            $lessonTest = LessonTest::where('lesson_id', $lesson->id)
                ->where('source_type', 'internal')
                ->first();
            
            if (!$lessonTest) {
                // Створюємо або оновлюємо LessonTest
                $lessonTest = LessonTest::updateOrCreate(
                    ['lesson_id' => $lesson->id],
                    [
                        'source_type' => 'internal',
                        'time_limit_minutes' => rand(10, 60),
                        'passing_score' => rand(60, 80),
                    ]
                );
            }
            
            // Створюємо внутрішній тест
            $internalTest = InternalTest::create([
                'lesson_id' => $lesson->id,
                'title' => $this->getRandomTestTitle(),
                'description' => $this->getRandomTestDescription(),
                'passing_score' => $lessonTest->passing_score,
                'time_limit_minutes' => $lessonTest->time_limit_minutes,
                'status' => rand(0, 1) ? 'active' : 'draft',
                'randomize_questions' => (bool)rand(0, 1),
                'questions_to_show' => rand(0, 1) ? null : rand(5, 10), // null означає всі питання
                'max_attempts' => rand(1, 5),
                'show_results_immediately' => (bool)rand(0, 1),
            ]);
            
            // Створюємо питання для тесту (від 5 до 15 питань)
            $questionsCount = rand(5, 15);
            
            for ($i = 1; $i <= $questionsCount; $i++) {
                $questionType = $this->getRandomQuestionType();
                
                $question = TestQuestion::create([
                    'internal_test_id' => $internalTest->id,
                    'question_text' => $this->getRandomQuestionText($questionType),
                    'question_type' => $questionType,
                    'position' => $i,
                    'points' => rand(1, 10),
                    'explanation' => rand(0, 1) ? $this->getRandomExplanation() : null,
                    'is_required' => (bool)rand(0, 1),
                ]);
                
                // Створюємо відповіді в залежності від типу питання
                $this->createAnswersForQuestion($question, $questionType);
            }
            
            $this->command->info("Створено внутрішній тест для уроку: {$lesson->title}");
        }
        
        $this->command->info('Внутрішні тести успішно створені для всіх уроків типу "test".');
    }
    
    /**
     * Отримати випадковий тип питання
     */
    private function getRandomQuestionType(): string
    {
        $types = ['single_choice', 'multiple_choice', 'text_input'];
        
        // Додаємо більше ваги для single_choice
        $weightedTypes = [
            'single_choice', 'single_choice', 'single_choice',
            'multiple_choice', 'multiple_choice',
            'text_input'
        ];
        
        return $weightedTypes[array_rand($weightedTypes)];
    }
    
    /**
     * Отримати випадкову назву тесту
     */
    private function getRandomTestTitle(): string
    {
        $titles = [
            'Перевірка знань з модуля',
            'Тестування засвоєних навичок',
            'Контрольна робота',
            'Опитування по темі',
            'Діагностичний тест',
            'Підсумкова перевірка',
            'Самоперевірка знань',
            'Практичне тестування',
            'Оцінка рівня підготовки',
            'Експрес-тест',
            'Детальна перевірка',
            'Аналіз розуміння матеріалу',
        ];
        
        return $titles[array_rand($titles)];
    }
    
    /**
     * Отримати випадковий опис тесту
     */
    private function getRandomTestDescription(): string
    {
        $descriptions = [
            'Цей тест допоможе оцінити ваше розуміння основних концепцій, розглянутих у цьому модулі.',
            'Перевірте свої знання та навички, отримані під час вивчення матеріалу.',
            'Тестування включає питання різної складності для комплексної оцінки ваших знань.',
            'Практичні завдання та теоретичні питання для перевірки засвоєння матеріалу.',
            'Діагностичний тест для визначення рівня вашої підготовки з теми.',
            'Комплексна перевірка знань з можливістю отримання детального зворотного зв\'язку.',
            'Тест містить питання, що охоплюють ключові аспекти вивченого матеріалу.',
            'Оцініть свій прогрес та готовність до переходу до наступного модуля.',
        ];
        
        return $descriptions[array_rand($descriptions)];
    }
    
    /**
     * Отримати випадковий текст питання
     */
    private function getRandomQuestionText(string $questionType): string
    {
        $questions = [
            'single_choice' => [
                'Який з наведених варіантів є правильним визначенням поняття?',
                'Виберіть найкращий підхід для вирішення цієї проблеми:',
                'Що з наведеного є основною характеристикою цього процесу?',
                'Який інструмент найкраще підходить для цього завдання?',
                'Оберіть правильну послідовність дій:',
                'Який принцип лежить в основі цього методу?',
                'Що є головною перевагою цього підходу?',
                'Який варіант найточніше описує цей процес?',
            ],
            'multiple_choice' => [
                'Виберіть всі правильні твердження (можливо декілька варіантів):',
                'Які з наведених елементів входять до складу системи?',
                'Оберіть усі методи, що застосовуються в цьому процесі:',
                'Які переваги має цей підхід? (виберіть усі правильні)',
                'Які етапи включає в себе цей процес?',
                'Оберіть всі інструменти, що можуть бути використані:',
                'Які принципи слід дотримуватися при виконанні цього завдання?',
                'Виберіть усі можливі варіанти рішення проблеми:',
            ],
            'text_input' => [
                'Як називається цей процес або методологія?',
                'Введіть назву технології, що використовується для цього:',
                'Яка назва цього поняття або терміна?',
                'Як називається інструмент для виконання цього завдання?',
                'Введіть ключове слово, що характеризує цей підхід:',
                'Яка назва цього алгоритму або методу?',
                'Як називається ця характеристика або властивість?',
                'Введіть термін, що описує цю концепцію:',
            ],
        ];
        
        return $questions[$questionType][array_rand($questions[$questionType])];
    }
    
    /**
     * Отримати випадкове пояснення
     */
    private function getRandomExplanation(): string
    {
        $explanations = [
            'Це правильна відповідь, оскільки вона найточніше відображає основні принципи теми.',
            'Даний варіант є оптимальним рішенням з огляду на ефективність та практичність.',
            'Це твердження базується на фундаментальних концепціях, розглянутих у курсі.',
            'Правильна відповідь ґрунтується на кращих практиках галузі.',
            'Це рішення забезпечує найкращий результат при мінімальних затратах ресурсів.',
            'Даний підхід рекомендується експертами як найбільш надійний.',
            'Це правильний варіант згідно з сучасними стандартами та методологіями.',
            'Такий підхід забезпечує максимальну ефективність та якість результату.',
        ];
        
        return $explanations[array_rand($explanations)];
    }
    
    /**
     * Створити відповіді для питання залежно від типу
     */
    private function createAnswersForQuestion(TestQuestion $question, string $questionType): void
    {
        switch ($questionType) {
            case 'single_choice':
                $this->createSingleChoiceAnswers($question);
                break;
            case 'multiple_choice':
                $this->createMultipleChoiceAnswers($question);
                break;
            case 'text_input':
                $this->createTextInputAnswers($question);
                break;
        }
    }
    
    /**
     * Створити відповіді для питання з одиночним вибором
     */
    private function createSingleChoiceAnswers(TestQuestion $question): void
    {
        $answers = [
            'Варіант А - перший можливий варіант відповіді',
            'Варіант Б - другий можливий варіант відповіді',
            'Варіант В - третій можливий варіант відповіді',
            'Варіант Г - четвертий можливий варіант відповіді',
        ];
        
        // Випадково вибираємо кількість відповідей (3-4)
        $answersCount = rand(3, 4);
        $selectedAnswers = array_slice($answers, 0, $answersCount);
        
        // Випадково визначаємо, яка відповідь буде правильною
        $correctIndex = rand(0, $answersCount - 1);
        
        foreach ($selectedAnswers as $index => $answerText) {
            QuestionAnswer::create([
                'test_question_id' => $question->id,
                'answer_text' => $answerText,
                'is_correct' => $index === $correctIndex,
                'position' => $index + 1,
            ]);
        }
    }
    
    /**
     * Створити відповіді для питання з множинним вибором
     */
    private function createMultipleChoiceAnswers(TestQuestion $question): void
    {
        $answers = [
            'Перший правильний варіант',
            'Другий правильний варіант',
            'Третій правильний варіант',
            'Неправильний варіант А',
            'Неправильний варіант Б',
            'Неправильний варіант В',
        ];
        
        // Випадково вибираємо кількість відповідей (4-6)
        $answersCount = rand(4, 6);
        $selectedAnswers = array_slice($answers, 0, $answersCount);
        
        // Випадково визначаємо, які відповіді будуть правильними (2-3 з можливих)
        $correctCount = rand(2, min(3, $answersCount - 1));
        $correctIndices = array_rand(array_flip(range(0, $answersCount - 1)), $correctCount);
        
        if (!is_array($correctIndices)) {
            $correctIndices = [$correctIndices];
        }
        
        foreach ($selectedAnswers as $index => $answerText) {
            QuestionAnswer::create([
                'test_question_id' => $question->id,
                'answer_text' => $answerText,
                'is_correct' => in_array($index, $correctIndices),
                'position' => $index + 1,
            ]);
        }
    }
    
    /**
     * Створити відповіді для текстового введення
     */
    private function createTextInputAnswers(TestQuestion $question): void
    {
        // Для текстових питань створюємо можливі правильні варіанти відповідей
        $possibleAnswers = [
            ['React', 'ReactJS', 'React.js'],
            ['JavaScript', 'JS', 'Javascript'],
            ['HTML', 'HyperText Markup Language'],
            ['CSS', 'Cascading Style Sheets'],
            ['API', 'Application Programming Interface'],
            ['SQL', 'Structured Query Language'],
            ['HTTP', 'HyperText Transfer Protocol'],
            ['JSON', 'JavaScript Object Notation'],
        ];
        
        $selectedAnswerGroup = $possibleAnswers[array_rand($possibleAnswers)];
        
        foreach ($selectedAnswerGroup as $index => $answerText) {
            QuestionAnswer::create([
                'test_question_id' => $question->id,
                'answer_text' => $answerText,
                'is_correct' => true, // Всі варіанти правильні для текстового введення
                'position' => $index + 1,
            ]);
        }
    }
}