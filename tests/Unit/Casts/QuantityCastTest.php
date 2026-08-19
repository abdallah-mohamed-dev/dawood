<?php

use App\Casts\QuantityCast;
use App\Models\User;

beforeEach(function () {
    $this->cast = new QuantityCast;
    $this->model = new User;
});

it('converts a scaled integer into a decimal string', function () {
    expect($this->cast->get($this->model, 'quantity', 2500, []))->toBe('2.500');
});

it('converts a decimal string into a scaled integer', function () {
    expect($this->cast->set($this->model, 'quantity', '2.5', []))->toBe(2500);
});

it('round-trips whole numbers', function () {
    expect($this->cast->set($this->model, 'quantity', '13', []))->toBe(13000);
});

it('passes already-scaled integers straight through on set', function () {
    expect($this->cast->set($this->model, 'quantity', 2500, []))->toBe(2500);
});

it('handles zero', function () {
    expect($this->cast->get($this->model, 'quantity', 0, []))->toBe('0.000');
    expect($this->cast->set($this->model, 'quantity', '0', []))->toBe(0);
});

it('handles null on both directions', function () {
    expect($this->cast->get($this->model, 'quantity', null, []))->toBeNull();
    expect($this->cast->set($this->model, 'quantity', null, []))->toBeNull();
});

it('handles negative quantities', function () {
    expect($this->cast->get($this->model, 'quantity', -2500, []))->toBe('-2.500');
    expect($this->cast->set($this->model, 'quantity', '-2.5', []))->toBe(-2500);
});

it('rounds half up on the fourth decimal digit', function () {
    expect($this->cast->set($this->model, 'quantity', '2.5005', []))->toBe(2501);
    expect($this->cast->set($this->model, 'quantity', '2.5004', []))->toBe(2500);
});

it('rejects float input to prevent floating point drift', function () {
    $this->cast->set($this->model, 'quantity', 2.5, []);
})->throws(InvalidArgumentException::class);

it('exposes a validation regex matching its own precision', function () {
    expect(QuantityCast::validationPattern())->toBe('/^\d+(\.\d{1,3})?$/');
    expect(preg_match(QuantityCast::validationPattern(), '2.500'))->toBe(1);
    expect(preg_match(QuantityCast::validationPattern(), '2.5000'))->toBe(0);
});
