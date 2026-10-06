<?php

use App\Models\User;

/**
 * Every font, stylesheet and script is served from this app (public/build),
 * never from a CDN. These tests read the rendered HTML, so a regression in a
 * layout shows up here, not in the browser.
 */
test('the app layout ships the local IBM Plex Sans Arabic font faces', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->getContent();

    expect($html)
        ->toContain('@font-face')
        ->toContain('IBM Plex Sans Arabic')
        ->toContain('/build/assets/ibm-plex-sans-arabic-');
});

test('the guest layout ships the same local font', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->toContain('/build/assets/ibm-plex-sans-arabic-');
});

test('no page loads anything from an external host', function (string $route) {
    $html = $this->actingAs(User::factory()->create())->get(route($route))->assertOk()->getContent();

    expect($html)
        ->not->toContain('fonts.googleapis')
        ->not->toContain('fonts.gstatic')
        ->not->toContain('cdn.jsdelivr')
        ->not->toContain('unpkg.com')
        ->not->toContain('cdnjs.cloudflare');
})->with(['dashboard', 'customers.index', 'rooms.index', 'cashbox.index', 'reports.profit']);
