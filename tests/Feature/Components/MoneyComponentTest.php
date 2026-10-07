<?php

use App\Casts\MoneyCast;

function renderMoney(int|string $amount): string
{
    return trim(view('components.money', ['amount' => $amount])->render());
}

/**
 * What the eye reads, with the markup taken out — the currency sits in its
 * own lighter span, so the raw HTML is not one contiguous string any more.
 */
function renderMoneyText(int|string $amount): string
{
    return trim(strip_tags(renderMoney($amount)));
}

it('formats a raw scaled integer with thousands separators', function () {
    expect(renderMoneyText(1254000))->toContain('12,540.00 ج.م');
});

it('formats the MoneyCast decimal-string output identically to the raw integer', function () {
    $raw = 1254000;
    $castOutput = MoneyCast::toDecimalString($raw);

    expect($castOutput)->toBe('12540.00');
    expect(renderMoney($castOutput))->toBe(renderMoney($raw));
});

it('formats zero', function () {
    expect(renderMoneyText(0))->toContain('0.00 ج.م');
});

it('formats negative amounts from both input forms identically', function () {
    expect(renderMoneyText(-54000))->toContain('-540.00 ج.م');
    expect(renderMoney('-540.00'))->toBe(renderMoney(-54000));
});
