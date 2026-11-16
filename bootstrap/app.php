<?php

use App\Console\Commands\ReescribirUrlFotos;
use App\Http\Middleware\AuditLogMiddleware;
use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\CheckModuloAccess;
use App\Http\Middleware\EnsureSelfOrSuperAdmin;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Auditoría automática de escritura (POST/PUT/PATCH/DELETE).
        $middleware->api(append: [
            AuditLogMiddleware::class,
        ]);
        $middleware->alias([
            'superadmin' => EnsureSuperAdmin::class,
            'auth.jwt' => AuthenticateJwt::class,
            'modulo' => CheckModuloAccess::class,
            'self_or_superadmin' => EnsureSelfOrSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withCommands([
        ReescribirUrlFotos::class,
    ])->create();
