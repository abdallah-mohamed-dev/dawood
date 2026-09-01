<?php

namespace App\Http\Requests;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
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
            'amount' => ['required', 'regex:'.MoneyCast::validationPattern()],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
