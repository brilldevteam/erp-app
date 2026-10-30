<?php

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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\CheckInstallation::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\UpdateUserActiveStatus::class,
            \App\Http\Middleware\EnsureAuthSessionIsCurrent::class,
            \App\Http\Middleware\PreventAuthenticatedResponseCaching::class,
            \App\Http\Middleware\EnsureRouteModelsBelongToCompany::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\EnsureRouteModelsBelongToCompany::class,
        ]);
        $middleware->alias([
            'PlanModuleCheck' => \App\Http\Middleware\PlanModuleCheck::class,
            'api.json' => \App\Http\Middleware\ApiForceJson::class,
            'auth.session.current' => \App\Http\Middleware\EnsureAuthSessionIsCurrent::class,
        ]);

        // Pasted spreadsheet rows end in a tab when their last cell is empty; the import trims each cell itself.
        $middleware->trimStrings(except: ['pasted_data']);

        $middleware->validateCsrfTokens(
            except: [
                'aamarpay/*',
                'iyzipay/*',
                'meta/callback',
                'payfort/*',
                'sslcommerz/*',
                'twocheckout/*',
                'payu/*',
                'powertranz/*'
            ]
        );

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
