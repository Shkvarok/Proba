<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Налаштування внутрішніх тестів
    |--------------------------------------------------------------------------
    */

    // Медіафайли
    'media' => [
        // Максимальний розмір файлу в байтах (50MB)
        'max_file_size' => 50 * 1024 * 1024,
        
        // Дозволені типи зображень
        'allowed_image_types' => [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp'
        ],
        
        // Дозволені типи відео
        'allowed_video_types' => [
            'video/mp4',
            'video/avi',
            'video/mov',
            'video/wmv',
            'video/webm'
        ],
        
        // Шляхи для збереження
        'storage_paths' => [
            'questions' => 'tests/questions',
            'answers' => 'tests/answers',
        ],
    ],

    // Налаштування тестів за замовчуванням
    'defaults' => [
        'passing_score' => 70, // Відсоток для проходження
        'max_attempts' => 3,   // Максимальна кількість спроб
        'time_limit_minutes' => null, // Без обмеження часу за замовчуванням
        'randomize_questions' => false,
        'show_results_immediately' => true,
        'status' => 'draft',
    ],

    // Обмеження
    'limits' => [
        'max_questions_per_test' => 100,
        'max_answers_per_question' => 10,
        'max_attempts' => 10,
        'max_time_limit_hours' => 24,
        'min_passing_score' => 1,
        'max_passing_score' => 100,
    ],

    // Типи питань
    'question_types' => [
        'single_choice' => [
            'label' => 'Одиночний вибір',
            'description' => 'Одна правильна відповідь',
            'min_answers' => 2,
            'max_correct_answers' => 1,
        ],
        'multiple_choice' => [
            'label' => 'Множинний вибір',
            'description' => 'Декілька правильних відповідей',
            'min_answers' => 2,
            'max_correct_answers' => null, // Без обмежень
        ],
        'text_input' => [
            'label' => 'Текстове введення',
            'description' => 'Введення власної відповіді',
            'min_answers' => 1, // Мінімум одна правильна відповідь
            'max_correct_answers' => null,
        ],
    ],

    // Кешування
    'cache' => [
        'test_results_ttl' => 3600, // 1 година
        'analytics_ttl' => 1800,    // 30 хвилин
    ],
];