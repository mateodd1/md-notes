<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NotePdfTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
    }

    public function test_a_guest_cannot_export_a_note(): void
    {
        $this->get(route('notes.pdf', ['path' => 'Apuntes.md']))
            ->assertRedirect(route('login'));
    }

    public function test_an_owner_can_download_a_real_pdf_with_the_original_note_name(): void
    {
        $user = User::factory()->create();
        $spaces = app(NoteSpace::class);
        $path = $spaces->createNote($user, '', 'Matemáticas');
        $content = "# Matemáticas\n\nUna línea\nOtra línea con **negrita** y `código`.\n\n| Tema | Estado |\n| --- | --- |\n| Álgebra | Revisado |\n";
        $spaces->write($user, $path, $content);

        $response = $this->actingAs($user)->get(route('notes.pdf', ['path' => $path]));

        $response->assertOk()->assertDownload('Matematicas.pdf')->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString("filename*=utf-8''Matem%C3%A1ticas.pdf", $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
        $this->assertStringContainsString('/Type /Page', $response->streamedContent());
        $this->assertSame($content, $spaces->read($user, $path));
        $this->assertSame([], File::directories(storage_path('app/private/pdf-tmp')));
        $this->assertSame([], File::allFiles(storage_path('app/private/pdf-tmp')));
        $this->assertDatabaseCount('note_versions', 0);
    }

    public function test_an_export_embeds_the_owners_image_even_when_it_uses_a_legacy_url(): void
    {
        $user = User::factory()->create();
        $image = app(NoteMedia::class)->store($user, UploadedFile::fake()->image('Horario.png', 32, 32));
        $spaces = app(NoteSpace::class);
        $path = $spaces->createNote($user, '', 'Horario');
        $spaces->write($user, $path, '# Horario'."\n\n![Horario](https://md.mateo.ovh/media/{$image})");

        $response = $this->actingAs($user)->get(route('notes.pdf', ['path' => $path]));

        $response->assertOk();
        $this->assertStringContainsString('/Subtype /Image', $response->streamedContent());
    }

    public function test_an_export_does_not_load_foreign_missing_external_or_unsafe_images(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $image = app(NoteMedia::class)->store($other, UploadedFile::fake()->image('Privada.png', 32, 32));
        $spaces = app(NoteSpace::class);
        $path = $spaces->createNote($owner, '', 'Seguridad');
        $spaces->write($owner, $path, implode("\n\n", [
            '# Seguridad',
            '![Ajena]('.route('media.show', ['filename' => $image]).')',
            '![Externa](http://127.0.0.1:9/image.png)',
            '![Local](file:///etc/passwd)',
            '![Ausente](/media/abcdefghijklmnopqrstuvwx.png)',
            '![SVG](data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><image href="file:///etc/passwd"/></svg>').')',
            '<script>alert(1)</script><img src="http://127.0.0.1:9/unsafe.png">',
        ]));

        $response = $this->actingAs($owner)->get(route('notes.pdf', ['path' => $path]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
        $this->assertStringNotContainsString('/Subtype /Image', $response->streamedContent());
        $this->assertStringNotContainsString('/JavaScript', $response->streamedContent());
    }

    public function test_a_user_cannot_export_another_users_note_or_escape_their_space(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $path = app(NoteSpace::class)->createNote($other, '', 'Privada');

        $this->actingAs($owner)->get(route('notes.pdf', ['path' => $path]))->assertNotFound();
        $this->get(route('notes.pdf', ['path' => '../'.$other->id.'/'.$path]))->assertNotFound();
        $this->get(route('notes.pdf', ['path' => 'config.php']))->assertNotFound();
    }

    public function test_the_note_context_menu_offers_pdf_export(): void
    {
        $user = User::factory()->create();
        $path = app(NoteSpace::class)->createNote($user, '', 'Apuntes');

        $this->actingAs($user)->get(route('notes.show', ['path' => $path]))
            ->assertOk()
            ->assertSee('data-context-action="pdf" data-context-note-only', false)
            ->assertSee(__('ui.export_pdf'));
    }

    public function test_pdf_export_does_not_shadow_notes_inside_a_folder_named_pdf(): void
    {
        $user = User::factory()->create();
        $spaces = app(NoteSpace::class);
        $spaces->createFolder($user, '', 'pdf');
        $path = $spaces->createNote($user, 'pdf', 'Apuntes');

        $this->actingAs($user)->get(route('notes.show', ['path' => $path]))
            ->assertOk()->assertSee('id="editor-layout"', false);
        $this->get(route('notes.pdf', ['path' => $path]))
            ->assertOk()->assertDownload('Apuntes.pdf')->assertHeader('Content-Type', 'application/pdf');
    }
}
