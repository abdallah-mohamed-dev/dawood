<?php

use App\Enums\RoomCostType;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->type = MaterialType::query()->where('name', 'خامة')->value('id');
});

function roomWithHistory(int $entities, int $type): Room
{
    $room = Room::factory()->create(['customer_id' => Customer::factory()]);

    for ($i = 0; $i < $entities; $i++) {
        $material = Material::factory()->create(['material_type_id' => $type]);
        RoomMaterial::factory()->create(['room_id' => $room->id, 'material_id' => $material->id]);
        RoomCost::factory()->create(['room_id' => $room->id, 'type' => RoomCostType::Other, 'amount' => 10]);
    }

    return $room;
}

function activityQueries(Room $room, User $admin): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->actingAs($admin)->get(route('rooms.show', $room))->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('the room history box runs a fixed number of queries whatever the number of linked records', function () {
    // Both rooms are built before any request, so the login does not add rows to one of them.
    $small = roomWithHistory(2, $this->type);
    $large = roomWithHistory(12, $this->type);

    expect(activityQueries($large, $this->admin))->toBe(activityQueries($small, $this->admin));
});
