<?php

namespace App\Http\Requests;

use App\Casts\MoneyCast;
use App\Models\Partner;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class StorePartnerRequest extends FormRequest
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
            'percentage' => [
                'required',
                'regex:'.MoneyCast::validationPattern(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        $newPercentage = MoneyCast::toScaledInt((string) $value);
                    } catch (InvalidArgumentException) {
                        $fail('قيمة النسبة غير صالحة.');

                        return;
                    }

                    if ($newPercentage <= 0) {
                        $fail('يجب أن تكون النسبة أكبر من صفر.');

                        return;
                    }

                    $existingTotal = (int) Partner::query()->sum('percentage');

                    if ($existingTotal + $newPercentage > 10_000) {
                        $fail('مجموع نسب الشركاء لا يجوز أن يتجاوز 100%.');
                    }
                },
            ],
        ];
    }
}
