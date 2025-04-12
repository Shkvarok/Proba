<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Змініть на відповідну логіку авторизації, якщо необхідно
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoryId = $this->route('category.id') ?? null;

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable', 
                'string', 
                'max:100', 
                Rule::unique('categories', 'slug')->ignore($categoryId)
            ],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable', 
                'integer', 
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($categoryId) {
                    // Перевірка на рекурсивність (категорія не може бути власним батьком)
                    if ($value == $categoryId) {
                        $fail('Категорія не може бути власним батьком.');
                    }
                }
            ],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
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
            'name.required' => 'Назва категорії обов\'язкова.',
            'name.max' => 'Назва категорії не повинна перевищувати 100 символів.',
            'slug.unique' => 'Такий URL-ідентифікатор вже існує.',
            'slug.max' => 'URL-ідентифікатор не повинен перевищувати 100 символів.',
            'parent_id.exists' => 'Обрана батьківська категорія не існує.',
        ];
    }
}