<?php

namespace App\Http\Requests\Donation;

use App\Rules\NoLinks;
use App\Rules\NoProfanity;
use Illuminate\Foundation\Http\FormRequest;

class SendDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('web')->check();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'fee_covered' => ['sometimes', 'boolean'],
            'fee_covered_by_donor' => ['sometimes', 'boolean'],
            'fee_covered_platform' => ['sometimes', 'boolean'],
            'fee_covered_withdrawal' => ['sometimes', 'boolean'],
            'message' => ['nullable', 'string', 'max:200', new NoLinks, new NoProfanity],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Укажите сумму доната.',
            'amount.numeric' => 'Сумма доната должна быть числом.',
            'amount.min' => 'Сумма доната должна быть больше 0.',
            'message.max' => 'Сообщение к донату не должно превышать 200 символов.',
        ];
    }
}
