<?php

namespace App\Http\Requests;

use App\Casts\MoneyCast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCapitalItemRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // not_regex rules out "0" and "0.00" — the pattern already rejects negatives.
            'amount' => ['required', 'regex:'.MoneyCast::validationPattern(), 'not_regex:/^[0.]*$/'],
            'occurred_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم البند مطلوب.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.regex' => 'قيمة المبلغ غير صالحة.',
            'amount.not_regex' => 'المبلغ لازم يكون أكبر من صفر.',
            'occurred_at.required' => 'التاريخ مطلوب.',
            'occurred_at.date' => 'التاريخ غير صالح.',
        ];
    }
}
