<?php

use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\RoomMaterial;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->type = MaterialType::query()->where('name', 'خامة')->value('id');
});

function roomWithMaterials(int $materialCount, int $type): Room
{
    $room = Room::factory()->create(['customer_id' => Customer::factory()]);

    for ($i = 0; $i < $materialCount; $i++) {
        $material = Material::factory()->create(['material_type_id' => $type]);
        RoomMaterial::factory()->create(['room_id' => $room->id, 'material_id' => $material->id]);
    }

    return $room;
}

function queriesToShow(Room $room, User $admin): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->actingAs($admin)->get(route('rooms.show', $room))->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('the room page runs the same number of queries whatever the number of material rows', function () {
    // Both rooms are built before any request: a request logs the user in,
    // and rows created afterwards would carry a user and add a query.
    $few = roomWithMaterials(3, $this->type);
    $many = roomWithMaterials(10, $this->type);

    expect(queriesToShow($many, $this->admin))->toBe(queriesToShow($few, $this->admin));
});
