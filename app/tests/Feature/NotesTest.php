<?php

namespace Tests\Feature;

use App\Models\NoteVersion;
use App\Models\User;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use App\Services\StorageQuota;
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
        User::created(fn (User $user) => $user->markEmailAsVerified());
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
        $spaces->shouldReceive('synchronized')->once()->andReturnUsing(fn ($owner, $operation) => $operation());
        $spaces->shouldReceive('withNoteRollback')->once()->andReturnUsing(fn ($owner, $path, $operation) => $operation());
        $spaces->shouldReceive('write')->once()->withArgs(fn (User $owner, string $path, string $value): bool => $owner->is($user) && $path === 'Clase/Larga.md' && $value === $content);
        $spaces->allows('root')->andReturn($this->mediaPath.'/'.$user->id);
        $this->app->instance(NoteSpace::class, $spaces);

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('referer', route('notes.show', ['path' => 'Clase/Larga.md']))
            ->actingAs($user)
            ->put(route('notes.update', ['path' => 'Clase/Larga.md']), ['_token' => 'test-token', 'content' => $content, 'snapshot' => '1'])
            ->assertRedirect(route('notes.show', ['path' => 'Clase/Larga.md']));
        $this->assertDatabaseHas('note_versions', ['user_id' => $user->id, 'path' => 'Clase/Larga.md', 'content' => $content]);
    }

    public function test_an_automatic_save_does_not_create_a_version_snapshot(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $content = '# Guardado automático';
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('synchronized')->once()->andReturnUsing(fn ($owner, $operation) => $operation());
        $spaces->shouldReceive('withNoteRollback')->once()->andReturnUsing(fn ($owner, $path, $operation) => $operation());
        $spaces->shouldReceive('write')->once()->withArgs(fn (User $owner, string $path, string $value): bool => $owner->is($user) && $path === 'Clase/Auto.md' && $value === $content);
        $spaces->allows('root')->andReturn($this->mediaPath.'/'.$user->id);
        $this->app->instance(NoteSpace::class, $spaces);

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('referer', route('notes.show', ['path' => 'Clase/Auto.md']))
            ->actingAs($user)
            ->put(route('notes.update', ['path' => 'Clase/Auto.md']), ['_token' => 'test-token', 'content' => $content, 'snapshot' => '0'])
            ->assertRedirect(route('notes.show', ['path' => 'Clase/Auto.md']));

        $this->assertDatabaseMissing('note_versions', ['user_id' => $user->id, 'path' => 'Clase/Auto.md']);
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
        $spaces->shouldReceive('read')->once()->withArgs(fn (User $owner, string $path): bool => $owner->is($user) && $path === 'Clase/Lectura.md')->andReturn("# Lectura\n\nPrimera línea\nSegunda línea con `código`.");
        $spaces->shouldReceive('tree')->once()->withArgs(fn (User $owner): bool => $owner->is($user))->andReturn([[
            'type' => 'folder', 'name' => 'Clase', 'path' => 'Clase', 'collapsed' => true, 'pinned' => false,
            'children' => [['type' => 'note', 'name' => 'Lectura', 'path' => 'Clase/Lectura.md', 'pinned' => false]],
        ]]);
        $spaces->allows('root')->andReturn($this->mediaPath.'/'.$user->id);
        $this->app->instance(NoteSpace::class, $spaces);

        $this->actingAs($user)->get(route('notes.show', ['path' => 'Clase/Lectura.md']))
            ->assertOk()
            ->assertSee('id="editor-layout" class="editor-layout is-reading"', false)
            ->assertSee('autocomplete="off" data-create-form data-create-kind="folder"', false)
            ->assertSee('autocomplete="off" data-create-form data-create-kind="note"', false)
            ->assertSee('id="rename-name" name="name" required maxlength="80" autocomplete="off"', false)
            ->assertSee('<div class="preview-document">', false)
            ->assertSee(__('ui.edit'))
            ->assertSee('<h1>Lectura</h1>', false)
            ->assertSee("Primera línea<br>\nSegunda línea", false)
            ->assertSee('<code>código</code>', false)
            ->assertSee('<details class="tree-folder"  open', false)
            ->assertSee('data-context-trigger', false)
            ->assertSee('assets/md-notes-workspace.css', false);
    }

    public function test_invalid_names_return_field_errors_without_creating_notes_or_folders(): void
    {
        $user = User::factory()->create();
        $spaces = new NoteSpace($this->mediaPath);
        $this->app->instance(NoteSpace::class, $spaces);

        foreach (['folders.store', 'notes.store'] as $route) {
            $this->withSession(['_token' => 'test-token'])
                ->withHeader('X-CSRF-TOKEN', 'test-token')
                ->actingAs($user)->postJson(route($route), [
                    '_token' => 'test-token',
                    'parent' => '',
                    'name' => "27-\n  08",
                ])->assertUnprocessable()
                ->assertJsonPath('errors.name.0', __('ui.invalid_item_name'));
        }

        $this->assertSame([], $spaces->tree($user));
    }

    public function test_ajax_creation_returns_the_updated_tree_and_new_note_url(): void
    {
        $user = User::factory()->create();
        $spaces = new NoteSpace($this->mediaPath);
        $this->app->instance(NoteSpace::class, $spaces);

        $folderResponse = $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->actingAs($user)->postJson(route('folders.store'), [
                '_token' => 'test-token',
                'parent' => '',
                'name' => 'Proyecto',
                'active_path' => '',
            ])->assertCreated()
            ->assertJsonPath('message', __('ui.folder_created'))
            ->assertJsonStructure(['tree', 'parentOptions']);
        $this->assertStringContainsString('data-parent-value="Proyecto"', $folderResponse->json('parentOptions'));

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->actingAs($user)->postJson(route('notes.store'), [
                '_token' => 'test-token',
                'parent' => 'Proyecto',
                'name' => 'Guía',
            ])->assertCreated()
            ->assertJsonPath('path', 'Proyecto/Guía.md')
            ->assertJsonPath('url', route('notes.show', ['path' => 'Proyecto/Guía.md']))
            ->assertJsonPath('message', __('ui.note_created'))
            ->assertJsonStructure(['tree']);

        $this->assertFileExists($spaces->root($user).'/Proyecto/Guía.md');
        $this->assertDatabaseHas('note_versions', [
            'user_id' => $user->id,
            'path' => 'Proyecto/Guía.md',
        ]);
    }

    public function test_the_reader_lists_only_its_referenced_attachments_in_two_column_cards(): void
    {
        $user = User::factory()->create();
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $spaces = $this->app->make(NoteSpace::class);
        $path = $spaces->createNote($user, '', 'Adjuntos');

        $pdf = $this->withSession(['_token' => 'test-token'])
            ->actingAs($user)
            ->post(route('media.store'), [
                '_token' => 'test-token',
                'file' => UploadedFile::fake()->createWithContent('temario.pdf', '%PDF-1.4 test document'),
            ])
            ->assertOk();
        $image = $this->withSession(['_token' => 'test-token'])
            ->actingAs($user)
            ->post(route('media.store'), [
                '_token' => 'test-token',
                'file' => UploadedFile::fake()->create('esquema.jpg', 1, 'image/jpeg'),
            ])
            ->assertOk();
        $unreferenced = $this->withSession(['_token' => 'test-token'])
            ->actingAs($user)
            ->post(route('media.store'), [
                '_token' => 'test-token',
                'file' => UploadedFile::fake()->create('privado.zip', 1, 'application/zip'),
            ])
            ->assertOk();

        $spaces->write($user, $path, implode("\n", [
            '# Adjuntos',
            '[Temario]('.$pdf->json('url').')',
            '![Esquema]('.$image->json('url').')',
            '[Temario repetido]('.$pdf->json('url').')',
            '[Externo](https://example.com/manual.zip)',
        ]));

        $response = $this->actingAs($user)->get(route('notes.show', ['path' => $path]));

        $response->assertOk()
            ->assertSee('class="note-attachments-grid"', false)
            ->assertSee('temario.pdf')
            ->assertSee('esquema.jpg')
            ->assertSee('download="temario.pdf"', false)
            ->assertSee('download="esquema.jpg"', false)
            ->assertSee('data-pdf-preview-url="'.route('media.preview', ['filename' => basename((string) parse_url($pdf->json('url'), PHP_URL_PATH))]).'"', false)
            ->assertSee(__('ui.view_pdf'))
            ->assertSee('id="pdf-viewer"', false)
            ->assertDontSee('privado.zip')
            ->assertDontSee(basename((string) parse_url($unreferenced->json('url'), PHP_URL_PATH)));
        $this->assertSame(1, substr_count($response->getContent(), 'download="temario.pdf"'));
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

    public function test_a_note_properties_endpoint_includes_its_referenced_attachments(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
        ]);
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $spaces = $this->app->make(NoteSpace::class);
        $path = $spaces->createNote($user, '', 'Propiedades');
        $filename = 'abcdefghijklmnopqrstuvwx.pdf';
        $content = "# Propiedades\n\n[Archivo]({$filename})\n";
        $spaces->write($user, $path, $content);
        File::ensureDirectoryExists($this->mediaPath.'/'.$user->id.'/.md-notes-media');
        File::put($this->mediaPath.'/'.$user->id.'/.md-notes-media/'.$filename, 'datos');
        NoteVersion::query()->create([
            'user_id' => $user->id,
            'path' => $path,
            'content' => $content,
        ]);

        $this->actingAs($user)->get(route('notes.properties', ['path' => $path]))
            ->assertOk()
            ->assertJsonPath('markdown_bytes', strlen($content))
            ->assertJsonPath('attachments_count', 1)
            ->assertJsonPath('attachments_bytes', 5)
            ->assertJsonPath('total_bytes', strlen($content) + 5)
            ->assertJsonPath('created_at', fn (string $value): bool => str_contains($value, 'T'));
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
            ->assertSee(__('ui.version_history'))
            ->assertSee(__('ui.view'))
            ->assertSee(__('ui.restore'))
            ->assertSee(__('ui.download'));
    }

    public function test_version_history_is_counted_against_the_storage_quota(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
        ]);
        $user->forceFill(['storage_quota_bytes' => 10])->save();
        NoteVersion::query()->create([
            'user_id' => $user->id,
            'path' => 'Historia.md',
            'content' => str_repeat('a', 10),
        ]);

        $quota = $this->app->make(StorageQuota::class)->summary($user, $this->mediaPath.'/'.$user->id);

        $this->assertSame(10, $quota['used']);
        $this->assertSame(0, $quota['available']);
    }

    public function test_an_edit_that_would_exceed_the_quota_returns_a_validation_error(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
        ]);
        $user->forceFill(['storage_quota_bytes' => 30])->save();
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $spaces = $this->app->make(NoteSpace::class);
        $path = $spaces->createNote($user, '', 'Cuota');
        $spaces->write($user, $path, str_repeat('a', 15));
        $this->app->make(NoteVersionHistory::class)->record($user, $path, str_repeat('a', 15));

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('Accept', 'application/json')
            ->actingAs($user)
            ->put(route('notes.update', ['path' => $path]), [
                '_token' => 'test-token',
                'content' => str_repeat('b', 16),
                'snapshot' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
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
        $this->actingAs($user)->get(route('trash.show', ['id' => $entry['id']]))
            ->assertOk()
            ->assertSee('<h1>Papelera</h1>', false)
            ->assertDontSee('id="editor"', false);
        $this->withSession(['_token' => 'test-token'])->actingAs($user)->post(route('trash.restore', ['id' => $entry['id']]), ['_token' => 'test-token'])
            ->assertRedirect(route('notes.show', ['path' => $path]))
            ->assertSessionHas('status', __('ui.trash_restored'));

        $this->assertSame("# Papelera\n\n", $spaces->read($user, $path));
        $this->assertDatabaseHas('note_versions', ['user_id' => $user->id, 'path' => $path]);
    }

    public function test_trash_preview_returns_sanitized_markdown_and_preserves_its_images(): void
    {
        $user = User::factory()->create();
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $spaces = $this->app->make(NoteSpace::class);
        $filename = 'abcdefghijklmnopqrstuvwx.png';
        $mediaDirectory = $spaces->root($user).'/.md-notes-media';
        File::ensureDirectoryExists($mediaDirectory);
        File::put($mediaDirectory.'/'.$filename, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLJDQAAAABJRU5ErkJggg=='));
        $path = $spaces->createNote($user, '', 'Vista previa');
        $content = "# Apuntes\n\nPrimera línea\nSegunda línea\n\n**Importante**\n\n![Horario](/app/media/{$filename})\n\n<script>alert(1)</script>\n\n[Enlace](javascript:alert(1))";
        $spaces->write($user, $path, $content);
        $entry = $spaces->trash($user, $path);

        $this->actingAs($user)->get(route('trash.index'))->assertOk()
            ->assertSee('data-trash-preview-url="'.route('trash.show', ['id' => $entry['id']]).'"', false)
            ->assertSee('<dialog id="trash-preview"', false);

        $response = $this->getJson(route('trash.show', ['id' => $entry['id']]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('title', 'Vista previa')
            ->assertJsonPath('path', $path)
            ->assertJsonMissingPath('content');
        $rendered = $response->json('rendered');
        $this->assertStringContainsString('<h1>Apuntes</h1>', $rendered);
        $this->assertStringContainsString("Primera línea<br>\nSegunda línea", $rendered);
        $this->assertStringContainsString('<strong>Importante</strong>', $rendered);
        $this->assertStringContainsString('src="'.route('media.show', ['filename' => $filename]).'"', $rendered);
        $this->assertStringNotContainsString('<script', $rendered);
        $this->assertStringNotContainsString('javascript:', $rendered);
        $this->get(route('media.show', ['filename' => $filename]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($content, $spaces->trashedNote($user, $entry['id'])['content']);
        $this->assertFileDoesNotExist($spaces->root($user).'/'.$path);
    }

    public function test_trash_preview_is_only_available_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $spaces = $this->app->make(NoteSpace::class);
        $path = $spaces->createNote($owner, '', 'Privada');
        $entry = $spaces->trash($owner, $path);
        $url = route('trash.show', ['id' => $entry['id']]);

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($other)->getJson($url)->assertNotFound();
        $this->get($url)->assertNotFound();
        $this->actingAs($owner)->getJson($url)->assertOk()->assertJsonPath('title', 'Privada');
    }

    public function test_trash_preview_rejects_folders_and_notes_that_are_no_longer_in_the_trash(): void
    {
        $user = User::factory()->create();
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));
        $spaces = $this->app->make(NoteSpace::class);
        $spaces->createFolder($user, '', 'Carpeta');
        $folder = $spaces->trash($user, 'Carpeta');
        $this->actingAs($user)->getJson(route('trash.show', ['id' => $folder['id']]))->assertNotFound();
        $this->get(route('trash.index'))->assertOk()->assertDontSee('data-trash-preview-url=', false);

        $path = $spaces->createNote($user, '', 'Restaurada');
        $entry = $spaces->trash($user, $path);
        $spaces->restoreTrash($user, $entry['id']);
        $this->getJson(route('trash.show', ['id' => $entry['id']]))->assertNotFound();
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

    public function test_uploaded_attachments_use_the_original_name_when_downloaded(): void
    {
        $user = User::factory()->create();
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));

        $image = UploadedFile::fake()->createWithContent('horario de clase.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLJDQAAAABJRU5ErkJggg=='));
        $document = UploadedFile::fake()->createWithContent('apuntes de clase.pdf', '%PDF-1.4 test');
        foreach ([$image, $document] as $file) {
            $response = $this->withSession(['_token' => 'test-token'])
                ->actingAs($user)
                ->post(route('media.store'), ['_token' => 'test-token', 'file' => $file]);
            $response->assertOk();

            $download = $this->actingAs($user)->get($response->json('url'));
            $contentDisposition = (string) $download->headers->get('Content-Disposition');
            $this->assertStringContainsString($file->getClientOriginalName(), $contentDisposition);
            $this->assertStringContainsString($file->getMimeType() === 'image/png' ? 'inline' : 'attachment', $contentDisposition);
        }
    }

    public function test_a_pdf_can_be_previewed_inline_only_by_its_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));

        $upload = $this->withSession(['_token' => 'test-token'])
            ->actingAs($owner)
            ->post(route('media.store'), [
                '_token' => 'test-token',
                'file' => UploadedFile::fake()->createWithContent('manual privado.pdf', '%PDF-1.4 private document'),
            ])
            ->assertOk();

        $filename = basename((string) parse_url($upload->json('url'), PHP_URL_PATH));
        $previewUrl = route('media.preview', ['filename' => $filename]);
        $preview = $this->actingAs($owner)->get($previewUrl);

        $preview->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', (string) $preview->headers->get('Content-Disposition'));
        $this->assertStringContainsString('manual privado.pdf', (string) $preview->headers->get('Content-Disposition'));
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $preview->headers->get('Content-Security-Policy'));

        $this->actingAs($otherUser)->get($previewUrl)->assertNotFound();
        auth()->logout();
        $this->get($previewUrl)->assertRedirect(route('login'));
    }

    public function test_a_non_pdf_attachment_cannot_be_opened_in_the_pdf_viewer(): void
    {
        $user = User::factory()->create();
        $this->app->instance(NoteSpace::class, new NoteSpace($this->mediaPath));

        $upload = $this->withSession(['_token' => 'test-token'])
            ->actingAs($user)
            ->post(route('media.store'), [
                '_token' => 'test-token',
                'file' => UploadedFile::fake()->createWithContent('notas.txt', 'No es un PDF'),
            ])
            ->assertOk();

        $filename = basename((string) parse_url($upload->json('url'), PHP_URL_PATH));
        $this->actingAs($user)
            ->get(route('media.preview', ['filename' => $filename]))
            ->assertNotFound();
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

    public function test_the_reader_rewrites_legacy_attachment_urls_to_the_canonical_private_route(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'is_admin' => true,
        ]);
        $filename = 'abcdefghijklmnopqrstuvwx.png';
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('read')->once()->andReturn("![](https://md.mateo.ovh/media/{$filename})");
        $spaces->shouldReceive('tree')->once()->andReturn([]);
        $spaces->allows('root')->andReturn($this->mediaPath.'/'.$user->id);
        $this->app->instance(NoteSpace::class, $spaces);

        $this->actingAs($user)
            ->get(route('notes.show', ['path' => 'Horario.md']))
            ->assertOk()
            ->assertSee(route('media.show', ['filename' => $filename]), false);
    }
}
