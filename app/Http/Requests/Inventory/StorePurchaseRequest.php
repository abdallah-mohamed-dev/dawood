<?php

namespace App\Http\Requests\Inventory;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'quantity' => ['required', 'regex:'.QuantityCast::validationPattern()],
            'unit_cost' => ['required', 'regex:'.MoneyCast::validationPattern()],
            'purchase_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
