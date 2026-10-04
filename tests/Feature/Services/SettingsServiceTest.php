<?php

use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->settings = app(SettingsService::class);
});

test('get returns the default when the key is not stored', function () {
    expect($this->settings->get('season_length_months'))->toBe('6');
});

test('get returns null for an unknown key', function () {
    expect($this->settings->get('unknown_key'))->toBeNull();
});

test('set then get returns the new value', function () {
    $this->settings->set('season_length_months', 8);

    expect($this->settings->get('season_length_months'))->toBe('8');
});

test('setting the same key twice updates the row instead of adding one', function () {
    $this->settings->set('backup_reminder_days', 3);
    $this->settings->set('backup_reminder_days', 14);

    expect(DB::table('settings')->where('key', 'backup_reminder_days')->count())->toBe(1)
        ->and($this->settings->get('backup_reminder_days'))->toBe('14');
});

test('getInt returns an integer', function () {
    $this->settings->set('backup_reminder_days', '10');

    expect($this->settings->getInt('backup_reminder_days'))->toBe(10)
        ->and($this->settings->getInt('season_length_months'))->toBe(6);
});

test('all merges stored values over the defaults', function () {
    $this->settings->set('season_length_months', 4);

    expect($this->settings->all())->toBe([
        'season_length_months' => '4',
        'backup_reminder_days' => '7',
    ]);
});
