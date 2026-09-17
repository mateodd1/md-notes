<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Use Spanish only when it is the browser/operating-system preferred
     * language. Every other language deliberately falls back to English.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(['es', 'en']);

        $locale = $locale === 'es' ? 'es' : 'en';
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
