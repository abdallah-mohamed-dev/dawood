<?php

namespace App\Providers;

use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Services\CashboxService;
use App\Services\ProfitService;
use App\Services\ReminderService;
use App\Services\SeasonService;
use App\Services\SettingsService;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // `php artisan serve` hands the server process a whitelist of
        // environment variables, and TMP/TEMP are not on it — so on Windows
        // sys_get_temp_dir() falls back to C:\Windows, tempnam() returns false,
        // and the process cannot create a single temp file. Nested transactions
        // (a controller transaction around InventoryService::addStock) make
        // sqlite reach for a statement journal in that temp dir, and the write
        // dies with "unable to open database file". File uploads break the same
        // way. Passing the two variables through fixes both.
        ServeCommand::$passthroughVariables[] = 'TMP';
        ServeCommand::$passthroughVariables[] = 'TEMP';

        // Belt and braces for the same failure: keep sqlite's scratch journals
        // in memory, so a host with no writable temp dir cannot break a write
        // at all. config/database.php has no temp_store option, so it is set
        // here, on every connection as it opens.
        Event::listen(function (ConnectionEstablished $event) {
            if ($event->connection->getDriverName() === 'sqlite') {
                $event->connection->statement('PRAGMA temp_store = MEMORY');
            }
        });

        // Computed once per rendered page, and only where the app layout is used,
        // so the login page never shows them.
        View::composer('components.app-layout', function ($view) {
            $view->with('reminders', app(ReminderService::class)->due());

            // Context bar (specs/020.2): read-only, computed on the same pass.
            $season = Season::query()->where('status', SeasonStatus::Open)->first();

            $view->with('context', [
                'seasonName' => $season ? app(SeasonService::class)->displayName($season) : null,
                'cashboxBalance' => app(CashboxService::class)->balance(),
                'netProfit' => app(ProfitService::class)->netProfit(),
                'lastBackupAt' => app(SettingsService::class)->get('last_backup_at'),
            ]);
        });
    }
}
