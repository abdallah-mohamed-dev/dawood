<?php

namespace App\Http\Requests;

use App\Casts\MoneyCast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'creditor' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:'.MoneyCast::validationPattern()],
            'incurred_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:incurred_at'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
