<?php

use App\Enums\RoomStatus;
use App\Enums\SeasonStatus;
use App\Models\Partner;
use App\Models\Room;
use App\Models\RoomMaterial;
use App\Models\Season;
use App\Models\User;
use App\Services\SeasonBackupService;
use App\Services\SeasonService;

beforeEach(function () {
    app()->instance(SeasonBackupService::class, new class extends SeasonBackupService
    {
        public function create(): string
        {
            return 'fake-backup.sqlite';
        }
    });

    Season::query()->where('status', SeasonStatus::Open)->update(['started_at' => '2026-01-01']);
    $this->admin = User::factory()->create();
});

test('the preview shows the numbers and writes nothing', function () {
    Room::factory()->create(['status' => RoomStatus::Completed, 'sale_price' => 10_000]);
    $before = Season::query()->count();

    $this->actingAs($this->admin)
        ->get(route('seasons.preview', ['ends_at' => '2026-06-30']))
        ->assertOk()
        ->assertSee('معاينة إقفال الموسم')
        ->assertSee('10,000');

    expect(Season::query()->count())->toBe($before);
    expect(Season::query()->where('status', SeasonStatus::Open)->count())->toBe(1);
    expect(Room::query()->whereNotNull('season_id')->count())->toBe(0);
});

test('the preview warns when what is owed to partners is more than the cashbox holds', function () {
    // One partner at 100% is owed the whole 50,000 EGP profit; the cashbox is empty.
    Partner::factory()->create(['percentage' => 10_000]);
    Room::factory()->create(['status' => RoomStatus::Completed, 'sale_price' => 50_000]);

    $this->actingAs($this->admin)
        ->get(route('seasons.preview', ['ends_at' => '2026-06-30']))
        ->assertSee('أكبر من رصيد الخزنة');
});

test('the season page lists the open and closed seasons', function () {
    app(SeasonService::class)->close('2026-06-30');

    $this->actingAs($this->admin)
        ->get(route('seasons.index'))
        ->assertOk()
        ->assertSee('موسم 1')
        ->assertSee('موسم 2');
});

test('the closed season detail shows its frozen numbers and the partner table', function () {
    $partner = Partner::factory()->create(['percentage' => 2_000, 'name' => 'شريك الاختبار']);
    app(SeasonService::class)->close('2026-06-30');
    $closed = Season::query()->where('number', 1)->first();

    $this->actingAs($this->admin)
        ->get(route('seasons.index', ['season' => $closed->id]))
        ->assertOk()
        ->assertSee('تفاصيل')
        ->assertSee('شريك الاختبار');
});

test('reopen is offered on the latest closed season', function () {
    app(SeasonService::class)->close('2026-06-30');

    $this->actingAs($this->admin)
        ->get(route('seasons.index'))
        ->assertSee('فتح الموسم');
});

test('the rooms page shows only the open season by default', function () {
    $sealed = Room::factory()->create(['status' => RoomStatus::Completed, 'room_type' => 'مطبخ مقفول']);
    Room::factory()->create(['status' => RoomStatus::InProgress, 'room_type' => 'صالون مفتوح']);
    app(SeasonService::class)->close('2026-06-30');

    $this->actingAs($this->admin)
        ->get(route('rooms.index'))
        ->assertSee('صالون مفتوح')
        ->assertDontSee('مطبخ مقفول');

    $this->actingAs($this->admin)
        ->get(route('rooms.index', ['season' => 'all']))
        ->assertSee('مطبخ مقفول');
});

test('the profit report says which season it covers and shows a carried loss', function () {
    $room = Room::factory()->create(['status' => RoomStatus::Completed, 'sale_price' => 0]);
    RoomMaterial::factory()->create(['room_id' => $room->id, 'cost' => 20_000]);
    app(SeasonService::class)->close('2026-06-30');

    $this->actingAs($this->admin)
        ->get(route('reports.profit'))
        ->assertOk()
        ->assertSee('الأرقام دي للموسم المفتوح')
        ->assertSee('خسارة مُدوَّرة من موسم سابق');
});

test('the partner page shows the amount carried in from an earlier season', function () {
    $partner = Partner::factory()->create(['percentage' => 2_000]);
    app(SeasonService::class)->close('2026-06-30');

    $this->actingAs($this->admin)
        ->get(route('partners.show', $partner))
        ->assertOk();
});
