<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\EnsureBranchBillingAccess;
use App\Http\Middleware\EnsureAttendanceEmployeeAuthenticated;
use App\Http\Middleware\EnsureSystemSettingsCompleted;
use App\Http\Middleware\EnsureMenuAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'attendance.employee' => EnsureAttendanceEmployeeAuthenticated::class,
            'billing.access' => EnsureBranchBillingAccess::class,
            'settings.completed' => EnsureSystemSettingsCompleted::class,
            'menu.access' => EnsureMenuAccess::class,
            'super.admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
