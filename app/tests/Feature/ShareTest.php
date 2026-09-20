<?php

namespace Tests\Feature;

use App\Models\SharedNote;
use App\Models\User;
use App\Services\NoteSpace;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class ShareTest extends TestCase
{
    private string $mediaPath;

    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        $this->mediaPath = sys_get_temp_dir().'/md-notes-shares-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaPath);

        parent::tearDown();
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
        $spaces->shouldReceive('read')->once()->andReturn("![](https://md.mateo.ovh/media/{$oldFilename})\n![](https://mdnotes.net/app/media/{$currentFilename})");
        $this->app->instance(NoteSpace::class, $spaces);

        $response = $this->get(route('shares.show', ['token' => $share->token]));

        $response->assertOk()
            ->assertSee(route('shares.media', ['token' => $share->token, 'filename' => $oldFilename]), false)
            ->assertSee(route('shares.media', ['token' => $share->token, 'filename' => $currentFilename]), false);
    }

    public function test_an_authenticated_user_can_copy_a_shared_note_and_its_attachments(): void
    {
        $owner = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
        ]);
        $recipient = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'otra-clave-segura',
        ]);
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $spaces = $this->app->make(NoteSpace::class);
        $filename = 'abcdefghijklmnopqrstuvwx.png';
        $content = "# Apuntes\n\n![](https://mdnotes.net/app/media/{$filename})";
        $spaces->writeFromApi($owner, 'Clase/Apuntes.md', $content, snapshot: false);
        $ownerMedia = $spaces->root($owner).'/.md-notes-media';
        File::ensureDirectoryExists($ownerMedia);
        File::put($ownerMedia.'/'.$filename, 'shared image');
        $share = SharedNote::query()->create([
            'user_id' => $owner->id,
            'path' => 'Clase/Apuntes.md',
            'token' => 'A2BCD',
        ]);

        $this->actingAs($recipient)
            ->get(route('shares.show', ['token' => $share->token]))
            ->assertOk()
            ->assertSee(__('ui.copy_to_your_space'));

        $this->withSession(['_token' => 'test-token'])
            ->actingAs($recipient)
            ->post(route('shares.copy', ['token' => $share->token]), ['_token' => 'test-token'])
            ->assertRedirect(route('notes.show', ['path' => 'Apuntes (copy).md']));

        $copied = $spaces->read($recipient, 'Apuntes (copy).md');
        $this->assertMatchesRegularExpression('/app\/media\/([a-z0-9]{24}\.png)/', $copied);
        preg_match('/app\/media\/([a-z0-9]{24}\.png)/', $copied, $matches);
        $this->assertNotSame($filename, $matches[1]);
        $this->assertFileExists($spaces->root($recipient).'/.md-notes-media/'.$matches[1]);
        $this->actingAs($recipient)->get(route('media.show', ['filename' => $matches[1]]))->assertOk();
        $this->assertDatabaseHas('note_versions', ['user_id' => $recipient->id, 'path' => 'Apuntes (copy).md']);
    }

    public function test_a_share_owner_does_not_see_the_copy_to_space_action(): void
    {
        $owner = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
        ]);
        $share = SharedNote::query()->create([
            'user_id' => $owner->id,
            'path' => 'Clase/Apuntes.md',
            'token' => 'A2BCD',
        ]);
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('read')->once()->andReturn('# Apuntes');
        $this->app->instance(NoteSpace::class, $spaces);

        $this->actingAs($owner)
            ->get(route('shares.show', ['token' => $share->token]))
            ->assertOk()
            ->assertDontSee(__('ui.copy_to_your_space'));
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
