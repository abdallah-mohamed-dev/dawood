<?php

namespace App\Http\Requests;

use App\Casts\MoneyCast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'room_type' => ['required', 'string', 'max:255'],
            'sale_price' => ['required', 'regex:'.MoneyCast::validationPattern()],
        ];
    }
}
