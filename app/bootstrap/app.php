<?php

use App\Http\Middleware\ApiTokenAuthentication;
use App\Http\Middleware\RedirectLegacyDomain;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateSessionVersion;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $offlineSync = fn (Request $request): bool => $request->is('offline/sync', 'app/offline/sync');
        $middleware->trimStrings(except: [$offlineSync]);
        $middleware->convertEmptyStringsToNull(except: [$offlineSync]);
        $middleware->replace(Illuminate\Http\Middleware\TrustProxies::class, TrustProxies::class);
        $middleware->prepend(RedirectLegacyDomain::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'api.token' => ApiTokenAuthentication::class,
        ]);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', ValidateSessionVersion::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            $seconds = (int) ($exception->getHeaders()['Retry-After'] ?? 60);
            $locale = $request->getPreferredLanguage(['es', 'en']) === 'es' ? 'es' : 'en';
            app()->setLocale($locale);
            $message = __('ui.too_many_requests', ['seconds' => $seconds]);

            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['message' => $message], 429, $exception->getHeaders())
                : response()->view('errors.429', ['message' => $message], 429, $exception->getHeaders());
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
