<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $connectSources = ["'self'"];
        $allowsSameOriginFrame = $request->routeIs('media.preview');

        if ($request->routeIs('home') && ($workspaceOrigin = $this->workspaceOrigin()) !== null) {
            $connectSources[] = $workspaceOrigin;
        }

        $response->headers->remove('X-Powered-By');
        $frameAncestors = $allowsSameOriginFrame ? "'self'" : "'none'";
        $response->headers->set('Content-Security-Policy', "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors {$frameAncestors}; object-src 'none'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src ".implode(' ', $connectSources)."; font-src 'self' data:");
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', $allowsSameOriginFrame ? 'SAMEORIGIN' : 'DENY');

        if ($request->is('share/*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        } elseif ($this->isWorkspaceHost($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }

    private function workspaceOrigin(): ?string
    {
        $parts = parse_url((string) config('md-notes.workspace_url'));
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    private function isWorkspaceHost(Request $request): bool
    {
        $publicHost = parse_url((string) config('md-notes.canonical_url'), PHP_URL_HOST);
        $workspaceHost = parse_url((string) config('md-notes.workspace_url'), PHP_URL_HOST);

        return is_string($publicHost)
            && is_string($workspaceHost)
            && $workspaceHost !== $publicHost
            && strcasecmp($request->getHost(), $workspaceHost) === 0;
    }
}
