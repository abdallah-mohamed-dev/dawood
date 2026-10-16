<?php

use Illuminate\Support\Facades\Blade;

/**
 * The app is RTL, and an SVG <text> follows the page direction: anchored at
 * the start of the right-hand padding, the axis numbers grew leftwards over
 * the first bar (seen on the profit report's waterfall). Each value-axis label
 * must be pinned LTR so it grows into the padding instead.
 */
test('value-axis labels are pinned left-to-right', function (string $blade) {
    $svg = Blade::render($blade);

    preg_match_all('/<text[^>]*tabular-nums[^>]*>/', $svg, $labels);

    expect($labels[0])->not->toBeEmpty();

    foreach ($labels[0] as $label) {
        expect($label)->toContain('direction="ltr"')->toContain('text-anchor="start"');
    }
})->with([
    'waterfall' => ['<x-charts.waterfall :steps="[[\'label\' => \'الإيراد\', \'value\' => 265000, \'type\' => \'total\'], [\'label\' => \'الخامات\', \'value\' => -90000, \'type\' => \'change\']]" />'],
    'area line' => ['<x-charts.area-line :labels="[\'1\', \'2\']" :values="[1000, 5000]" />'],
    'grouped bars' => ['<x-charts.grouped-bars :labels="[\'1\', \'2\']" :series="[[\'name\' => \'داخل\', \'token\' => \'chart-a\', \'values\' => [1000, 3000]], [\'name\' => \'خارج\', \'token\' => \'chart-b\', \'values\' => [500, 800]]]" />'],
]);

test('the waterfall draws its closing total from zero and no bar for a zero change', function () {
    $svg = Blade::render('<x-charts.waterfall :steps="$steps" />', ['steps' => [
        ['label' => 'الإيراد', 'value' => 1000, 'type' => 'total'],
        ['label' => 'الخامات', 'value' => -400, 'type' => 'change'],
        ['label' => 'غرف ملغاة', 'value' => 0, 'type' => 'change'],
        ['label' => 'صافي الربح', 'value' => 600, 'type' => 'total'],
    ]]);

    preg_match_all('/<rect[^>]*height="([\d.]+)"/', $svg, $heights);

    // Revenue, materials, net — the zero change has a label but no bar.
    expect($heights[1])->toHaveCount(3);
    expect($svg)->toContain('غرف ملغاة');

    // Plot is 200 units tall for 0..1000: the net bar is 600 → 120 units,
    // not the 2-unit sliver it was when drawn from the running total.
    expect((float) $heights[1][2])->toBe(120.0);
});
