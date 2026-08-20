<?php

namespace App\Http\Requests;

use App\Casts\QuantityCast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomMaterialRequest extends FormRequest
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
            'material_id' => [
                'required',
                'integer',
                'exists:materials,id',
                Rule::unique('room_materials', 'material_id')->where('room_id', $this->route('room')->id),
            ],
            'required_quantity' => ['required', 'regex:'.QuantityCast::validationPattern()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'material_id.unique' => 'هذه المادة مضافة بالفعل لهذه الغرفة.',
        ];
    }
}
