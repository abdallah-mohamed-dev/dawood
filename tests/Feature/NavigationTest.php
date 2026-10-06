<?php

use App\Models\User;

test('the sidebar lists the pages in the agreed group order', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->getContent();

    $labels = ['العملاء', 'الغرف', 'المخزن', 'الخامات', 'المشتريات', 'حركات المخزون', 'الفلوس', 'المدفوعات', 'المصروفات الإدارية', 'الديون', 'الخزنة', 'الإدارة', 'تقارير الربح', 'المصنعيات', 'المواسم', 'الشركاء', 'رأس المال'];

    $nav = substr($html, strpos($html, '<nav'), strpos($html, '</nav>') - strpos($html, '<nav'));
    $positions = array_map(fn (string $label) => strpos($nav, $label), $labels);

    expect(in_array(false, $positions, true))->toBeFalse();
    expect($positions)->toBe(collect($positions)->sort()->values()->all());
});

test('the sidebar does not list the dashboard or the old movement log', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->getContent();

    $nav = substr($html, strpos($html, '<nav'), strpos($html, '</nav>') - strpos($html, '<nav'));

    expect($nav)->not->toContain('لوحة التحكم');
    expect($nav)->not->toContain('سجل المخزن');
});

test('every sidebar link opens with a 200', function () {
    $user = User::factory()->create();

    foreach (['customers.index', 'inventory.materials.index', 'rooms.index', 'inventory.shortages.index', 'inventory.movements.index', 'payments.index', 'expenses.index', 'debts.index', 'cashbox.index', 'reports.profit', 'reports.labor', 'seasons.index', 'partners.index', 'capital.index', 'backup.index', 'settings.index'] as $route) {
        $this->actingAs($user)->get(route($route))->assertOk();
    }
});
