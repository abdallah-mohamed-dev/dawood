<?php

use App\Casts\QuantityCast;
use App\Models\User;

beforeEach(function () {
    $this->cast = new QuantityCast;
    $this->model = new User;
});

it('reads a stored quantity back as a whole number of units', function () {
    expect($this->cast->get($this->model, 'quantity', 14, []))->toBe('14');
});

it('writes a whole number string as the same integer', function () {
    expect($this->cast->set($this->model, 'quantity', '14', []))->toBe(14);
});

it('passes integers straight through on set', function () {
    expect($this->cast->set($this->model, 'quantity', 14, []))->toBe(14);
});

it('handles zero', function () {
    expect($this->cast->get($this->model, 'quantity', 0, []))->toBe('0');
    expect($this->cast->set($this->model, 'quantity', '0', []))->toBe(0);
});

it('handles null on both directions', function () {
    expect($this->cast->get($this->model, 'quantity', null, []))->toBeNull();
    expect($this->cast->set($this->model, 'quantity', null, []))->toBeNull();
});

it('handles negative quantities', function () {
    expect($this->cast->get($this->model, 'quantity', -14, []))->toBe('-14');
    expect($this->cast->set($this->model, 'quantity', '-14', []))->toBe(-14);
});

it('never renders a decimal point, whatever the quantity', function () {
    foreach ([0, 7, 14, 1_000, 1_234_567, -25] as $quantity) {
        expect(QuantityCast::toDecimalString($quantity))->not->toContain('.');
        expect(QuantityCast::toDisplayString($quantity))->not->toContain('.');
    }
});

it('groups thousands on display without a fraction', function () {
    expect(QuantityCast::toDisplayString(12_500))->toBe('12,500');
    expect(QuantityCast::toDisplayString(-1_250))->toBe('-1,250');
});

it('rounds a stray fraction half up rather than truncating it', function () {
    // Validation rejects a decimal before it reaches the cast; this is the
    // safety net for anything that gets here by another road.
    expect($this->cast->set($this->model, 'quantity', '2.5', []))->toBe(3);
    expect($this->cast->set($this->model, 'quantity', '2.4', []))->toBe(2);
});

it('rejects float input to prevent floating point drift', function () {
    $this->cast->set($this->model, 'quantity', 2.5, []);
})->throws(InvalidArgumentException::class);

it('rejects scientific notation instead of silently truncating it', function () {
    $this->cast->set($this->model, 'quantity', '1e10', []);
})->throws(InvalidArgumentException::class);

it('rejects non-numeric garbage instead of silently coercing it to zero', function () {
    $this->cast->set($this->model, 'quantity', 'not-a-number', []);
})->throws(InvalidArgumentException::class);

it('exposes a validation regex that refuses any fraction at all', function () {
    expect(QuantityCast::validationPattern())->toBe('/^\d+$/');
    expect(preg_match(QuantityCast::validationPattern(), '14'))->toBe(1);
    expect(preg_match(QuantityCast::validationPattern(), '14.000'))->toBe(0);
    expect(preg_match(QuantityCast::validationPattern(), '14.5'))->toBe(0);
    expect(preg_match(QuantityCast::validationPattern(), '1e10'))->toBe(0);
});
