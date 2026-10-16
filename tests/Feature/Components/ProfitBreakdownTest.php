<?php

use Illuminate\Support\Facades\Blade;

/**
 * The profit report's revenue → net chart: every bar starts from zero and is
 * sized against the same scale, so the lengths compare directly. It replaced
 * a waterfall whose floating mid-air bars read badly.
 */
function breakdown(int $revenue, array $deductions, int $net): string
{
    return Blade::render(
        '<x-charts.profit-breakdown :revenue="$revenue" :deductions="$deductions" :net="$net" />',
        compact('revenue', 'deductions', 'net'),
    );
}

test('each bar starts from zero, sized against the same scale', function () {
    $html = breakdown(1000, [
        ['label' => 'تكلفة الخامات', 'value' => 400],
        ['label' => 'مصروفات إدارية', 'value' => 100],
    ], 500);

    // Plain widths from zero: revenue full, materials 40%, admin 10%, net 50%.
    expect($html)->toContain('style="width: 100%"')
        ->toContain('style="width: 40%"')
        ->toContain('style="width: 10%"')
        ->toContain('style="width: 50%"');

    // Each deduction shows its share of revenue.
    expect($html)->toContain('(40٪)')->toContain('(10٪)');
});

test('a zero deduction gets no bar but stays in the table', function () {
    $html = breakdown(1000, [
        ['label' => 'تكلفة الخامات', 'value' => 400],
        ['label' => 'غرف ملغاة', 'value' => 0],
    ], 600);

    // Once in the table only — not as a bar row.
    expect(substr_count($html, 'غرف ملغاة'))->toBe(1);
    expect(substr_count($html, 'style="width:'))->toBe(3); // revenue, materials, net
});

test('a loss is drawn in the danger colour', function () {
    $html = breakdown(1000, [['label' => 'تكلفة الخامات', 'value' => 1500]], -500);

    expect($html)->toContain('bg-danger')->not->toContain('bg-success');
});

test('no data at all shows a message instead of empty bars', function () {
    expect(breakdown(0, [['label' => 'تكلفة الخامات', 'value' => 0]], 0))
        ->toContain('مفيش بيانات في الفترة دي.');
});
