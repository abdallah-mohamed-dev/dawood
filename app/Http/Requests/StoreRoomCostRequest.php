<?php

namespace App\Http\Requests;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The room page carries four separate forms, and both the customer
     * payment form and this one have a field called `amount`. Without a
     * per-type error bag, a bad labour amount renders its message inside
     * the payment form instead. Set here (not as a static property)
     * because the bag depends on which of the two sections posted.
     */
    protected function prepareForValidation(): void
    {
        $type = RoomCostType::tryFrom($this->string('type')->toString());

        $this->errorBag = 'roomCost_'.($type?->value ?? 'labor');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(RoomCostType::class)],
            'amount' => ['required', 'regex:'.MoneyCast::validationPattern()],
            'occurred_at' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
