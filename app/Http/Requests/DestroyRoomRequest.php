<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * If the room has any issued materials, the confirmation modal's choice
 * (return to stock vs. treat as consumed) is mandatory — see
 * docs/customers-and-rooms.md. Otherwise there is nothing to choose.
 */
class DestroyRoomRequest extends FormRequest
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
        $hasIssuedMaterials = $this->route('room')->hasIssuedMaterials();

        return [
            'return_materials' => $hasIssuedMaterials ? ['required', 'boolean'] : ['nullable', 'boolean'],
        ];
    }
}
