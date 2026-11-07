<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['mot_de_passe', 'mot_de_passe_confirmation', 'secret']);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
        $middleware->appendToGroup('web', \\App\\Http\\Middleware\\ResoudreDomaineApplication::class);
        $middleware->alias([
            'throttle' => \App\Http\Middleware\GamadThrottleRequests::class,
            'gamad.session' => \App\Http\Middleware\ExigerSession::class,
            'gamad.console' => \App\Http\Middleware\ExigerAccesConsole::class,
            'gamad.api' => \App\Http\Middleware\AuthentifierApi::class,
            'gamad.https' => \App\Http\Middleware\ExigerHttpsEnProduction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['mot_de_passe', 'mot_de_passe_confirmation', 'secret', 'code']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
