<?php

namespace App\Http\Requests;

use App\Casts\MoneyCast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDebtRequest extends FormRequest
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
            'creditor' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:'.MoneyCast::validationPattern()],
            'incurred_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:incurred_at'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
