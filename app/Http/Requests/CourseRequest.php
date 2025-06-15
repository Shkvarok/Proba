<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

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
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch') || 
                   ($this->isMethod('post') && $this->has('_method') && $this->_method === 'PUT');
        
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
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048', // 2MB
                'dimensions:min_width=300,min_height=200,max_width=1920,max_height=1080'
            ],
            'promo_video_url' => ['nullable', 'string', 'max:255', 'url'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'what_you_learn' => ['nullable', 'string', 'max:5000'],
            'is_published' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
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
            'cover_image.file' => 'Обкладинка повинна бути файлом.',
            'cover_image.image' => 'Файл обкладинки повинен бути зображенням.',
            'cover_image.mimes' => 'Дозволені формати зображень: JPEG, JPG, PNG, WebP.',
            'cover_image.max' => 'Розмір зображення обкладинки не повинен перевищувати 2MB.',
            'cover_image.dimensions' => 'Розміри зображення повинні бути мінімум 300x200 і максимум 1920x1080 пікселів.',
            'promo_video_url.url' => 'URL промо-відео повинен бути коректним.',
            'promo_video_url.max' => 'URL промо-відео не повинен перевищувати 255 символів.',
            'requirements.max' => 'Вимоги не повинні перевищувати 5000 символів.',
            'what_you_learn.max' => 'Опис того, що вивчать, не повинен перевищувати 5000 символів.',
            'meta_title.max' => 'Мета-заголовок не повинен перевищувати 255 символів.',
            'meta_description.max' => 'Мета-опис не повинен перевищувати 500 символів.',
        ];
    }

    /**
     * Підготовка даних для валідації
     */
    protected function prepareForValidation()
    {
        // Логування для налагодження
        Log::info('CourseRequest::prepareForValidation', [
            'method' => $this->method(),
            'all_data' => $this->except(['cover_image']),
            'content_type' => $this->header('Content-Type'),
            'has_method_field' => $this->has('_method')
        ]);

        // Обробляємо method spoofing для multipart requests
        if ($this->isMethod('POST') && $this->has('_method')) {
            $method = strtoupper($this->input('_method'));
            if (in_array($method, ['PUT', 'PATCH'])) {
                $this->setMethod($method);
                $this->request->remove('_method');
                Log::info('Method spoofing applied', ['new_method' => $method]);
            }
        }

        // Конвертуємо строкові булеві значення
        if ($this->has('is_published')) {
            $value = $this->input('is_published');
            if (is_string($value)) {
                $booleanValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($booleanValue !== null) {
                    $this->merge(['is_published' => $booleanValue]);
                }
            }
        }

        // Конвертуємо числові поля
        $numericFields = ['price', 'discount_price', 'category_id', 'level_id', 'instructor_id'];
        foreach ($numericFields as $field) {
            if ($this->has($field) && $this->input($field) !== '' && $this->input($field) !== null) {
                $value = $this->input($field);
                if (is_string($value) && is_numeric($value)) {
                    $this->merge([$field => strpos($value, '.') !== false ? (float)$value : (int)$value]);
                }
            }
        }

        // Якщо ціна 0, то автоматично позначити як безкоштовний
        if ($this->has('price') && $this->price == 0) {
            $this->merge(['is_free' => true]);
        }

        // Встановити instructor_id як поточного користувача для створення
        if (!$this->has('instructor_id') && !$this->isUpdate()) {
            $this->merge(['instructor_id' => auth()->id()]);
        }

        // Очищення порожніх рядків
        $fieldsToClean = ['description', 'requirements', 'what_you_learn', 'promo_video_url', 'meta_title', 'meta_description'];
        foreach ($fieldsToClean as $field) {
            if ($this->has($field) && trim($this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }

        Log::info('CourseRequest::prepareForValidation completed', [
            'processed_data' => $this->except(['cover_image']),
            'final_method' => $this->method()
        ]);
    }

    /**
     * Отримати очищені дані для створення/оновлення курсу
     */
    public function getCourseData(): array
    {
        // Логування для налагодження
        Log::info('CourseRequest::getCourseData called', [
            'method' => $this->method(),
            'is_update' => $this->isUpdate(),
            'all_input' => $this->except(['cover_image', '_token', '_method'])
        ]);

        // Отримуємо тільки поля без файлу
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
            'promo_video_url',
            'requirements',
            'what_you_learn',
            'is_published',
            'meta_title',
            'meta_description'
        ]);

        // Для оновлення видаляємо поля які не були надіслані або null
        if ($this->isUpdate()) {
            $data = array_filter($data, function($value, $key) {
                // Зберігаємо поле якщо:
                // 1. Воно присутнє в запиті
                // 2. Або це булеве поле is_published (може бути false)
                return $this->has($key) || $key === 'is_published';
            }, ARRAY_FILTER_USE_BOTH);
            
            // Спеціальна обробка для булевих полів
            if ($this->has('is_published')) {
                $data['is_published'] = $this->boolean('is_published');
            }
        }

        Log::info('CourseRequest::getCourseData returning:', $data);

        return $data;
    }

    /**
     * Перевірити чи це оновлення
     */
    public function isUpdate(): bool
    {
        return $this->isMethod('put') || $this->isMethod('patch') || 
               ($this->isMethod('post') && $this->has('_method') && $this->_method === 'PUT');
    }
}