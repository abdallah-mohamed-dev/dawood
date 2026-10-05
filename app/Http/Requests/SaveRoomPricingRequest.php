<?php

namespace App\Http\Requests;

use App\Casts\MoneyCast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveRoomPricingRequest extends FormRequest
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
        $amount = ['nullable', 'regex:'.MoneyCast::validationPattern()];

        return [
            'estimated_materials' => $amount,
            'estimated_accessories' => $amount,
            'estimated_labor' => $amount,
            'estimated_other' => $amount,
            'expected_duration_days' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estimated_materials.regex' => 'تقدير الخامات غير صالح.',
            'estimated_accessories.regex' => 'تقدير الاكسسوارات غير صالح.',
            'estimated_labor.regex' => 'تقدير المصنعية غير صالح.',
            'estimated_other.regex' => 'تقدير المصروفات الأخرى غير صالح.',
            'expected_duration_days.integer' => 'مدة التنفيذ لازم تكون رقم صحيح بالأيام.',
            'expected_duration_days.min' => 'مدة التنفيذ لازم تكون يوم واحد على الأقل.',
        ];
    }
}
