<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\NoteVersion;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class NotesTest extends TestCase
{
    private string $mediaPath;

    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        $this->mediaPath = sys_get_temp_dir().'/md-notes-media-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaPath);

        parent::tearDown();
    }

    public function test_a_note_can_contain_more_than_the_old_2048_character_limit(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $content = str_repeat('a', 4096);
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('write')->once()->withArgs(fn (User $owner, string $path, string $value): bool => $owner->is($user) && $path === 'Clase/Larga.md' && $value === $content);
        $this->app->instance(NoteSpace::class, $spaces);

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('referer', route('notes.show', ['path' => 'Clase/Larga.md']))
            ->actingAs($user)
            ->put(route('notes.update', ['path' => 'Clase/Larga.md']), ['_token' => 'test-token', 'content' => $content])
            ->assertRedirect(route('notes.show', ['path' => 'Clase/Larga.md']));
        $this->assertDatabaseHas('note_versions', ['user_id' => $user->id, 'path' => 'Clase/Larga.md', 'content' => $content]);
    }

    public function test_a_note_opens_in_reading_mode_with_an_edit_control(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('read')->once()->withArgs(fn (User $owner, string $path): bool => $owner->is($user) && $path === 'Clase/Lectura.md')->andReturn('# Lectura');
        $spaces->shouldReceive('tree')->once()->withArgs(fn (User $owner): bool => $owner->is($user))->andReturn([]);
        $this->app->instance(NoteSpace::class, $spaces);

        $this->actingAs($user)->get(route('notes.show', ['path' => 'Clase/Lectura.md']))
            ->assertOk()
            ->assertSee('id="editor-layout" class="editor-layout is-reading"', false)
            ->assertSee('Editar')
            ->assertSee('<h1>Lectura</h1>', false);
    }

    public function test_an_authenticated_user_can_refresh_their_storage_quota(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);

        $this->actingAs($user)->get(route('quota.show'))
            ->assertOk()
            ->assertJsonStructure(['used', 'limit', 'available', 'percentage', 'used_human', 'limit_human']);
    }

    public function test_a_user_can_open_the_history_of_their_own_note(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        NoteVersion::query()->create([
            'user_id' => $user->id,
            'path' => 'Clase/Historia.md',
            'content' => '# Primera versión',
        ]);
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('read')->once()->withArgs(fn (User $owner, string $path): bool => $owner->is($user) && $path === 'Clase/Historia.md')->andReturn('# Actual');
        $this->app->instance(NoteSpace::class, $spaces);

        $this->actingAs($user)->get(route('versions.index', ['path' => 'Clase/Historia.md']))
            ->assertOk()
            ->assertSee('Historial de versiones')
            ->assertSee('Ver')
            ->assertSee('Restaurar')
            ->assertSee('Descargar');
    }

    public function test_history_keeps_at_most_fifty_versions_and_removes_expired_ones(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $history = $this->app->make(NoteVersionHistory::class);

        foreach (range(1, 51) as $number) {
            $history->record($user, 'Clase/Versiones.md', 'Versión '.$number);
        }

        $this->assertSame(50, NoteVersion::query()->where('user_id', $user->id)->where('path', 'Clase/Versiones.md')->count());
        $this->assertDatabaseMissing('note_versions', ['user_id' => $user->id, 'path' => 'Clase/Versiones.md', 'content' => 'Versión 1']);

        $expired = NoteVersion::query()->create([
            'user_id' => $user->id,
            'path' => 'Clase/Antigua.md',
            'content' => 'Antigua',
        ]);
        $expired->forceFill(['created_at' => now()->subDays(8)])->save();
        $history->pruneExpired();

        $this->assertDatabaseMissing('note_versions', ['id' => $expired->id]);
    }

    public function test_trashing_and_restoring_a_note_preserves_its_version_history(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $spaces = $this->app->make(NoteSpace::class);
        $history = $this->app->make(NoteVersionHistory::class);
        $path = $spaces->createNote($user, '', 'Versionada');
        $history->record($user, $path, $spaces->read($user, $path));
        $spaces->write($user, $path, '# Segunda versión');
        $history->record($user, $path, '# Segunda versión');

        $entry = $spaces->trash($user, $path);
        $history->relocate($user, $path, $entry['history_path']);

        $this->assertSame(2, NoteVersion::query()->where('user_id', $user->id)->where('path', $entry['history_path'])->count());

        $spaces->restoreTrash($user, $entry['id']);
        $history->relocate($user, $entry['history_path'], $path);

        $this->assertSame(2, NoteVersion::query()->where('user_id', $user->id)->where('path', $path)->count());
    }

    public function test_deleting_a_note_moves_it_to_the_trash_until_it_is_restored(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $spaces = $this->app->make(NoteSpace::class);
        $path = $spaces->createNote($user, '', 'Papelera');
        $this->app->make(NoteVersionHistory::class)->record($user, $path, $spaces->read($user, $path));

        $this->withSession(['_token' => 'test-token'])->actingAs($user)->delete(route('notes.destroy', ['path' => $path]), ['_token' => 'test-token'])
            ->assertRedirect(route('notes.index'));

        $entry = $spaces->trashItems($user)[0];
        $this->assertDatabaseHas('note_versions', ['user_id' => $user->id, 'path' => $entry['history_path']]);
        $this->actingAs($user)->get(route('trash.index'))->assertOk()->assertSee('Papelera');
        $this->withSession(['_token' => 'test-token'])->actingAs($user)->post(route('trash.restore', ['id' => $entry['id']]), ['_token' => 'test-token'])
            ->assertRedirect(route('notes.index'));

        $this->assertSame("# Papelera\n\n", $spaces->read($user, $path));
        $this->assertDatabaseHas('note_versions', ['user_id' => $user->id, 'path' => $path]);
    }

    public function test_an_image_from_the_clipboard_can_be_uploaded_to_the_users_private_space(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $image = UploadedFile::fake()->createWithContent('clipboard.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLJDQAAAABJRU5ErkJggg=='));

        $response = $this->withSession(['_token' => 'test-token'])
            ->actingAs($user)
            ->post(route('media.store'), ['_token' => 'test-token', 'image' => $image]);

        $response->assertOk()->assertJsonPath('markdown', fn (string $markdown): bool => str_starts_with($markdown, '![](http'));
        $this->actingAs($user)->get($response->json('url'))->assertOk();
    }

    public function test_an_authenticated_user_can_open_an_attachment_using_the_legacy_media_url(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $filename = 'abcdefghijklmnopqrstuvwx.png';
        $directory = $this->mediaPath.'/'.$user->id.'/.md-notes-media';
        File::ensureDirectoryExists($directory);
        File::put($directory.'/'.$filename, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLJDQAAAABJRU5ErkJggg=='));

        $this->actingAs($user)
            ->get('/media/'.$filename)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
