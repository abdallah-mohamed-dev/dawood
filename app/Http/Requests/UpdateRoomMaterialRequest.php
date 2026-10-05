<?php

namespace App\Http\Requests;

use App\Casts\QuantityCast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomMaterialRequest extends FormRequest
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
            'required_quantity' => ['required', 'regex:'.QuantityCast::validationPattern(), 'not_regex:/^[0.]*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required_quantity.required' => 'حقل الكمية المطلوبة مطلوب.',
            'required_quantity.regex' => 'قيمة الكمية غير صالحة.',
            'required_quantity.not_regex' => 'الكمية لازم تكون أكبر من صفر.',
        ];
    }
}
