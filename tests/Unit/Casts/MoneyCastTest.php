<?php

use App\Casts\MoneyCast;
use App\Models\User;

beforeEach(function () {
    $this->cast = new MoneyCast;
    $this->model = new User;
});

it('converts a scaled integer into a decimal string', function () {
    expect($this->cast->get($this->model, 'amount', 54000, []))->toBe('540.00');
});

it('converts a decimal string into a scaled integer', function () {
    expect($this->cast->set($this->model, 'amount', '540.00', []))->toBe(54000);
});

it('round-trips whole numbers', function () {
    expect($this->cast->set($this->model, 'amount', '540', []))->toBe(54000);
});

it('passes already-scaled integers straight through on set', function () {
    expect($this->cast->set($this->model, 'amount', 54000, []))->toBe(54000);
});

it('handles zero', function () {
    expect($this->cast->get($this->model, 'amount', 0, []))->toBe('0.00');
    expect($this->cast->set($this->model, 'amount', '0.00', []))->toBe(0);
});

it('handles null on both directions', function () {
    expect($this->cast->get($this->model, 'amount', null, []))->toBeNull();
    expect($this->cast->set($this->model, 'amount', null, []))->toBeNull();
});

it('handles negative amounts', function () {
    expect($this->cast->get($this->model, 'amount', -54000, []))->toBe('-540.00');
    expect($this->cast->set($this->model, 'amount', '-540.00', []))->toBe(-54000);
});

it('rounds half up on the third decimal digit', function () {
    expect($this->cast->set($this->model, 'amount', '540.005', []))->toBe(54001);
    expect($this->cast->set($this->model, 'amount', '540.004', []))->toBe(54000);
});

it('formats a single-digit piastre value with a leading zero', function () {
    expect($this->cast->get($this->model, 'amount', 54005, []))->toBe('540.05');
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

it('exposes a validation regex matching its own precision', function () {
    expect(MoneyCast::validationPattern())->toBe('/^\d+(\.\d{1,2})?$/');
    expect(preg_match(MoneyCast::validationPattern(), '540.00'))->toBe(1);
    expect(preg_match(MoneyCast::validationPattern(), '540.005'))->toBe(0);
    expect(preg_match(MoneyCast::validationPattern(), '1e10'))->toBe(0);
});

it('rejects a magnitude too large to scale safely instead of silently overflowing into a float', function () {
    // 16 digits — one over the safe limit that keeps whole × scale within PHP_INT_MAX.
    $this->cast->set($this->model, 'amount', '9999999999999999.00', []);
})->throws(InvalidArgumentException::class);

it('accepts a large but safely representable magnitude', function () {
    expect($this->cast->set($this->model, 'amount', '999999999999999.00', []))
        ->toBe(99999999999999900);
});
