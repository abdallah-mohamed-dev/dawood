<?php

use App\Exceptions\SeasonClosedException;
use App\Exceptions\SeasonCloseFailedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Season lock and close failures are shown as Arabic flash messages on
        // whatever page the user was on, instead of a bare error page.
        $exceptions->render(function (SeasonClosedException|SeasonCloseFailedException $exception, Request $request) {
            return back()->with('error', $exception->getMessage());
        });
    })->create();
