<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && request()->hasSession()) {
                request()->session()->put('auth_version', (int) $event->user->auth_version);
            }
        });

        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(6)->by('ip:'.$request->ip()),
            Limit::perMinutes(15, 20)->by('account:'.$this->emailKey($request)),
        ]);
        RateLimiter::for('password-email', fn (Request $request): array => [
            Limit::perMinute(3)->by('ip:'.$request->ip()),
            Limit::perMinutes(15, 3)->by('account:'.$this->emailKey($request)),
        ]);
        RateLimiter::for('password-reset', fn (Request $request): array => [
            Limit::perMinute(3)->by('ip:'.$request->ip()),
            Limit::perMinutes(15, 5)->by('account:'.$this->emailKey($request)),
        ]);
        RateLimiter::for('shares-read', fn (Request $request): array => $this->shareLimits($request, 60));
        RateLimiter::for('shares-media', fn (Request $request): array => $this->shareLimits($request, 120));
        RateLimiter::for('shares-copy', fn (Request $request): array => $this->shareLimits($request, 20));
        RateLimiter::for('api-notes-upload', function (Request $request): array {
            $ip = 'ip:'.$request->ip();

            if (! $request->headers->has('Authorization')) {
                return [
                    Limit::perMinute(3)->by('anonymous-minute:'.$ip),
                    Limit::perDay(20)->by('anonymous-day:'.$ip),
                ];
            }

            return [
                Limit::perMinute(60)->by('authenticated:'.hash('sha256', (string) $request->bearerToken())),
                Limit::perMinute(60)->by('authenticated-ip:'.$ip),
            ];
        });
    }

    private function emailKey(Request $request): string
    {
        $email = $request->input('email');

        return hash('sha256', is_string($email) ? mb_strtolower(trim($email)) : '');
    }

    /** @return array<int, Limit> */
    private function shareLimits(Request $request, int $maximum): array
    {
        $limits = [Limit::perMinute($maximum)->by('ip:'.$request->ip())];
        if ($request->user()) {
            $limits[] = Limit::perMinute($maximum)->by('user:'.$request->user()->getKey());
        }

        return $limits;
    }
}
