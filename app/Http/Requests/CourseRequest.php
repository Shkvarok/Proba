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
        $rules = [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'instructor_id' => ['nullable', 'integer', 'exists:users,id'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'lt:price'],
            'discount_expires_at' => ['nullable', 'date', 'after:now'],
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            'language' => ['nullable', 'string', 'max:50'],
            'cover_image' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'image', 'max:2048'], // 2MB max
            'promo_video_url' => ['nullable', 'string', 'max:255', 'url'],
            'requirements' => ['nullable', 'string'],
            'what_you_learn' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            // Для оновлення не всі поля обов'язкові
            $rules = collect($rules)->map(function ($rule, $field) {
                if (in_array('required', $rule)) {
                    return array_diff($rule, ['required']);
                }
                return $rule;
            })->toArray();
        }

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
            'title.max' => 'Назва курсу не повинна перевищувати 100 символів.',
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
            'cover_image.max' => 'Розмір зображення обкладинки не повинен перевищувати 2MB.',
            'promo_video_url.url' => 'URL промо-відео повинен бути коректним.',
            'promo_video_url.max' => 'URL промо-відео не повинен перевищувати 255 символів.',
        ];
    }
}