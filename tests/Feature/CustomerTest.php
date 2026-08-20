<?php

use App\Models\Customer;
use App\Models\Room;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot access customers', function () {
    $this->get(route('customers.index'))->assertRedirect(route('login'));
});

test('a customer can be created and viewed', function () {
    $response = $this->actingAs($this->admin)->post(route('customers.store'), [
        'name' => 'أحمد',
        'phone' => '0100000000',
        'address' => 'القاهرة',
    ]);

    $customer = Customer::query()->where('name', 'أحمد')->firstOrFail();
    $response->assertRedirect(route('customers.show', $customer));
});

test('creating a customer without a name fails validation', function () {
    $response = $this->actingAs($this->admin)->post(route('customers.store'), ['name' => '']);

    $response->assertSessionHasErrors('name');
});

test('a customer can be edited', function () {
    $customer = Customer::factory()->create(['name' => 'أحمد']);

    $this->actingAs($this->admin)->put(route('customers.update', $customer), [
        'name' => 'أحمد محمد',
        'phone' => $customer->phone,
        'address' => $customer->address,
    ])->assertRedirect(route('customers.show', $customer));

    expect($customer->fresh()->name)->toBe('أحمد محمد');
});

test('a customer with no rooms can be deleted', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->admin)->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));

    expect(Customer::query()->find($customer->id))->toBeNull();
});

test('deleting a customer with rooms is rejected', function () {
    $customer = Customer::factory()->create();
    Room::factory()->for($customer)->create();

    $response = $this->actingAs($this->admin)->delete(route('customers.destroy', $customer));

    $response->assertSessionHas('error');
    expect(Customer::query()->find($customer->id))->not->toBeNull();
});

test('a room shows on its customer page with type and price', function () {
    $customer = Customer::factory()->create(['name' => 'أحمد']);
    Room::factory()->for($customer)->create(['room_type' => 'غرفة نوم', 'sale_price' => 3_000_000]);

    $response = $this->actingAs($this->admin)->get(route('customers.show', $customer));

    $response->assertOk()->assertSee('غرفة نوم')->assertSee('30,000.00 ج.م');
});
