<?php

namespace App\Providers;

use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Services\CashboxService;
use App\Services\ProfitService;
use App\Services\ReminderService;
use App\Services\SeasonService;
use App\Services\SettingsService;
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
