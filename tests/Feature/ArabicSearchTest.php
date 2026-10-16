<?php

use App\Enums\PaymentMethod;
use App\Enums\RoomStatus;
use App\Models\CapitalItem;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\User;
use App\Services\CustomerPaymentService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\RoomMaterialService;
use App\Support\ArabicSearch;

/**
 * Every search box ignores the spellings people type interchangeably:
 * أ/إ/آ → ا, ة → ه, ى → ي (and tashkeel). Each case types the "wrong"
 * spelling and must still find the stored one — and must not match something
 * unrelated.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('fold() unifies the letters on both sides', function () {
    expect(ArabicSearch::fold('أحمد'))->toBe(ArabicSearch::fold('احمد'));
    expect(ArabicSearch::fold('إيمان'))->toBe(ArabicSearch::fold('ايمان'));
    expect(ArabicSearch::fold('آمال'))->toBe(ArabicSearch::fold('امال'));
    expect(ArabicSearch::fold('مكتبة'))->toBe(ArabicSearch::fold('مكتبه'));
    expect(ArabicSearch::fold('مصطفى'))->toBe(ArabicSearch::fold('مصطفي'));
    expect(ArabicSearch::fold('مُحَمَّد'))->toBe(ArabicSearch::fold('محمد'));
    expect(ArabicSearch::fold('MDF'))->toBe('mdf');
});

test('customers are found whatever the alef', function () {
    Customer::factory()->create(['name' => 'أحمد علي']);
    Customer::factory()->create(['name' => 'سارة إبراهيم']);

    $this->actingAs($this->admin)->get(route('customers.index', ['q' => 'احمد']))
        ->assertSee('أحمد علي')->assertDontSee('سارة إبراهيم');
    $this->actingAs($this->admin)->get(route('customers.index', ['q' => 'ساره ابراهيم']))
        ->assertSee('سارة إبراهيم')->assertDontSee('أحمد علي');
});

test('rooms are found by room type or customer name', function () {
    // Compared on room types: the page's customer filter lists every customer.
    Room::factory()->create(['room_type' => 'مكتبة كتب', 'customer_id' => Customer::factory()->create(['name' => 'عمر'])]);
    Room::factory()->create(['room_type' => 'مطبخ الماستر', 'customer_id' => Customer::factory()->create(['name' => 'رانيا مصطفى'])]);

    $this->actingAs($this->admin)->get(route('rooms.index', ['q' => 'مكتبه', 'season' => 'all']))
        ->assertSee('مكتبة كتب')->assertDontSee('مطبخ الماستر');
    $this->actingAs($this->admin)->get(route('rooms.index', ['q' => 'مصطفي', 'season' => 'all']))
        ->assertSee('مطبخ الماستر')->assertDontSee('مكتبة كتب');
});

test('materials and their movements are found by name', function () {
    $type = MaterialType::query()->where('name', 'اكسسوار')->value('id');
    $hinge = Material::factory()->create(['name' => 'مفصلة هيدروليك', 'material_type_id' => $type]);
    Material::factory()->create(['name' => 'غراء', 'material_type_id' => $type]);
    app(InventoryService::class)->addStock($hinge, 5, 45, '2026-01-01', PaymentMethod::Cash);

    $this->actingAs($this->admin)->get(route('inventory.materials.index', ['q' => 'مفصله']))
        ->assertSee('مفصلة هيدروليك')->assertDontSee('غراء');
    $this->actingAs($this->admin)->get(route('inventory.movements.index', ['q' => 'مفصله']))
        ->assertSee('مفصلة هيدروليك');
});

test('payments are found by customer name', function () {
    $room = Room::factory()->create(['sale_price' => 10_000, 'customer_id' => Customer::factory()->create(['name' => 'إيمان فتحي'])]);
    $other = Room::factory()->create(['sale_price' => 10_000, 'customer_id' => Customer::factory()->create(['name' => 'كريم'])]);
    app(CustomerPaymentService::class)->create($room, 1_000, '2026-01-01');
    app(CustomerPaymentService::class)->create($other, 1_000, '2026-01-01');

    $this->actingAs($this->admin)->get(route('payments.index', ['q' => 'ايمان']))
        ->assertSee('إيمان فتحي')->assertDontSee('كريم');
});

test('expenses and capital items are found by their text', function () {
    $category = ExpenseCategory::factory()->create(['name' => 'مواصلات']);
    app(ExpenseService::class)->create($category, 100, '2026-01-01', 'أجرة عربية', PaymentMethod::Cash);
    app(ExpenseService::class)->create($category, 100, '2026-01-01', 'بنزين', PaymentMethod::Cash);
    CapitalItem::factory()->create(['name' => 'ماكينة شطف', 'note' => 'آلة مستعملة']);
    CapitalItem::factory()->create(['name' => 'شنيور', 'note' => null]);

    $this->actingAs($this->admin)->get(route('expenses.index', ['q' => 'اجره']))
        ->assertSee('أجرة عربية')->assertDontSee('بنزين');
    $this->actingAs($this->admin)->get(route('capital.index', ['q' => 'الة']))
        ->assertSee('ماكينة شطف')->assertDontSee('شنيور');
});

test('the shortages page filters materials by name the same way', function () {
    $type = MaterialType::query()->where('name', 'خامة')->value('id');
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);
    app(RoomMaterialService::class)->addRequirement($room, Material::factory()->create(['name' => 'خشب أرو', 'material_type_id' => $type]), 4);
    app(RoomMaterialService::class)->addRequirement($room, Material::factory()->create(['name' => 'كونتر', 'material_type_id' => $type]), 4);

    $this->actingAs($this->admin)->get(route('inventory.shortages.index', ['q' => 'ارو']))
        ->assertSee('خشب أرو')->assertDontSee('كونتر');
});

test('the activity log is searched by entity name the same way', function () {
    // Model events are on in tests, so creating the customer logs it.
    $this->actingAs($this->admin);
    Customer::factory()->create(['name' => 'آمال']);
    Customer::factory()->create(['name' => 'يوسف']);

    $this->get(route('logs.index', ['q' => 'امال']))
        ->assertSee('آمال')->assertDontSee('يوسف');
});
