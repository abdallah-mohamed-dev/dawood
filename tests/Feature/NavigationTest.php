<?php

use App\Models\User;

test('the sidebar lists the twelve pages in the agreed order', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->getContent();

    $labels = ['العملاء', 'المخزن', 'الغرف', 'المشتريات', 'المدفوعات', 'المصروفات الإدارية', 'الخزنة', 'تقارير الربح', 'المواسم', 'الشركاء', 'رأس المال', 'النسخ الاحتياطي'];

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

    foreach (['customers.index', 'inventory.materials.index', 'rooms.index', 'inventory.shortages.index', 'payments.index', 'expenses.index', 'cashbox.index', 'reports.profit', 'seasons.index', 'partners.index', 'capital.index', 'backup.index'] as $route) {
        $this->actingAs($user)->get(route($route))->assertOk();
    }
});
