<?php

namespace App\Http\Requests\Review;

use App\Rules\NoLinks;
use App\Rules\NoProfanity;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'text' => ['nullable', 'string', 'max:250', new NoLinks, new NoProfanity],
            'epithets' => ['nullable', 'array'],
            'epithets.*' => ['integer', 'exists:review_epithets,id'],
        ];
    }
}
