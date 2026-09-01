<?php

use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot access materials', function () {
    $this->get(route('inventory.materials.index'))->assertRedirect(route('login'));
});

test('adding a material shows it listed with its unit', function () {
    $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => 'لوح MDF',
        'unit' => 'لوح',
    ])->assertRedirect(route('inventory.materials.index'));

    $response = $this->actingAs($this->admin)->get(route('inventory.materials.index'));

    $response->assertOk()
        ->assertSee('لوح MDF')
        ->assertSee('لوح');
});

test('creating a material without a name fails validation', function () {
    $response = $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => '',
        'unit' => 'لوح',
    ]);

    $response->assertSessionHasErrors('name');
    expect(Material::query()->count())->toBe(0);
});

test('creating a material without a unit fails validation', function () {
    $response = $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => 'لوح MDF',
        'unit' => '',
    ]);

    $response->assertSessionHasErrors('unit');
    expect(Material::query()->count())->toBe(0);
});

test('two materials cannot share the same name anywhere in the system', function () {
    Material::factory()->create(['name' => 'لوح MDF']);

    $response = $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => 'لوح MDF',
        'unit' => 'لوح',
    ]);

    $response->assertSessionHasErrors('name');
    expect(Material::query()->where('name', 'لوح MDF')->count())->toBe(1);
});

test('a material can be edited', function () {
    $material = Material::factory()->create();

    $this->actingAs($this->admin)->put(route('inventory.materials.update', $material), [
        'name' => 'اسم محدث',
        'unit' => 'متر',
    ])->assertRedirect(route('inventory.materials.index'));

    $material->refresh();
    expect($material->name)->toBe('اسم محدث');
    expect($material->unit)->toBe('متر');
});

test('updating a material to its own current name does not fail uniqueness validation', function () {
    $material = Material::factory()->create(['name' => 'لوح MDF']);

    $response = $this->actingAs($this->admin)->put(route('inventory.materials.update', $material), [
        'name' => 'لوح MDF',
        'unit' => $material->unit,
    ]);

    $response->assertSessionDoesntHaveErrors();
});

test('renaming a material to another existing material name fails validation', function () {
    Material::factory()->create(['name' => 'لوح MDF']);
    $material = Material::factory()->create(['name' => 'خشب زان']);

    $response = $this->actingAs($this->admin)->put(route('inventory.materials.update', $material), [
        'name' => 'لوح MDF',
        'unit' => $material->unit,
    ]);

    $response->assertSessionHasErrors('name');
    expect($material->fresh()->name)->toBe('خشب زان');
});

test('the materials page and its nav link are labelled المخزون, not المواد', function () {
    $response = $this->actingAs($this->admin)->get(route('inventory.materials.index'));

    $response->assertOk()
        ->assertSee('المخزون')
        ->assertDontSee('المواد');
});

test('searching by name shows only the matching materials', function () {
    Material::factory()->create(['name' => 'لوح MDF']);
    Material::factory()->create(['name' => 'خشب زان']);

    $response = $this->actingAs($this->admin)->get(route('inventory.materials.index', ['q' => 'زان']));

    $response->assertOk()
        ->assertSee('خشب زان')
        ->assertDontSee('لوح MDF');
});

test('searching matches a partial name anywhere in it', function () {
    Material::factory()->create(['name' => 'لوح MDF مستورد']);

    $response = $this->actingAs($this->admin)->get(route('inventory.materials.index', ['q' => 'MDF']));

    $response->assertOk()->assertSee('لوح MDF مستورد');
});

test('an empty search shows every material', function () {
    Material::factory()->create(['name' => 'لوح MDF']);
    Material::factory()->create(['name' => 'خشب زان']);

    $response = $this->actingAs($this->admin)->get(route('inventory.materials.index', ['q' => '']));

    $response->assertOk()
        ->assertSee('لوح MDF')
        ->assertSee('خشب زان');
});

test('a search with no matches shows a clear message', function () {
    Material::factory()->create(['name' => 'لوح MDF']);

    $response = $this->actingAs($this->admin)->get(route('inventory.materials.index', ['q' => 'حاجة مش موجودة']));

    $response->assertOk()
        ->assertSee('لا توجد مادة بهذا الاسم.')
        ->assertDontSee('لوح MDF');
});

test('a material can be deleted', function () {
    $material = Material::factory()->create();

    $this->actingAs($this->admin)->delete(route('inventory.materials.destroy', $material))
        ->assertRedirect(route('inventory.materials.index'));

    expect(Material::query()->find($material->id))->toBeNull();
});

test('the inventory page paginates at 50 and reaches the second page', function () {
    Material::factory()->count(60)->sequence(fn ($sequence) => [
        'name' => 'مادة '.str_pad((string) ($sequence->index + 1), 3, '0', STR_PAD_LEFT),
    ])->create();

    $first = $this->actingAs($this->admin)->get(route('inventory.materials.index'));
    $first->assertOk()->assertSee('مادة 001')->assertDontSee('مادة 060');

    $this->actingAs($this->admin)
        ->get(route('inventory.materials.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('مادة 060')
        ->assertDontSee('مادة 001');
});

test('a search survives moving to the second page', function () {
    Material::factory()->count(60)->sequence(fn ($sequence) => [
        'name' => 'خشب '.str_pad((string) ($sequence->index + 1), 3, '0', STR_PAD_LEFT),
    ])->create();
    Material::factory()->create(['name' => 'مسامير']);

    $response = $this->actingAs($this->admin)
        ->get(route('inventory.materials.index', ['q' => 'خشب']));

    // withQueryString() puts the active search into the page links, or the
    // second page would silently drop the filter and list everything.
    $response->assertOk()->assertSee('q=%D8%AE%D8%B4%D8%A8', escape: false);

    $this->actingAs($this->admin)
        ->get(route('inventory.materials.index', ['q' => 'خشب', 'page' => 2]))
        ->assertOk()
        ->assertSee('خشب 060')
        ->assertDontSee('مسامير');
});

test('the stock column is only summed for the materials on the current page', function () {
    $inventory = app(InventoryService::class);

    $onPageOne = Material::factory()->create(['name' => 'أ مادة أولى']);
    $inventory->purchase($onPageOne, 5_000, 10_000, '2026-01-01');

    Material::factory()->count(60)->sequence(fn ($sequence) => [
        'name' => 'ب مادة '.str_pad((string) ($sequence->index + 1), 3, '0', STR_PAD_LEFT),
    ])->create();

    $onPageTwo = Material::factory()->create(['name' => 'ي مادة أخيرة']);
    $inventory->purchase($onPageTwo, 7_000, 10_000, '2026-01-01');

    // Page one must not pay for aggregating page two's batches.
    $ids = [];
    DB::listen(function ($query) use (&$ids) {
        if (str_contains($query->sql, 'sum(remaining_quantity)') || str_contains($query->sql, 'SUM(remaining_quantity)')) {
            $ids[] = $query->bindings;
        }
    });

    $this->actingAs($this->admin)->get(route('inventory.materials.index'))->assertOk();

    expect($ids)->not->toBeEmpty();
    expect($ids[0])->toContain($onPageOne->id);
    expect($ids[0])->not->toContain($onPageTwo->id);
});

test('the expense categories page paginates', function () {
    ExpenseCategory::factory()->count(30)->sequence(fn ($sequence) => [
        'name' => 'بند '.str_pad((string) ($sequence->index + 1), 3, '0', STR_PAD_LEFT),
    ])->create();

    $this->actingAs($this->admin)
        ->get(route('expenses.categories.index'))
        ->assertOk()
        ->assertSee('بند 001')
        ->assertDontSee('بند 030');

    $this->actingAs($this->admin)
        ->get(route('expenses.categories.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('بند 030');
});
