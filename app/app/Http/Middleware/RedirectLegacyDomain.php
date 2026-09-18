<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacyDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $legacyHosts = array_map('strtolower', config('md-notes.legacy_hosts', []));

        if (! in_array(strtolower($request->getHost()), $legacyHosts, true)) {
            return $next($request);
        }

        $canonicalUrl = rtrim((string) config('md-notes.canonical_url'), '/');
        if ($canonicalUrl === '') {
            return $next($request);
        }

        return redirect()->away($canonicalUrl.$request->getRequestUri(), 308);
    }
}
