<?php

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Room;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function logsFor(string $modelClass, int $id)
{
    return ActivityLog::query()->where('subject_type', $modelClass)->where('subject_id', $id)->get();
}

test('creating a customer records a created row', function () {
    $this->actingAs($this->admin);

    $customer = Customer::factory()->create(['name' => 'أحمد علي']);

    $log = logsFor(Customer::class, $customer->id)->sole();

    expect($log->event)->toBe('created')
        ->and($log->subject_label)->toBe('أحمد علي')
        ->and($log->changes)->toBeNull()
        ->and($log->user_id)->toBe($this->admin->id);
});

test('renaming a customer records the old and new name', function () {
    $customer = Customer::factory()->create(['name' => 'اسم قديم']);

    $customer->update(['name' => 'اسم جديد']);

    $log = logsFor(Customer::class, $customer->id)->where('event', 'updated')->sole();

    expect($log->changes)->toBe([
        'name' => ['old' => 'اسم قديم', 'new' => 'اسم جديد'],
    ]);
});

test('editing a payment amount records readable pounds, not the raw column', function () {
    $room = Room::factory()->create();
    $payment = CustomerPayment::factory()->for($room)->create(['amount' => '1000']);

    $payment->update(['amount' => '1500']);

    $log = logsFor(CustomerPayment::class, $payment->id)->where('event', 'updated')->sole();

    expect($log->changes['amount'])->toBe(['old' => '1,000', 'new' => '1,500']);
});

test('deleting a customer records a deleted row with a readable label', function () {
    $customer = Customer::factory()->create(['name' => 'سمير حسن']);
    $id = $customer->id;

    $customer->delete();

    $log = logsFor(Customer::class, $id)->where('event', 'deleted')->sole();

    expect($log->subject_label)->toBe('سمير حسن')
        ->and($log->changes)->toBeNull();
});

test('saving a model without real changes records nothing', function () {
    $customer = Customer::factory()->create();
    $before = logsFor(Customer::class, $customer->id)->count();

    $customer->save();

    expect(logsFor(Customer::class, $customer->id)->count())->toBe($before);
});

test('touching only updated_at records nothing', function () {
    $customer = Customer::factory()->create();
    $before = logsFor(Customer::class, $customer->id)->count();

    $customer->touch();

    expect(logsFor(Customer::class, $customer->id)->count())->toBe($before);
});

test('the activity log does not record itself', function () {
    $before = ActivityLog::count();

    ActivityLog::create([
        'subject_type' => Customer::class,
        'subject_id' => 1,
        'subject_label' => 'اختبار',
        'event' => 'created',
        'created_at' => now(),
    ]);

    expect(ActivityLog::count())->toBe($before + 1)
        ->and(ActivityLog::query()->where('subject_type', ActivityLog::class)->exists())->toBeFalse();
});

test('a password change never reaches the log', function () {
    $this->admin->update(['name' => 'اسم جديد', 'password' => 'secret-password']);

    $log = logsFor(User::class, $this->admin->id)->where('event', 'updated')->sole();

    expect(array_keys($log->changes))->toBe(['name']);
});

test('the details column shows the Arabic field name with old and new values', function () {
    $customer = Customer::factory()->create(['name' => 'قديم']);
    $customer->update(['name' => 'جديد']);

    $this->actingAs($this->admin)
        ->get(route('logs.index'))
        ->assertOk()
        ->assertSee('الاسم: قديم ← جديد');
});

test('the log page filters by date range and event', function () {
    ActivityLog::create([
        'subject_type' => Customer::class, 'subject_id' => 1, 'subject_label' => 'عميل قديم',
        'event' => 'created', 'created_at' => '2026-01-10 09:00:00',
    ]);
    ActivityLog::create([
        'subject_type' => Customer::class, 'subject_id' => 2, 'subject_label' => 'عميل حديث',
        'event' => 'deleted', 'created_at' => '2026-06-10 09:00:00',
    ]);

    $this->actingAs($this->admin)
        ->get(route('logs.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertOk()
        ->assertSee('عميل حديث')
        ->assertDontSee('عميل قديم');

    $this->actingAs($this->admin)
        ->get(route('logs.index', ['event' => 'created']))
        ->assertSee('عميل قديم')
        ->assertDontSee('عميل حديث');
});

test('the CSV export starts with a BOM and follows the filters', function () {
    ActivityLog::create([
        'subject_type' => Customer::class, 'subject_id' => 1, 'subject_label' => 'عميل قديم',
        'event' => 'created', 'created_at' => '2026-01-10 09:00:00',
    ]);
    ActivityLog::create([
        'subject_type' => Customer::class, 'subject_id' => 2, 'subject_label' => 'عميل محذوف',
        'event' => 'deleted', 'created_at' => '2026-06-10 09:00:00',
    ]);

    $response = $this->actingAs($this->admin)->get(route('logs.export', ['event' => 'deleted']));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $content = $response->streamedContent();

    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($content)->toContain('عميل محذوف')
        ->and($content)->not->toContain('عميل قديم');
});

test('purging deletes only the rows older than the given date', function () {
    ActivityLog::create([
        'subject_type' => Customer::class, 'subject_id' => 1, 'subject_label' => 'سجل قديم',
        'event' => 'created', 'created_at' => '2026-01-10 09:00:00',
    ]);
    ActivityLog::create([
        'subject_type' => Customer::class, 'subject_id' => 2, 'subject_label' => 'سجل حديث',
        'event' => 'created', 'created_at' => '2026-09-01 09:00:00',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('logs.purge'), ['before' => '2026-06-01'])
        ->assertRedirect();

    expect(ActivityLog::query()->where('subject_label', 'سجل قديم')->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('subject_label', 'سجل حديث')->exists())->toBeTrue();
});

test('purging without a valid date is rejected', function () {
    $this->actingAs($this->admin)
        ->delete(route('logs.purge'), ['before' => ''])
        ->assertSessionHasErrors('before');
});

test('guests cannot reach any of the log routes', function () {
    $this->get(route('logs.index'))->assertRedirect(route('login'));
    $this->get(route('logs.export'))->assertRedirect(route('login'));
    $this->delete(route('logs.purge'), ['before' => '2026-06-01'])->assertRedirect(route('login'));
});

test('the settings page links to the activity log', function () {
    $this->actingAs($this->admin)
        ->get(route('settings.index'))
        ->assertSee(route('logs.index'));
});
