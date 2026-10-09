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

it('formats a whole number of pounds with thousands separators', function () {
    expect(renderMoneyText(12_540))->toContain('12,540 ج.م');
});

it('formats the MoneyCast string output identically to the raw integer', function () {
    $raw = 12_540;
    $castOutput = MoneyCast::toDecimalString($raw);

    expect($castOutput)->toBe('12540');
    expect(renderMoney($castOutput))->toBe(renderMoney($raw));
});

it('formats zero', function () {
    expect(renderMoneyText(0))->toContain('0 ج.م');
});

it('formats negative amounts from both input forms identically', function () {
    expect(renderMoneyText(-540))->toContain('-540 ج.م');
    expect(renderMoney('-540'))->toBe(renderMoney(-540));
});

it('never renders a decimal point in the figure itself', function () {
    // "ج.م" carries a dot of its own, so only the figure is inspected.
    foreach ([0, 7, 540, 12_540, -8_727] as $amount) {
        $figure = trim(str_replace('ج.م', '', renderMoneyText($amount)));

        expect($figure)->not->toContain('.');
    }
});
