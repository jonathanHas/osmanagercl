<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session has expired. Please refresh the page.'], 419);
            }

            // Session still alive, token merely stale (long-open tab, rotated key).
            // Bouncing to login here would discard the POST body and park the page
            // in url.intended, so the user re-authenticated only to land back on an
            // empty form with their work gone. Flash the input instead — views that
            // repopulate from old() will restore it.
            if ($request->user()) {
                return back()->withInput()->withErrors([
                    'session' => 'Your form token expired. Your work has been restored — please press Save again.',
                ]);
            }

            // Genuinely expired session: the store is gone, nothing else is possible.
            return redirect()->guest(route('login'))
                ->with('status', 'Your session has expired. Please log in again.');
        });
    })->create();
