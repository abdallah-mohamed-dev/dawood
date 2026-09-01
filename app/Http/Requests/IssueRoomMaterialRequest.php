<?php

namespace App\Http\Requests;

use App\Casts\QuantityCast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IssueRoomMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The room page renders one of these forms per material row, all with a
     * field called `quantity`. A shared error bag would print the same
     * message under every row, so each row gets its own bag keyed by the
     * room_material it belongs to.
     */
    protected function prepareForValidation(): void
    {
        $this->errorBag = 'issue_'.$this->route('roomMaterial')->getKey();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'regex:'.QuantityCast::validationPattern()],
        ];
    }
}
