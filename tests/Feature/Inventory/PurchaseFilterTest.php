<?php

use App\Models\InventoryBatch;
use App\Models\Material;
use App\Models\Room;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\InventoryService;
use App\Services\RoomMaterialService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->inventory = app(InventoryService::class);
});

function purchasesPage(array $filters = []): string
{
    return test()->actingAs(test()->admin)
        ->get(route('inventory.purchases.index', $filters))
        ->assertOk()
        ->getContent();
}

/**
 * Only the results table. The add form above it lists every material in a
 * dropdown, so searching the whole page for a material name would always
 * match regardless of the filter.
 */
function purchasesTable(array $filters = []): string
{
    $html = purchasesPage($filters);

    return substr($html, strpos($html, 'overflow-x-auto rounded-xl'));
}

test('searching by material name filters the table', function () {
    $wood = Material::factory()->create(['name' => 'خشب زان']);
    $nails = Material::factory()->create(['name' => 'مسامير']);
    $this->inventory->purchase($wood, 10_000, 10_000, '2026-01-01');
    $this->inventory->purchase($nails, 10_000, 5_000, '2026-01-01');

    $table = purchasesTable(['q' => 'خشب']);

    expect($table)->toContain('خشب زان');
    expect($table)->not->toContain('مسامير');
});

test('a date range filters the table on both ends', function () {
    $material = Material::factory()->create(['name' => 'خشب زان']);
    $this->inventory->purchase($material, 1_000, 10_000, '2026-01-15');
    $this->inventory->purchase($material, 2_000, 10_000, '2026-02-15');
    $this->inventory->purchase($material, 3_000, 10_000, '2026-03-15');

    expect(purchasesPage(['from' => '2026-02-01', 'to' => '2026-02-28']))->toContain('<strong>1</strong>');
    expect(purchasesPage(['from' => '2026-02-01']))->toContain('<strong>2</strong>');
    expect(purchasesPage(['to' => '2026-02-28']))->toContain('<strong>2</strong>');
});

test('the batch status filter separates untouched, partial and depleted batches', function () {
    $material = Material::factory()->create();
    $roomMaterials = app(RoomMaterialService::class);
    $room = Room::factory()->create();

    // Three batches: the first will be fully issued, the second partially,
    // the third left alone. FIFO drains them in purchase order.
    $this->inventory->purchase($material, 5_000, 10_000, '2026-01-01');
    $this->inventory->purchase($material, 5_000, 10_000, '2026-01-02');
    $this->inventory->purchase($material, 5_000, 10_000, '2026-01-03');

    $requirement = $roomMaterials->addRequirement($room, $material, 15_000);
    $roomMaterials->issue($requirement, 7_000, '2026-01-04'); // 5 + 2

    expect(purchasesPage(['status' => 'depleted']))->toContain('<strong>1</strong>');
    expect(purchasesPage(['status' => 'partial']))->toContain('<strong>1</strong>');
    expect(purchasesPage(['status' => 'available']))->toContain('<strong>1</strong>');
});

test('filters combine rather than override each other', function () {
    $wood = Material::factory()->create(['name' => 'خشب زان']);
    $nails = Material::factory()->create(['name' => 'مسامير']);
    $this->inventory->purchase($wood, 1_000, 10_000, '2026-01-15');
    $this->inventory->purchase($wood, 1_000, 10_000, '2026-03-15');
    $this->inventory->purchase($nails, 1_000, 10_000, '2026-01-15');

    // Wood in January only: one of the three.
    expect(purchasesPage(['q' => 'خشب', 'from' => '2026-01-01', 'to' => '2026-01-31']))
        ->toContain('<strong>1</strong>');
});

test('a filter survives moving to the second page', function () {
    $wood = Material::factory()->create(['name' => 'خشب زان']);
    Material::factory()->create(['name' => 'مسامير']);

    foreach (range(1, 30) as $i) {
        $this->inventory->purchase($wood, 1_000, 10_000, '2026-01-01');
    }

    // withQueryString() carries the filter into the page links.
    expect(purchasesPage(['q' => 'خشب']))->toContain('q=%D8%AE%D8%B4%D8%A8');
});

test('the summary counts and totals the whole filtered set, not just the visible page', function () {
    $material = Material::factory()->create();

    // 30 purchases of 10 units @ 100.00 EGP = 1,000.00 EGP each.
    foreach (range(1, 30) as $i) {
        $this->inventory->purchase($material, 10_000, 10_000, '2026-01-01');
    }

    $html = purchasesPage();

    // The table shows 25 rows; the summary must describe all 30.
    expect($html)->toContain('<strong>30</strong>');
    expect($html)->toContain('30,000.00');
});

test('the summary total matches what the cashbox was charged for the same purchases', function () {
    $material = Material::factory()->create();

    // Deliberately awkward numbers so a second, differently-rounded copy of
    // the cost formula would drift from the cashbox.
    $this->inventory->purchase($material, 3_333, 777, '2026-01-01');
    $this->inventory->purchase($material, 1_111, 999, '2026-01-02');
    $this->inventory->purchase($material, 7_777, 333, '2026-01-03');

    $summary = app(InventoryService::class)->purchasesSummary(InventoryBatch::query());

    expect($summary['count'])->toBe(3);
    expect($summary['total'])->toBe(app(CashboxService::class)->totalOut());
});

test('the summary follows the filters', function () {
    $wood = Material::factory()->create(['name' => 'خشب زان']);
    $nails = Material::factory()->create(['name' => 'مسامير']);
    $this->inventory->purchase($wood, 10_000, 10_000, '2026-01-01');  // 1,000.00
    $this->inventory->purchase($nails, 10_000, 50_000, '2026-01-01'); // 5,000.00

    expect(purchasesPage())->toContain('6,000.00');
    expect(purchasesPage(['q' => 'خشب']))->toContain('1,000.00');
});

test('a filter with no matches says so instead of showing the generic empty message', function () {
    $material = Material::factory()->create(['name' => 'خشب زان']);
    $this->inventory->purchase($material, 1_000, 10_000, '2026-01-01');

    expect(purchasesTable(['q' => 'لا يوجد']))->toContain('لا توجد عمليات شراء مطابقة للفلاتر.');
});

test('an unknown status value is ignored rather than emptying the table', function () {
    $material = Material::factory()->create(['name' => 'خشب زان']);
    $this->inventory->purchase($material, 1_000, 10_000, '2026-01-01');

    expect(purchasesTable(['status' => 'nonsense']))->toContain('خشب زان');
});
