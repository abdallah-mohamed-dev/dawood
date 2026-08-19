<?php

use App\Casts\MoneyCast;

function renderMoney(int|string $amount): string
{
    return trim(view('components.money', ['amount' => $amount])->render());
}

it('formats a raw scaled integer with thousands separators', function () {
    expect(renderMoney(1254000))->toContain('12,540.00 ج.م');
});

it('formats the MoneyCast decimal-string output identically to the raw integer', function () {
    $raw = 1254000;
    $castOutput = MoneyCast::toDecimalString($raw);

    expect($castOutput)->toBe('12540.00');
    expect(renderMoney($castOutput))->toBe(renderMoney($raw));
});

it('formats zero', function () {
    expect(renderMoney(0))->toContain('0.00 ج.م');
});

it('formats negative amounts from both input forms identically', function () {
    expect(renderMoney(-54000))->toContain('-540.00 ج.م');
    expect(renderMoney('-540.00'))->toBe(renderMoney(-54000));
});
