<?php

namespace App\Http\Requests\Inventory;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('materials', 'name')->ignore($this->route('material')),
            ],
            'unit' => ['required', 'string', 'max:50'],
            'material_type_id' => ['required', 'exists:material_types,id'],
            // See StoreMaterialRequest for why not_in:0 rather than gt:0.
            'unit_price' => ['required', 'regex:'.MoneyCast::validationPattern(), 'not_in:0', 'not_regex:/^[0.]*$/'],
            // Required on edit — the box shows the new total, and any change
            // from it is a movement. Zero is legal: it empties the material.
            'quantity' => ['required', 'regex:'.QuantityCast::validationPattern()],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_price.regex' => 'قيمة سعر الوحدة غير صالحة.',
            'unit_price.not_in' => 'سعر الوحدة لازم يكون أكبر من صفر.',
            'unit_price.not_regex' => 'سعر الوحدة لازم يكون أكبر من صفر.',
            'unit_price.required' => 'حقل سعر الوحدة مطلوب.',
            'quantity.required' => 'حقل الكمية مطلوب.',
            'name.required' => 'حقل الاسم مطلوب.',
            'unit.required' => 'حقل الوحدة مطلوب.',
            'quantity.regex' => 'قيمة الكمية غير صالحة.',
        ];
    }
}
