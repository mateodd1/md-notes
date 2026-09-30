<?php

namespace Tests\Feature;

use App\Models\SharedNote;
use App\Models\User;
use App\Services\DemoAccount;
use App\Services\NoteSpace;
use App\Services\ShareTokens;
use Tests\TestCase;

class DemoAccountTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        config(['md-notes.demo_enabled' => false]);
        $this->withSession(['_token' => 'demo-test'])->withHeader('X-CSRF-TOKEN', 'demo-test');
    }

    public function test_disabled_demo_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'demo@demo', 'password' => 'demo']);

        $this->post(route('login.store'), ['email' => 'demo@demo', 'password' => 'demo', 'remember' => true])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_existing_demo_session_is_logged_out_before_a_write(): void
    {
        $user = User::factory()->create(['email' => 'demo@demo']);

        $this->actingAs($user)->postJson(route('notes.store'), ['name' => 'Blocked', 'parent' => ''])
            ->assertUnauthorized();
        $this->assertGuest();
        $this->assertSame([], app(NoteSpace::class)->tree($user));
    }

    public function test_disabled_demo_api_token_cannot_upload(): void
    {
        $user = User::factory()->create(['email' => 'demo@demo']);
        $token = $user->apiTokens()->create(['name' => 'Old token', 'token_hash' => hash('sha256', 'mdn_demo-test')]);

        $this->withToken('mdn_demo-test')->putJson(route('api.notes.upload', ['path' => 'Blocked.md']), ['content' => '# Blocked'])
            ->assertUnauthorized();
        $this->assertNull($token->fresh()->last_used_at);
        $this->assertSame([], app(NoteSpace::class)->tree($user));
    }

    public function test_disabled_demo_share_is_unavailable_without_deleting_it(): void
    {
        $user = User::factory()->create(['email' => 'demo@demo']);
        app(NoteSpace::class)->write($user, 'Demo.md', '# Demo');
        $share = SharedNote::query()->create([
            'user_id' => $user->id, 'path' => 'Demo.md',
            ...app(ShareTokens::class)->storedAttributes('Abc234'),
        ]);

        $this->get(route('shares.show', ['token' => 'Abc234']))->assertNotFound();
        $this->assertModelExists($share);
    }

    public function test_reset_command_leaves_disabled_demo_data_untouched(): void
    {
        $user = User::factory()->create(['email' => 'demo@demo']);
        app(NoteSpace::class)->write($user, 'Keep.md', '# Keep');

        $this->artisan('md-notes:reset-demo')->assertSuccessful();
        $this->assertSame('# Keep', app(NoteSpace::class)->read($user, 'Keep.md'));
    }

    public function test_reset_command_does_not_create_disabled_demo(): void
    {
        $this->artisan('md-notes:reset-demo')->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_reset_service_also_refuses_disabled_demo(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The demo account is disabled.');
        app(DemoAccount::class)->reset();
    }

    public function test_explicitly_enabled_demo_can_be_reset(): void
    {
        config(['md-notes.demo_enabled' => true]);

        $this->artisan('md-notes:reset-demo')->assertSuccessful();
        $user = User::query()->where('email', 'demo@demo')->firstOrFail();
        $this->assertNotEmpty(app(NoteSpace::class)->tree($user));
    }
}
