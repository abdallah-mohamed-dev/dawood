<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    /**
     * Edited inline in its row, on a page that also carries the add form with
     * the same field names — its own bag keeps a rejected row save from
     * lighting up the add form too (specs/024).
     *
     * @var string
     */
    protected $errorBag = 'row';

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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories', 'name')->ignore($this->route('category')),
            ],
        ];
    }
}
