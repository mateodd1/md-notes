<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuthentication
{
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $plainToken = $request->bearerToken();

        if ($mode === 'optional' && ! $request->headers->has('Authorization')) {
            return $next($request);
        }

        if (! is_string($plainToken) || ! str_starts_with($plainToken, 'mdn_')) {
            return response()->json(['message' => 'An API token is required.'], 401);
        }

        $token = ApiToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token || ! $token->user) {
            return response()->json(['message' => 'The API token is invalid or has been revoked.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(static fn () => $token->user);

        return $next($request);
    }
}
