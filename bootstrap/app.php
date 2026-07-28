<?php

use App\Http\Middleware\EnsureRouteUserIsNotSelf;
use App\Http\Middleware\EnsureUserWithinUploadLimit;
use App\Http\Middleware\RequirePasswordForIdentityKeyOverwrite;
use App\Http\Middleware\VerifyTurnstile;
use App\Support\Csp\AddCspHeaders;
use App\Support\Csp\PrepareCspNonce;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(PrepareCspNonce::class);
        $middleware->append(AddCspHeaders::class);
        $middleware->alias([
            'identity-key-overwrite-password' => RequirePasswordForIdentityKeyOverwrite::class,
            'not-self' => EnsureRouteUserIsNotSelf::class,
            'turnstile' => VerifyTurnstile::class,
            'upload-limit' => EnsureUserWithinUploadLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('system:cleanup-expired')
            ->everyThirtyMinutes()
            ->withoutOverlapping();
    })
    ->create();
