<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SettingsService
{
    /**
     * القيم الافتراضية. المفتاحين اللي بعد الأول متسجلين للمستقبل بس.
     */
    public const DEFAULTS = [
        'season_length_months' => '6',
        'backup_reminder_days' => '7',
    ];

    public function get(string $key): ?string
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        if ($value !== null) {
            return $value;
        }

        return self::DEFAULTS[$key] ?? null;
    }

    public function getInt(string $key): int
    {
        return (int) $this->get($key);
    }

    public function set(string $key, string|int $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) $value, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        $stored = DB::table('settings')->pluck('value', 'key')->all();

        return array_merge(self::DEFAULTS, $stored);
    }
}
