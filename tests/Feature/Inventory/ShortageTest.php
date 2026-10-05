<?php

use App\Enums\PaymentMethod;
use App\Enums\RoomStatus;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\RoomMaterialService;
use App\Services\ShortageService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->inventory = app(InventoryService::class);
    $this->roomMaterials = app(RoomMaterialService::class);
    $this->wood = MaterialType::query()->where('name', 'خامة')->value('id');
    $this->accessory = MaterialType::query()->where('name', 'اكسسوار')->value('id');

    // Raw quantities: 10 units = 10000. Price 100.00 EGP = 10000 piastres.
    $this->oak = Material::factory()->create(['name' => 'خشب بلوط', 'material_type_id' => $this->wood, 'unit_price' => 10_000]);
    $this->hinge = Material::factory()->create(['name' => 'مفصلة', 'material_type_id' => $this->accessory, 'unit_price' => 5_000]);
});

function shortageRoom(string $status = 'in_progress', array $attributes = []): Room
{
    return Room::factory()->create(array_merge(['status' => $status, 'customer_id' => Customer::factory()], $attributes));
}

test('a room that needs 10 with 4 in stock is short 6', function () {
    $this->inventory->addStock($this->oak, 4_000, 10_000, '2026-01-01', PaymentMethod::Cash);
    $room = shortageRoom();
    $this->roomMaterials->addRequirement($room, $this->oak, 10_000);

    $group = app(ShortageService::class)->forActiveRooms()->first();

    expect($group['lines']->first()['shortage'])->toBe(6_000);

    $this->actingAs($this->admin)
        ->get(route('inventory.shortages.index'))
        ->assertOk()
        ->assertSee('خشب بلوط');
});

test('issued quantity reduces what is still outstanding before the shortage is worked out', function () {
    $this->inventory->addStock($this->oak, 6_000, 10_000, '2026-01-01', PaymentMethod::Cash);
    $room = shortageRoom();
    $requirement = $this->roomMaterials->addRequirement($room, $this->oak, 10_000);
    $this->roomMaterials->issue($requirement, 4_000, '2026-01-02'); // stock now 2, outstanding now 6

    $group = app(ShortageService::class)->forActiveRooms()->first();

    expect($group['lines']->first()['shortage'])->toBe(4_000);
});

test('a fully stocked room does not appear at all', function () {
    $this->inventory->addStock($this->oak, 10_000, 10_000, '2026-01-01', PaymentMethod::Cash);
    $room = shortageRoom();
    $this->roomMaterials->addRequirement($room, $this->oak, 10_000);

    expect(app(ShortageService::class)->forActiveRooms())->toHaveCount(0);
});

test('two rooms sharing one material: the first gets it, the second is short', function () {
    $this->inventory->addStock($this->oak, 5_000, 10_000, '2026-01-01', PaymentMethod::Cash);
    $first = shortageRoom();
    $second = shortageRoom();
    $this->roomMaterials->addRequirement($first, $this->oak, 5_000);
    $this->roomMaterials->addRequirement($second, $this->oak, 5_000);

    $groups = app(ShortageService::class)->forActiveRooms();

    expect($groups)->toHaveCount(1);
    expect($groups->first()['room']->id)->toBe($second->id);
    expect($groups->first()['lines']->first()['shortage'])->toBe(5_000);
});

test('completed and cancelled rooms never appear', function () {
    // Requirements are added while the rooms are open, then the status moves on.
    foreach ([RoomStatus::Completed, RoomStatus::Cancelled] as $status) {
        $room = shortageRoom();
        $this->roomMaterials->addRequirement($room, $this->oak, 5_000);
        $room->update(['status' => $status]);
    }

    expect(app(ShortageService::class)->forActiveRooms())->toHaveCount(0);
});

test('the estimated cost is the shortage times the current price', function () {
    $this->inventory->addStock($this->oak, 4_000, 10_000, '2026-01-01', PaymentMethod::Cash);
    $room = shortageRoom();
    $this->roomMaterials->addRequirement($room, $this->oak, 10_000);

    $group = app(ShortageService::class)->forActiveRooms()->first();

    // 6 units × 100.00 EGP = 600.00 EGP = 60000 piastres.
    expect($group['lines']->first()['cost'])->toBe(60_000);
    expect($group['total'])->toBe(60_000);
});

test('the grand total is the sum of every room total', function () {
    $this->inventory->addStock($this->oak, 4_000, 10_000, '2026-01-01', PaymentMethod::Cash);
    $this->inventory->addStock($this->hinge, 1_000, 5_000, '2026-01-01', PaymentMethod::Cash);
    $room = shortageRoom();
    $this->roomMaterials->addRequirement($room, $this->oak, 10_000); // short 6 → 60000
    $this->roomMaterials->addRequirement($room, $this->hinge, 3_000); // short 2 → 10000

    $this->actingAs($this->admin)
        ->get(route('inventory.shortages.index'))
        ->assertOk()
        ->assertSee('700.00');
});

test('each filter works alone and the filters work together', function () {
    $other = Customer::factory()->create(['name' => 'عميل تاني']);
    $one = shortageRoom(attributes: ['room_type' => 'مطبخ']);
    $two = shortageRoom(attributes: ['room_type' => 'صالون', 'customer_id' => $other->id]);
    $this->roomMaterials->addRequirement($one, $this->oak, 5_000);
    $this->roomMaterials->addRequirement($two, $this->hinge, 5_000);

    $service = app(ShortageService::class);

    expect($service->forActiveRooms(['room_id' => $one->id])->pluck('room.id')->all())->toBe([$one->id]);
    expect($service->forActiveRooms(['customer_id' => $other->id])->pluck('room.id')->all())->toBe([$two->id]);
    expect($service->forActiveRooms(['material_type_id' => $this->accessory])->pluck('room.id')->all())->toBe([$two->id]);
    expect($service->forActiveRooms(['q' => 'بلوط'])->pluck('room.id')->all())->toBe([$one->id]);
    expect($service->forActiveRooms(['customer_id' => $other->id, 'q' => 'بلوط'])->count())->toBe(0);
});

test('the number of queries does not grow with rooms or materials', function () {
    $count = function (int $rooms, int $materials) {
        for ($r = 0; $r < $rooms; $r++) {
            $room = shortageRoom();
            for ($m = 0; $m < $materials; $m++) {
                $material = Material::factory()->create(['material_type_id' => $this->wood, 'unit_price' => 1_000]);
                $this->roomMaterials->addRequirement($room, $material, 1_000);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('inventory.shortages.index'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $small = $count(2, 2);
    $large = $count(6, 8);

    expect($large)->toBe($small);
});

test('guests are redirected to login', function () {
    $this->get(route('inventory.shortages.index'))->assertRedirect(route('login'));
});

test('the print page shows only the short materials and the total', function () {
    $this->inventory->addStock($this->oak, 4_000, 10_000, '2026-01-01', PaymentMethod::Cash);
    $this->inventory->addStock($this->hinge, 9_000, 5_000, '2026-01-01', PaymentMethod::Cash);
    $room = shortageRoom(attributes: ['room_type' => 'مكتب']);
    $this->roomMaterials->addRequirement($room, $this->oak, 10_000);
    $this->roomMaterials->addRequirement($room, $this->hinge, 3_000); // covered

    $this->actingAs($this->admin)
        ->get(route('inventory.shortages.print', $room))
        ->assertOk()
        ->assertSee('خشب بلوط')
        ->assertDontSee('مفصلة')
        ->assertSee('600.00')
        ->assertDontSee('الشركاء');
});

test('the print page for a room with no shortage says so', function () {
    $room = shortageRoom();

    $this->actingAs($this->admin)
        ->get(route('inventory.shortages.print', $room))
        ->assertOk()
        ->assertSee('مفيش خامات ناقصة للغرفة دي');
});
