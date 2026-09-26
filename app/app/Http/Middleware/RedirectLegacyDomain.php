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
        $canonicalUrl = rtrim((string) config('md-notes.canonical_url'), '/');
        $workspaceUrl = rtrim((string) config('md-notes.workspace_url'), '/');
        $publicHost = strtolower((string) parse_url($canonicalUrl, PHP_URL_HOST));
        $workspaceHost = strtolower((string) parse_url($workspaceUrl, PHP_URL_HOST));
        $host = strtolower($request->getHost());
        $legacy = in_array($host, $legacyHosts, true);

        if ($canonicalUrl === '' || (! $legacy && $host !== $publicHost && $host !== $workspaceHost)) {
            return $next($request);
        }

        if ($workspaceHost === '' || $workspaceHost === $publicHost) {
            return $legacy ? redirect()->away($canonicalUrl.$request->getRequestUri(), 308) : $next($request);
        }

        // Old API clients keep working without following a redirect or losing credentials.
        if ($request->is('api/*')) {
            return $next($request);
        }

        $uri = $request->getRequestUri();
        $path = $request->getPathInfo();

        if ($path === '/share' || str_starts_with($path, '/share/')) {
            return $legacy ? redirect()->away($canonicalUrl.$uri, 308) : $next($request);
        }

        if (($host === $publicHost || $legacy) && ($path === '/app' || str_starts_with($path, '/app/'))) {
            $uri = substr($uri, 4);
            if ($uri === '' || str_starts_with($uri, '?')) {
                $uri = '/'.$uri;
            }

            return redirect()->away($workspaceUrl.$uri, 308);
        }

        if ($path === '/' || $path === '/documentation.md' || $path === '/documentation.raw.md') {
            return $legacy ? redirect()->away($canonicalUrl.$uri, 308) : $next($request);
        }

        // The public site keeps its landing, documentation and static assets.
        $workspacePath = preg_match('#^/(?:login|signup|logout|forgot-password|reset-password|media|account-export)(?:/|$)#', $path)
            || str_ends_with(strtolower($path), '.md');
        if (($host === $publicHost || $legacy) && $workspacePath) {
            return redirect()->away($workspaceUrl.$uri, 308);
        }

        return $legacy ? redirect()->away($canonicalUrl.$uri, 308) : $next($request);
    }
}
