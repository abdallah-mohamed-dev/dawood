<?php

namespace App\Providers;

use App\Services\ReminderService;
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
        });
    }
}
