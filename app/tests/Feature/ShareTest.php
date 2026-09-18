<?php

namespace Tests\Feature;

use App\Models\SharedNote;
use App\Models\User;
use App\Services\NoteSpace;
use Mockery;
use Tests\TestCase;

class ShareTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
    }

    public function test_an_authenticated_user_can_create_a_short_temporary_share_link(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('read')->once()->andReturn('# Apuntes');
        $this->app->instance(NoteSpace::class, $spaces);

        $response = $this->withSession(['_token' => 'test-token'])
            ->withHeader('referer', route('notes.show', ['path' => 'Otra.md']))
            ->actingAs($user)
            ->post(route('shares.store'), [
            '_token' => 'test-token',
            'path' => 'Clase/Apuntes.md',
            'duration' => '24h',
            ]);

        $response->assertRedirect(route('notes.show', ['path' => 'Otra.md']));
        $share = SharedNote::query()->sole();
        $this->assertSame($user->id, $share->user_id);
        $this->assertMatchesRegularExpression('/^[0123456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5}$/', $share->token);
        $this->assertTrue($share->expires_at->isFuture());
    }

    public function test_a_share_link_renders_the_note_without_authentication(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        SharedNote::query()->create([
            'user_id' => $user->id,
            'path' => 'Clase/Apuntes.md',
            'token' => 'A2BCD',
        ]);
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('read')->once()->withArgs(fn (User $owner, string $path): bool => $owner->is($user) && $path === 'Clase/Apuntes.md')->andReturn("# Apuntes\n\nPrimera línea\nSegunda línea");
        $this->app->instance(NoteSpace::class, $spaces);

        $this->get(route('shares.show', ['token' => 'A2BCD']))
            ->assertOk()
            ->assertSee('Apuntes')
            ->assertSee("Primera línea<br>\nSegunda línea", false)
            ->assertSee(__('ui.shared_note'))
            ->assertSee('◐ '.__('ui.theme'))
            ->assertSee('padding:26px clamp(18px,12vw,260px)', false)
            ->assertSee('max-width:1440px', false);
    }

    public function test_a_share_rewrites_media_urls_from_before_and_after_the_app_prefix(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $share = SharedNote::query()->create([
            'user_id' => $user->id,
            'path' => 'Clase/Apuntes.md',
            'token' => 'A2BCD',
        ]);
        $oldFilename = 'abcdefghijklmnopqrstuvwx.png';
        $currentFilename = 'zyxwvutsrqponmlkjihgfedc.jpg';
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('read')->once()->andReturn("![](/media/{$oldFilename})\n![](/app/media/{$currentFilename})");
        $this->app->instance(NoteSpace::class, $spaces);

        $response = $this->get(route('shares.show', ['token' => $share->token]));

        $response->assertOk()
            ->assertSee(route('shares.media', ['token' => $share->token, 'filename' => $oldFilename]), false)
            ->assertSee(route('shares.media', ['token' => $share->token, 'filename' => $currentFilename]), false);
    }

    public function test_an_owner_can_manage_their_share_without_accessing_another_users_share(): void
    {
        $owner = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $otherUser = User::query()->create([
            'name' => 'Otra persona',
            'email' => 'otra@example.test',
            'password' => 'una-clave-segura',
        ]);
        $ownShare = SharedNote::query()->create([
            'user_id' => $owner->id,
            'path' => 'Clase/Privada.md',
            'token' => 'A2BCD',
        ]);
        $otherShare = SharedNote::query()->create([
            'user_id' => $otherUser->id,
            'path' => 'Otra/Secreta.md',
            'token' => 'E3FGH',
        ]);

        $this->actingAs($owner)->get(route('shares.index'))
            ->assertOk()
            ->assertSee('Clase/Privada.md')
            ->assertDontSee('Otra/Secreta.md');

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('referer', route('shares.index'))
            ->actingAs($owner)
            ->patch(route('shares.update', ['share' => $ownShare]), ['_token' => 'test-token', 'duration' => '1h'])
            ->assertRedirect(route('shares.index'));
        $this->assertTrue($ownShare->refresh()->expires_at->isFuture());

        $this->withSession(['_token' => 'test-token'])
            ->actingAs($owner)
            ->delete(route('shares.destroy', ['share' => $otherShare]), ['_token' => 'test-token'])
            ->assertNotFound();
        $this->assertDatabaseHas('shared_notes', ['id' => $otherShare->id]);
    }
}
