<?php

namespace App\Http\Requests;

use App\Casts\QuantityCast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateRoomMaterialRequest extends FormRequest
{
    /**
     * A rejected edit is told at the top of the page, like the controller's
     * own refusals: the row's edit form is closed again after the redirect,
     * and the default bag would have shown the message under the ADD form.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(back()->with('error', $validator->errors()->first()));
    }

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
