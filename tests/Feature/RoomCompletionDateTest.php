<?php

use App\Enums\RoomStatus;
use App\Models\Customer;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function postCompletionStatus($test, Room $room, string $status)
{
    return $test->actingAs($test->admin)->post(route('rooms.status.update', $room), ['status' => $status]);
}

test('completing a room stamps today as its completion date', function () {
    $this->travelTo('2026-10-04');
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);

    postCompletionStatus($this, $room, 'completed')->assertSessionHasNoErrors();

    expect($room->fresh()->completed_at->toDateString())->toBe('2026-10-04');
});

test('moving a completed room to in progress clears the completion date', function () {
    $room = Room::factory()->create(['status' => RoomStatus::Completed, 'completed_at' => '2026-01-05']);

    postCompletionStatus($this, $room, 'in_progress');

    expect($room->fresh()->completed_at)->toBeNull();
});

test('completing a room again replaces the date with the new one', function () {
    $this->travelTo('2026-10-04');
    $room = Room::factory()->create(['status' => RoomStatus::Completed, 'completed_at' => '2026-01-05']);

    postCompletionStatus($this, $room, 'completed');

    expect($room->fresh()->completed_at->toDateString())->toBe('2026-10-04');
});

test('a draft moved to in progress gets no completion date', function () {
    $room = Room::factory()->create(['status' => RoomStatus::Draft]);

    postCompletionStatus($this, $room, 'in_progress');

    expect($room->fresh()->completed_at)->toBeNull();
});

test('the room page shows the completion date under the room name', function () {
    $room = Room::factory()->create(['status' => RoomStatus::Completed, 'completed_at' => '2026-03-15']);

    $this->actingAs($this->admin)
        ->get(route('rooms.show', $room))
        ->assertSee('تاريخ الاكتمال: 2026-03-15');
});

test('the migration fills the completion date of old completed rooms from their last update', function () {
    $migration = 'database/migrations/2026_10_04_100400_add_completed_at_to_rooms_table.php';
    $customer = Customer::factory()->create();

    Artisan::call('migrate:rollback', ['--path' => $migration]);

    DB::table('rooms')->insert([
        ['customer_id' => $customer->id, 'room_type' => 'مطبخ قديم', 'sale_price' => 100000, 'status' => 'completed', 'created_at' => now(), 'updated_at' => '2026-03-15 10:00:00'],
        ['customer_id' => $customer->id, 'room_type' => 'غرفة مسودة', 'sale_price' => 100000, 'status' => 'draft', 'created_at' => now(), 'updated_at' => '2026-03-15 10:00:00'],
    ]);

    Artisan::call('migrate', ['--path' => $migration]);

    expect(DB::table('rooms')->where('room_type', 'مطبخ قديم')->value('completed_at'))->toBe('2026-03-15')
        ->and(DB::table('rooms')->where('room_type', 'غرفة مسودة')->value('completed_at'))->toBeNull();
});
