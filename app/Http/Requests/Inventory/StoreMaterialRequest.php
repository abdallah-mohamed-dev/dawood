<?php

namespace App\Http\Requests\Inventory;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
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
                Rule::unique('materials', 'name'),
            ],
            'unit' => ['required', 'string', 'max:50'],
            'material_type_id' => ['required', 'exists:material_types,id'],
            // regex: the pattern itself rejects a leading minus, so a negative
            // price never reaches MoneyCast. not_in:0 then rules out "0" and
            // "0.00" — the one spelling gt:0 would let through, since Laravel
            // compares a numeric field as a NUMBER and (float)"0.00" === 0.0.
            'unit_price' => ['required', 'regex:'.MoneyCast::validationPattern(), 'not_in:0', 'not_regex:/^[0.]*$/'],
            // Optional here (14.4.11 "الكمية المبدئية اختيارية"): a material
            // can be catalogued before anything is bought for it. The pattern
            // rejects a negative quantity; zero is a legal "no stock yet".
            'quantity' => ['sometimes', 'regex:'.QuantityCast::validationPattern()],
            // No payment_method rule: buying stock is always cash (specs/023),
            // so the form never asks and the controller never reads a choice.
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
