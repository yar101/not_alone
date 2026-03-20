<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function messages(): array
    {
        return [
            'name.regex'  => 'Имя не должно содержать пробелы.',
            'name.unique' => 'Это имя уже занято.',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255', 'regex:/^\S+$/u',
                Rule::unique(User::class, 'name')->ignore($this->user()->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
