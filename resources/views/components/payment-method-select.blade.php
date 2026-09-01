@props(['name' => 'payment_method', 'selected' => null, 'bag' => 'default', 'id' => null])

@php
    $inputId = $id ?? $name;
    $default = $selected?->value ?? \App\Enums\PaymentMethod::Cash->value;

    // This component appears three times on the room page (labour, extra
    // expense, customer payment), all with a field called `payment_method`.
    // old() is global to the request, so reading it unguarded would apply one
    // form's choice to the other two. A form only repopulates when its own
    // error bag is the one carrying messages — i.e. when it is the form that
    // actually failed.
    $isFailedForm = $errors->getBag($bag)->isNotEmpty();
    $current = $isFailedForm ? old($name, $default) : $default;
@endphp

<div>
    <label for="{{ $inputId }}" class="mb-1 block text-xs font-medium text-gray-700">طريقة الدفع</label>
    <select
        id="{{ $inputId }}"
        name="{{ $name }}"
        required
        class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
    >
        @foreach (\App\Enums\PaymentMethod::cases() as $method)
            <option value="{{ $method->value }}" @selected($current === $method->value)>{{ $method->label() }}</option>
        @endforeach
    </select>
    @error($name, $bag)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
