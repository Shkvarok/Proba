<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InternalTestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Авторизація буде перевірятися через middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        
        return [
            'lesson_id' => [$isUpdate ? 'sometimes' : 'required', 'exists:lessons,id'],
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'passing_score' => ['integer', 'min:1', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], // Максимум 24 години
            'status' => ['in:active,draft'],
            'randomize_questions' => ['boolean'],
            'questions_to_show' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['integer', 'min:1', 'max:10'],
            'show_results_immediately' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'lesson_id.required' => 'Урок є обов\'язковим.',
            'lesson_id.exists' => 'Обраний урок не існує.',
            'title.required' => 'Назва тесту є обов\'язковою.',
            'title.max' => 'Назва тесту не повинна перевищувати 255 символів.',
            'description.max' => 'Опис не повинен перевищувати 5000 символів.',
            'passing_score.integer' => 'Прохідний бал повинен бути цілим числом.',
            'passing_score.min' => 'Прохідний бал повинен бути не менше 1%.',
            'passing_score.max' => 'Прохідний бал не може перевищувати 100%.',
            'time_limit_minutes.integer' => 'Ліміт часу повинен бути цілим числом.',
            'time_limit_minutes.min' => 'Ліміт часу повинен бути не менше 1 хвилини.',
            'time_limit_minutes.max' => 'Ліміт часу не може перевищувати 24 години.',
            'status.in' => 'Статус повинен бути "активний" або "чернетка".',
            'randomize_questions.boolean' => 'Параметр випадкового порядку повинен бути логічним значенням.',
            'questions_to_show.integer' => 'Кількість питань для показу повинна бути цілим числом.',
            'questions_to_show.min' => 'Кількість питань для показу повинна бути не менше 1.',
            'max_attempts.integer' => 'Максимальна кількість спроб повинна бути цілим числом.',
            'max_attempts.min' => 'Максимальна кількість спроб повинна бути не менше 1.',
            'max_attempts.max' => 'Максимальна кількість спроб не може перевищувати 10.',
            'show_results_immediately.boolean' => 'Параметр показу результатів повинен бути логічним значенням.',
        ];
    }

    /**
     * Підготовка даних для валідації
     */
    protected function prepareForValidation()
    {
        // Встановлюємо значення за замовчуванням
        $defaults = [
            'passing_score' => 70,
            'max_attempts' => 3,
            'status' => 'draft',
            'randomize_questions' => false,
            'show_results_immediately' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (!$this->has($key)) {
                $this->merge([$key => $value]);
            }
        }
    }

    /**
     * Отримати очищені дані для створення/оновлення тесту
     */
    public function getTestData(): array
    {
        return $this->only([
            'lesson_id',
            'title',
            'description',
            'passing_score',
            'time_limit_minutes',
            'status',
            'randomize_questions',
            'questions_to_show',
            'max_attempts',
            'show_results_immediately',
        ]);
    }
}