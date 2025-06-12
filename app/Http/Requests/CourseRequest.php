<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
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
        
        $rules = [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:categories,id'],
            'instructor_id' => ['nullable', 'integer', 'exists:users,id'],
            'price' => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0', 'max:9999999.99'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'lt:price'],
            'discount_expires_at' => ['nullable', 'date', 'after:now'],
            'level_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:levels,id'],
            'language' => ['nullable', 'string', 'max:50'],
            'cover_image' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048', // 2MB
                'dimensions:min_width=300,min_height=200,max_width=1920,max_height=1080'
            ],
            'promo_video_url' => ['nullable', 'string', 'max:255', 'url'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'what_you_learn' => ['nullable', 'string', 'max:5000'],
            'is_published' => ['nullable', 'boolean'],
            'is_free' => ['nullable', 'boolean'],
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Назва курсу обов\'язкова.',
            'title.max' => 'Назва курсу не повинна перевищувати 255 символів.',
            'category_id.required' => 'Категорія обов\'язкова.',
            'category_id.exists' => 'Обрана категорія не існує.',
            'instructor_id.exists' => 'Обраний інструктор не існує.',
            'price.required' => 'Ціна курсу обов\'язкова.',
            'price.numeric' => 'Ціна повинна бути числом.',
            'price.min' => 'Ціна не може бути від\'ємною.',
            'discount_price.numeric' => 'Ціна зі знижкою повинна бути числом.',
            'discount_price.min' => 'Ціна зі знижкою не може бути від\'ємною.',
            'discount_price.lt' => 'Ціна зі знижкою повинна бути меншою за основну ціну.',
            'discount_expires_at.date' => 'Дата закінчення знижки повинна бути коректною датою.',
            'discount_expires_at.after' => 'Дата закінчення знижки повинна бути в майбутньому.',
            'level_id.required' => 'Рівень складності обов\'язковий.',
            'level_id.exists' => 'Обраний рівень складності не існує.',
            'cover_image.image' => 'Файл обкладинки повинен бути зображенням.',
            'cover_image.mimes' => 'Дозволені формати зображень: JPEG, JPG, PNG, WebP.',
            'cover_image.max' => 'Розмір зображення обкладинки не повинен перевищувати 2MB.',
            'cover_image.dimensions' => 'Розміри зображення повинні бути мінімум 300x200 і максимум 1920x1080 пікселів.',
            'promo_video_url.url' => 'URL промо-відео повинен бути коректним.',
            'promo_video_url.max' => 'URL промо-відео не повинен перевищувати 255 символів.',
            'requirements.max' => 'Вимоги не повинні перевищувати 5000 символів.',
            'what_you_learn.max' => 'Опис того, що вивчать, не повинен перевищувати 5000 символів.',
        ];
    }

    /**
     * Підготовка даних для валідації
     */
    protected function prepareForValidation()
    {
        // Якщо ціна 0, то автоматично позначити як безкоштовний
        if ($this->has('price') && $this->price == 0) {
            $this->merge([
                'is_free' => true
            ]);
        }

        // Установити instructor_id як поточного користувача, якщо не вказано
        if (!$this->has('instructor_id')) {
            $this->merge([
                'instructor_id' => auth()->id()
            ]);
        }
    }

    /**
     * Отримати очищені дані для створення/оновлення курсу
     */
    public function getCourseData(): array
    {
        $data = $this->only([
            'title',
            'description',
            'category_id',
            'instructor_id',
            'price',
            'discount_price',
            'discount_expires_at',
            'level_id',
            'language',
            'cover_image',
            'promo_video_url',
            'requirements',
            'what_you_learn',
            'is_published',
            'meta_title',
            'meta_description'
        ]);

        // Логуємо дані перед поверненням
        \Log::info('CourseRequest::getCourseData returning:', $data);

        return $data;
    }
}