<?php

namespace Tests\Feature;

use App\Models\ProfileVerificationCode;
use App\Models\SharedNote;
use App\Models\User;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use App\Services\ShareTokens;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        $this->withSession(['_token' => 'security-test'])
            ->withHeader('X-CSRF-TOKEN', 'security-test');
    }

    public function test_session_cookie_is_host_only_secure_http_only_and_uses_the_host_prefix(): void
    {
        $response = $this->get(route('login'));
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === '__Host-md-notes-session');

        $this->assertNotNull($cookie);
        $this->assertNull($cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_only_the_configured_proxy_can_forward_the_client_ip_and_https(): void
    {
        config(['md-notes.trusted_proxies' => ['172.24.0.2']]);
        Route::get('/_test/client', fn (Request $request) => response()->json([
            'ip' => $request->ip(), 'secure' => $request->secure(), 'host' => $request->getHost(), 'port' => $request->getPort(),
        ]));

        $this->withServerVariables(['REMOTE_ADDR' => '172.24.0.2'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.8, 203.0.113.9', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example', 'X-Forwarded-Port' => '9999'])
            ->getJson('http://localhost/_test/client')->assertJsonPath('ip', '203.0.113.9')
            ->assertJsonPath('secure', true)->assertJsonPath('host', 'localhost')->assertJsonPath('port', 443);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->getJson('http://localhost/_test/client')->assertJsonPath('ip', '203.0.113.11')->assertJsonPath('secure', false);
    }

    public function test_forging_forwarded_ips_does_not_reset_the_login_limit(): void
    {
        $this->freezeTime();
        config(['md-notes.trusted_proxies' => ['172.24.0.2']]);
        $this->withServerVariables(['REMOTE_ADDR' => '172.24.0.2']);
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->withHeader('X-Forwarded-For', '198.51.100.'.$attempt.', 203.0.113.9')
                ->postJson(route('login.store'), [])->assertUnprocessable();
        }

        $this->withHeader('X-Forwarded-For', '198.51.100.99, 203.0.113.9')
            ->postJson(route('login.store'), [])->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_login_is_limited_per_normalized_account_even_from_different_ips(): void
    {
        $this->freezeTime();
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$attempt])
                ->postJson(route('login.store'), ['email' => 'victim@example.test'])->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->postJson(route('login.store'), ['email' => 'VICTIM@example.test'])->assertTooManyRequests();
    }

    public static function passwordEndpoints(): array
    {
        return [['password.email', 3], ['password.update', 5]];
    }

    #[DataProvider('passwordEndpoints')]
    public function test_password_endpoints_limit_the_account_across_ips(string $route, int $maximum): void
    {
        $this->freezeTime();
        for ($attempt = 1; $attempt <= $maximum; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$attempt])
                ->postJson(route($route), ['email' => 'invalid-address'])->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->postJson(route($route), ['email' => 'INVALID-ADDRESS'])->assertTooManyRequests();
    }

    public static function previousSessions(): array
    {
        return ['legacy session' => [null], 'versioned session' => [0]];
    }

    #[DataProvider('previousSessions')]
    public function test_resetting_password_revokes_previous_sessions_and_remember_cookies(?int $version): void
    {
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        app(NoteSpace::class)->write($user, 'Private.md', 'Private content');
        $oldSession = $this->createFileSession($user, $version);
        $this->useFileSession($this->createFileSession());
        $recallerName = Auth::guard()->getRecallerName();
        $recaller = $user->id.'|'.$user->remember_token.'|'.$user->password;

        $this->post(route('password.update'), [
            '_token' => 'security-test',
            'token' => Password::createToken($user), 'email' => $user->email,
            'password' => 'Changed-password', 'password_confirmation' => 'Changed-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Changed-password', $user->fresh()->password));
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->useFileSession($oldSession);
        $this->get(route('notes.show', ['path' => 'Private.md']))->assertRedirect(route('login'));
        $this->clearSessionGuard();
        $this->withCookie($recallerName, $recaller)
            ->getJson(route('notes.show', ['path' => 'Private.md']))->assertUnauthorized();

        $this->clearSessionGuard();
        $this->useFileSession($this->createFileSession());
        $this->post(route('login.store'), [
            '_token' => 'security-test', 'email' => $user->email, 'password' => 'Changed-password',
        ])
            ->assertRedirect(route('notes.show', ['path' => 'Private.md']))->assertSessionHas('auth_version', 1);
        $currentSession = app('session')->driver()->getId();
        $this->useFileSession($currentSession);
        $this->get(route('notes.show', ['path' => 'Private.md']))->assertSee('Private content');
    }

    public function test_profile_password_change_preserves_current_session_and_revokes_other_sessions(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        $otherSession = $this->createFileSession($user, 0);
        $currentSession = $this->createFileSession($user, 0);
        ProfileVerificationCode::query()->create([
            'user_id' => $user->id, 'purpose' => 'password', 'code_hash' => Hash::make('234567'),
            'expires_at' => now()->addMinutes(15),
        ]);
        $this->useFileSession($currentSession);

        $this->patch(route('profile.password'), [
            'code' => '234567', 'new_password' => 'Changed-password', 'new_password_confirmation' => 'Changed-password',
        ])->assertSessionHasNoErrors()->assertSessionHas('auth_version', 1);

        $newSession = app('session')->driver()->getId();
        $this->assertNotSame($currentSession, $newSession);
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
        $this->assertTrue(Hash::check('Changed-password', $user->fresh()->password));
        $this->useFileSession($newSession);
        $this->get(route('profile.edit'))->assertOk();
        $this->useFileSession($otherSession);
        $this->getJson(route('profile.edit'))->assertUnauthorized();
    }

    public function test_an_invalid_reset_does_not_revoke_the_existing_session(): void
    {
        $user = User::factory()->create();
        $oldSession = $this->createFileSession($user, null);
        $this->useFileSession($this->createFileSession());

        $this->post(route('password.update'), [
            '_token' => 'security-test',
            'token' => 'invalid', 'email' => $user->email,
            'password' => 'Changed-password', 'password_confirmation' => 'Changed-password',
        ])->assertSessionHasErrors('email');

        $this->assertSame(0, $user->fresh()->auth_version);
        $this->useFileSession($oldSession);
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_share_copy_is_limited_per_account_even_when_the_ip_changes(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $this->actingAs($user);
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$attempt])
                ->post(route('shares.copy', ['token' => 'aB2cD3']))->assertNotFound();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->postJson(route('shares.copy', ['token' => 'aB2cD3']))->assertTooManyRequests();
        $this->assertDatabaseCount('note_versions', 0);
    }

    public function test_share_copy_is_limited_per_ip_even_when_the_account_changes(): void
    {
        $this->freezeTime();
        $first = User::factory()->create();
        $second = User::factory()->create();
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->actingAs($first)->post(route('shares.copy', ['token' => 'aB2cD3']))->assertNotFound();
        }

        $this->actingAs($second)->postJson(route('shares.copy', ['token' => 'aB2cD3']))->assertTooManyRequests();
    }

    public function test_copy_requires_authentication(): void
    {
        $this->postJson(route('shares.copy', ['token' => 'aB2cD3']))->assertUnauthorized();
    }

    public static function publicShareEndpoints(): array
    {
        return [
            ['shares.show', ['token' => 'aB2cD3'], 60],
            ['shares.media', ['token' => 'aB2cD3', 'filename' => 'abcdefghijklmnopqrstuvwx.png'], 120],
        ];
    }

    #[DataProvider('publicShareEndpoints')]
    public function test_public_share_lookups_are_limited_despite_spoofed_headers(string $route, array $parameters, int $maximum): void
    {
        $this->freezeTime();
        config(['md-notes.trusted_proxies' => ['172.24.0.2']]);
        $this->withServerVariables(['REMOTE_ADDR' => '172.24.0.2']);
        for ($attempt = 1; $attempt <= $maximum; $attempt++) {
            $this->withHeader('X-Forwarded-For', '198.51.100.'.$attempt.', 203.0.113.9')
                ->getJson(route($route, $parameters))->assertNotFound();
        }

        $this->withHeader('X-Forwarded-For', '198.51.100.250, 203.0.113.9')
            ->getJson(route($route, $parameters))->assertTooManyRequests();
    }

    public static function readableTokens(): array
    {
        return [['aB2cD3'], ['A2BCD'], ['A2BCD34']];
    }

    #[DataProvider('readableTokens')]
    public function test_new_and_legacy_shares_and_attachments_remain_readable(string $token): void
    {
        $user = User::factory()->create();
        $filename = app(NoteMedia::class)->store($user, UploadedFile::fake()->image('image.png', 8, 8));
        app(NoteSpace::class)->write($user, 'Shared.md', "# Shared content\n![](/media/".$filename.')');
        SharedNote::query()->create(['user_id' => $user->id, 'path' => 'Shared.md', 'token' => $token]);

        $response = $this->get(route('shares.show', ['token' => $token]))
            ->assertSee('Shared content')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(route('shares.media', ['token' => $token, 'filename' => $filename]))->assertOk();
    }

    public function test_tokens_that_only_differ_in_case_refer_to_different_notes(): void
    {
        $user = User::factory()->create();
        app(NoteSpace::class)->write($user, 'First.md', 'First private note');
        app(NoteSpace::class)->write($user, 'Second.md', 'Second private note');
        SharedNote::query()->create(['user_id' => $user->id, 'path' => 'First.md', 'token' => 'aB2cD3']);
        SharedNote::query()->create(['user_id' => $user->id, 'path' => 'Second.md', 'token' => 'Ab2Cd3']);

        $this->get(route('shares.show', ['token' => 'aB2cD3']))->assertSee('First private note')->assertDontSee('Second private note');
        $this->get(route('shares.show', ['token' => 'Ab2Cd3']))->assertSee('Second private note')->assertDontSee('First private note');
        $this->get(route('shares.show', ['token' => 'AB2CD3']))->assertNotFound();
    }

    public function test_generating_a_share_retries_token_collisions(): void
    {
        $user = User::factory()->create();
        app(NoteSpace::class)->write($user, 'Shared.md', 'Example');
        SharedNote::query()->create(['user_id' => $user->id, 'path' => 'Shared.md', 'token' => 'aB2cD3']);
        $this->partialMock(ShareTokens::class, function ($mock): void {
            $mock->shouldReceive('generate')->twice()->andReturn('aB2cD3', 'xY3zA4');
        });

        $this->actingAs($user)->post(route('shares.store'), ['path' => 'Shared.md', 'duration' => '24h'])
            ->assertSessionHasNoErrors()->assertSessionHas('share_url', app(ShareTokens::class)->publicUrl('xY3zA4'));

        $this->assertDatabaseHas('shared_notes', ['user_id' => $user->id, 'token' => 'xY3zA4']);
    }

    private function createFileSession(?User $user = null, ?int $version = null): string
    {
        config(['session.driver' => 'file', 'session.files' => storage_path('framework/sessions')]);
        File::ensureDirectoryExists(storage_path('framework/sessions'));
        $this->clearSessionGuard();
        $session = app('session')->driver();
        $session->setId(bin2hex(random_bytes(20)));
        $session->start();
        $session->put('_token', 'security-test');
        if ($user !== null) {
            $session->put(Auth::guard()->getName(), $user->id);
        }
        if ($user !== null && $version !== null) {
            $session->put('auth_version', $version);
        }
        $session->save();
        $id = $session->getId();
        $this->clearSessionGuard();

        return $id;
    }

    private function useFileSession(string $id): void
    {
        $this->clearSessionGuard();
        $this->withCookie(config('session.cookie'), $id);
    }

    private function clearSessionGuard(): void
    {
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
