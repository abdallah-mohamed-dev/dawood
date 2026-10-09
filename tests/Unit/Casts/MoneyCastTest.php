<?php

use App\Casts\MoneyCast;
use App\Models\User;

beforeEach(function () {
    $this->cast = new MoneyCast;
    $this->model = new User;
});

it('reads a stored amount back as a whole number of pounds', function () {
    expect($this->cast->get($this->model, 'amount', 540, []))->toBe('540');
});

it('writes a whole number string as the same integer', function () {
    expect($this->cast->set($this->model, 'amount', '540', []))->toBe(540);
});

it('passes integers straight through on set', function () {
    expect($this->cast->set($this->model, 'amount', 540, []))->toBe(540);
});

it('handles zero', function () {
    expect($this->cast->get($this->model, 'amount', 0, []))->toBe('0');
    expect($this->cast->set($this->model, 'amount', '0', []))->toBe(0);
});

it('handles null on both directions', function () {
    expect($this->cast->get($this->model, 'amount', null, []))->toBeNull();
    expect($this->cast->set($this->model, 'amount', null, []))->toBeNull();
});

it('handles negative amounts', function () {
    expect($this->cast->get($this->model, 'amount', -540, []))->toBe('-540');
    expect($this->cast->set($this->model, 'amount', '-540', []))->toBe(-540);
});

it('never renders a decimal point, whatever the amount', function () {
    foreach ([0, 7, 540, 7_000, 1_234_567, -8_727] as $amount) {
        expect(MoneyCast::toDecimalString($amount))->not->toContain('.');
        expect(MoneyCast::toDisplayString($amount))->not->toContain('.');
    }
});

it('groups thousands on display without a fraction', function () {
    expect(MoneyCast::toDisplayString(85_000))->toBe('85,000');
    expect(MoneyCast::toDisplayString(-8_727))->toBe('-8,727');
});

it('rounds a stray fraction half up rather than truncating it', function () {
    // Validation rejects a decimal before it reaches the cast; this is the
    // safety net for anything that gets here by another road.
    expect($this->cast->set($this->model, 'amount', '540.5', []))->toBe(541);
    expect($this->cast->set($this->model, 'amount', '540.4', []))->toBe(540);
});

it('rejects float input to prevent floating point drift', function () {
    $this->cast->set($this->model, 'amount', 540.5, []);
})->throws(InvalidArgumentException::class);

it('rejects scientific notation instead of silently truncating it', function () {
    $this->cast->set($this->model, 'amount', '1e10', []);
})->throws(InvalidArgumentException::class);

it('rejects non-numeric garbage instead of silently coercing it to zero', function () {
    $this->cast->set($this->model, 'amount', 'not-a-number', []);
})->throws(InvalidArgumentException::class);

it('rejects a value with multiple decimal points', function () {
    $this->cast->set($this->model, 'amount', '1.2.3', []);
})->throws(InvalidArgumentException::class);

it('exposes a validation regex that refuses any fraction at all', function () {
    expect(MoneyCast::validationPattern())->toBe('/^\d+$/');
    expect(preg_match(MoneyCast::validationPattern(), '540'))->toBe(1);
    expect(preg_match(MoneyCast::validationPattern(), '540.'.'00'))->toBe(0);
    expect(preg_match(MoneyCast::validationPattern(), '540.5'))->toBe(0);
    expect(preg_match(MoneyCast::validationPattern(), '1e10'))->toBe(0);
});

it('rejects a magnitude too large to represent safely', function () {
    // 16 digits — one over the safe limit.
    $this->cast->set($this->model, 'amount', '9999999999999999', []);
})->throws(InvalidArgumentException::class);

it('accepts a large but safely representable magnitude', function () {
    expect($this->cast->set($this->model, 'amount', '999999999999999', []))
        ->toBe(999999999999999);
});
