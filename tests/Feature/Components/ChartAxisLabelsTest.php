<?php

use Illuminate\Support\Facades\Blade;

/**
 * The app is RTL, and an SVG <text> follows the page direction: anchored at
 * the start of the right-hand padding, the axis numbers grew leftwards over
 * the first bar (seen on the old profit-report chart). Each value-axis label
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
    'area line' => ['<x-charts.area-line :labels="[\'1\', \'2\']" :values="[1000, 5000]" />'],
    'grouped bars' => ['<x-charts.grouped-bars :labels="[\'1\', \'2\']" :series="[[\'name\' => \'داخل\', \'token\' => \'chart-a\', \'values\' => [1000, 3000]], [\'name\' => \'خارج\', \'token\' => \'chart-b\', \'values\' => [500, 800]]]" />'],
]);
