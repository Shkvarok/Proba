<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LevelRequest extends FormRequest
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
        $levelId = $this->route('level.id') ?? null;

        return [
            'code' => [
                'required', 
                'string', 
                'max:20', 
                Rule::unique('levels', 'code')->ignore($levelId)
            ],
            'name' => [
                'required', 
                'string', 
                'max:50', 
                Rule::unique('levels', 'name')->ignore($levelId)
            ],
            'description' => ['nullable', 'string'],
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
            'code.required' => 'Код рівня обов\'язковий.',
            'code.unique' => 'Такий код рівня вже існує.',
            'code.max' => 'Код рівня не повинен перевищувати 20 символів.',
            'name.required' => 'Назва рівня обов\'язкова.',
            'name.unique' => 'Така назва рівня вже існує.',
            'name.max' => 'Назва рівня не повинна перевищувати 50 символів.',
        ];
    }
}