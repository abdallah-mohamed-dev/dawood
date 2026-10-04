<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The settings page renders one name form per material type. Each one
     * gets its own error bag so a failure shows under the row that failed.
     */
    protected function prepareForValidation(): void
    {
        $this->errorBag = 'materialType_'.$this->route('materialType')->getKey();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('material_types', 'name')->ignore($this->route('materialType')),
            ],
        ];
    }
}
