<?php

use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Models\User;
use App\Services\SeasonBackupService;
use App\Services\SettingsService;

beforeEach(function () {
    app()->instance(SeasonBackupService::class, new class extends SeasonBackupService
    {
        public function create(): string
        {
            return 'fake-backup.sqlite';
        }
    });

    $this->admin = User::factory()->create();
    $this->settings = app(SettingsService::class);
    $this->settings->set('last_backup_at', now()->toDateString()); // keeps the backup reminder quiet by default
});

function setSeasonStart(string $date): void
{
    Season::query()->where('status', SeasonStatus::Open)->update(['started_at' => $date]);
}

test('a season older than its length shows a reminder', function () {
    setSeasonStart(now()->subMonths(7)->toDateString());

    $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('الموسم وصل مدته');
});

test('a season younger than its length shows no reminder', function () {
    setSeasonStart(now()->subMonths(3)->toDateString());

    $this->actingAs($this->admin)->get(route('dashboard'))->assertDontSee('الموسم وصل مدته');
});

test('changing the season length in settings changes the reminder', function () {
    setSeasonStart(now()->subMonths(7)->toDateString());
    $this->settings->set('season_length_months', 12);

    $this->actingAs($this->admin)->get(route('dashboard'))->assertDontSee('الموسم وصل مدته');
});

test('a database that was never backed up shows a backup reminder', function () {
    $this->settings->set('last_backup_at', '');

    $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('لسه ما اتعملش نسخة احتياطية');
});

test('a backup older than the reminder window shows the reminder again', function () {
    $this->settings->set('last_backup_at', now()->subDays(10)->toDateString());

    $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('آخر نسخة احتياطية كانت');
});

test('downloading a backup records the date and clears the reminder', function () {
    $this->settings->set('last_backup_at', now()->subDays(10)->toDateString());

    $this->actingAs($this->admin)->get(route('backup.csv'))->assertOk();

    expect($this->settings->get('last_backup_at'))->toBe(now()->toDateString());
    $this->actingAs($this->admin)->get(route('dashboard'))->assertDontSee('آخر نسخة احتياطية كانت');
});

test('reminders never close a season on their own', function () {
    setSeasonStart(now()->subMonths(12)->toDateString());

    $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('الموسم وصل مدته');

    expect(Season::query()->where('status', SeasonStatus::Closed)->count())->toBe(0);
    expect(Season::query()->where('status', SeasonStatus::Open)->count())->toBe(1);
});

test('the reminder banner is not shown on the login page', function () {
    $this->settings->set('last_backup_at', '');

    $this->get(route('login'))->assertOk()->assertDontSee('لسه ما اتعملش نسخة احتياطية');
});

test('reminder settings refuse zero and negative numbers in Arabic', function () {
    $this->actingAs($this->admin)
        ->put(route('settings.reminders.update'), ['season_length_months' => '0', 'backup_reminder_days' => '7'])
        ->assertSessionHasErrors(['season_length_months' => 'مدة الموسم لازم تكون شهر واحد على الأقل.']);

    $this->actingAs($this->admin)
        ->put(route('settings.reminders.update'), ['season_length_months' => '6', 'backup_reminder_days' => '-3'])
        ->assertSessionHasErrors(['backup_reminder_days']);
});
