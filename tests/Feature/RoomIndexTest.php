<?php

use App\Enums\RoomStatus;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests are redirected to login', function () {
    $this->get(route('rooms.index'))->assertRedirect(route('login'));
});

test('the page lists every room with its customer name', function () {
    $ahmed = Customer::factory()->create(['name' => 'أحمد علي']);
    Room::factory()->create(['customer_id' => $ahmed->id, 'room_type' => 'غرفة نوم']);

    $this->actingAs($this->admin)
        ->get(route('rooms.index'))
        ->assertOk()
        ->assertSee('غرفة نوم')
        ->assertSee('أحمد علي');
});

test('search by room type returns the matching room', function () {
    Room::factory()->create(['room_type' => 'مطبخ']);
    Room::factory()->create(['room_type' => 'صالون']);

    $this->actingAs($this->admin)
        ->get(route('rooms.index', ['q' => 'مطبخ']))
        ->assertSee('مطبخ')
        ->assertDontSee('صالون');
});

test('search by customer name returns that customer rooms', function () {
    $ali = Customer::factory()->create(['name' => 'علي حسن']);
    $sara = Customer::factory()->create(['name' => 'سارة محمد']);
    Room::factory()->create(['customer_id' => $ali->id, 'room_type' => 'مطبخ']);
    Room::factory()->create(['customer_id' => $sara->id, 'room_type' => 'صالون']);

    $this->actingAs($this->admin)
        ->get(route('rooms.index', ['q' => 'علي']))
        ->assertSee('مطبخ')
        ->assertDontSee('صالون');
});

test('status filter returns only rooms in that status', function () {
    Room::factory()->create(['room_type' => 'مطبخ', 'status' => RoomStatus::Completed]);
    Room::factory()->create(['room_type' => 'صالون', 'status' => RoomStatus::Draft]);

    $this->actingAs($this->admin)
        ->get(route('rooms.index', ['status' => RoomStatus::Completed->value]))
        ->assertSee('مطبخ')
        ->assertDontSee('صالون');
});

test('customer filter returns only that customer rooms', function () {
    $ali = Customer::factory()->create();
    $sara = Customer::factory()->create();
    Room::factory()->create(['customer_id' => $ali->id, 'room_type' => 'مطبخ']);
    Room::factory()->create(['customer_id' => $sara->id, 'room_type' => 'صالون']);

    $this->actingAs($this->admin)
        ->get(route('rooms.index', ['customer_id' => $ali->id]))
        ->assertSee('مطبخ')
        ->assertDontSee('صالون');
});

test('date filter returns rooms created inside the range', function () {
    $old = Room::factory()->create(['room_type' => 'مطبخ']);
    $new = Room::factory()->create(['room_type' => 'صالون']);
    DB::table('rooms')->where('id', $old->id)->update(['created_at' => '2026-01-10 10:00:00']);
    DB::table('rooms')->where('id', $new->id)->update(['created_at' => '2026-03-10 10:00:00']);

    $this->actingAs($this->admin)
        ->get(route('rooms.index', ['from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertSee('صالون')
        ->assertDontSee('مطبخ');
});

test('two filters work together', function () {
    $ali = Customer::factory()->create();
    Room::factory()->create(['customer_id' => $ali->id, 'room_type' => 'مطبخ', 'status' => RoomStatus::Completed]);
    Room::factory()->create(['customer_id' => $ali->id, 'room_type' => 'صالون', 'status' => RoomStatus::Draft]);

    $this->actingAs($this->admin)
        ->get(route('rooms.index', ['customer_id' => $ali->id, 'status' => RoomStatus::Completed->value]))
        ->assertSee('مطبخ')
        ->assertDontSee('صالون');
});

test('filters survive pagination', function () {
    Room::factory()->count(30)->create(['room_type' => 'مطبخ', 'status' => RoomStatus::Completed]);
    Room::factory()->count(5)->create(['room_type' => 'صالون', 'status' => RoomStatus::Draft]);

    $this->actingAs($this->admin)
        ->get(route('rooms.index', ['status' => RoomStatus::Completed->value, 'page' => 2]))
        ->assertOk()
        ->assertSee('status='.RoomStatus::Completed->value, false)
        ->assertDontSee('صالون');
});

test('paid and remaining amounts show the right reference numbers', function () {
    $room = Room::factory()->create(['sale_price' => 1_000_000]); // 10,000.00
    CustomerPayment::factory()->create(['room_id' => $room->id, 'amount' => 300_000]);
    CustomerPayment::factory()->create(['room_id' => $room->id, 'amount' => 200_000]);

    $this->actingAs($this->admin)
        ->get(route('rooms.index'))
        ->assertSee('5,000.00')   // paid 500,000 piastres
        ->assertSee('5,000.00');  // remaining 500,000 piastres
});

test('the number of queries does not grow with the number of rooms', function () {
    $countQueries = function () {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->actingAs($this->admin)->get(route('rooms.index'))->assertOk();

        return $count;
    };

    Room::factory()->count(3)->create();
    $few = $countQueries();

    Room::factory()->count(20)->create();
    $many = $countQueries();

    expect($many)->toBe($few);
});

test('the rooms index shows both charts and their figures as text', function () {
    $this->actingAs(User::factory()->create());
    Room::factory()->create(['customer_id' => Customer::factory(), 'sale_price' => 1_000_000]);

    $response = $this->get(route('rooms.index'))->assertOk();

    $response->assertSee('role="img"', false);
    $response->assertSeeInOrder(['رسم', 'جدول']);
});
