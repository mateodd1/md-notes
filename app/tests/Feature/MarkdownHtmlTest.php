<?php

namespace Tests\Feature;

use App\Models\NoteVersion;
use App\Models\SharedNote;
use App\Models\User;
use App\Services\MarkdownMediaUrls;
use App\Services\MarkdownRenderer;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarkdownHtmlTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
    }

    public function test_html_tables_and_safe_formatting_are_preserved(): void
    {
        $html = app(MarkdownRenderer::class)->render($this->htmlContent());

        foreach (['<table>', '<caption>Plan</caption>', '<thead>', '<tbody>', 'colspan="2"', 'rowspan="2"', 'scope="col"', '<details', '<summary>Más información</summary>', '<kbd>Ctrl</kbd>', '<sub>2</sub>', '<sup>2</sup>', '<strong>Markdown dentro</strong>'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_html_is_sanitized_and_cannot_be_used_for_scripts_forms_or_app_dom_attributes(): void
    {
        $content = <<<'MD'
<div id="editor" class="button" name="csrf" style="position:fixed" onclick="alert(1)">
<script>alert(1)</script><style>body{display:none}</style><iframe srcdoc="<script>alert(1)</script>"></iframe>
<form action="/logout"><button>Send</button></form><svg onload="alert(1)"><a href="javascript:alert(1)">SVG</a></svg>
<math><mtext><img src=x onerror="alert(1)"></mtext></math>
<a href="javascript&#58;alert(1)" target="_blank">Script</a>
<a href="data:text/html,test">Data</a><a href="file:///etc/passwd">File</a><a href="ftp://example.test/file">FTP</a>
<img src="data:image/svg+xml,test" srcset="evil" onerror="alert(1)">
<input type="text" value="secret" autofocus formaction="/logout">
<a href="https://example.test/docs" title="Docs">Safe</a><a href="mailto:hello@example.test">Email</a>
</div>

- [x] Done
MD;
        $html = app(MarkdownRenderer::class)->render($content);

        foreach (['<script', '<style', '<iframe', '<form', '<button', '<svg', '<math', 'javascript:', 'data:', 'file:', 'ftp:', 'onclick=', 'onerror=', 'srcset=', 'id=', 'name=', 'style=', 'class=', 'autofocus', 'formaction', 'value='] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $html);
        }
        $this->assertStringContainsString('href="https://example.test/docs"', $html);
        $this->assertStringContainsString('href="mailto:hello@example.test"', html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('disabled="disabled"', $html);
        $this->assertStringContainsString('checked', $html);
        $this->assertStringNotContainsString('type="text"', $html);
    }

    public function test_large_notes_are_not_truncated_and_standard_markdown_keeps_working(): void
    {
        $content = "| Left | Right |\n| :--- | ---: |\n| One | Two |\n\n".str_repeat('A long paragraph. ', 4000)."\n\nEnd of the note **preserved**.\nAnother line.";
        $html = app(MarkdownRenderer::class)->render($content);

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('align="right"', $html);
        $this->assertStringContainsString('End of the note <strong>preserved</strong>.<br>', substr($html, -1000));
        $this->assertStringContainsString('Another line.', substr($html, -1000));
    }

    #[DataProvider('complexHtml')]
    public function test_excessive_html_falls_back_to_complete_inert_text(string $content): void
    {
        $content .= '\n<script>alert(1)</script>End of the original note.';

        $html = app(MarkdownRenderer::class)->render($content);

        $this->assertStringContainsString('class="markdown-fallback"', $html);
        $this->assertStringContainsString('<pre><code>'.e($content).'</code></pre>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<div>', $html);
    }

    public static function complexHtml(): array
    {
        return [
            'deep tree' => [str_repeat('<div>', 150).'Body'.str_repeat('</div>', 150)],
            'large tree previously timing out' => [str_repeat('<div>', 100000).'Body'.str_repeat('</div>', 100000)],
            'unclosed tags' => [str_repeat('<div>', 100001)],
            'too many attributes' => ['<div '.implode(' ', array_map(static fn (int $i): string => 'data-'.$i.'="value"', range(1, 65))).'>Body</div>'],
        ];
    }

    public function test_complex_notes_remain_accessible_in_shares_history_and_trash_without_changing_source(): void
    {
        $user = User::factory()->create();
        $spaces = app(NoteSpace::class);
        $content = str_repeat('<div>', 150).'Original content'.str_repeat('</div>', 150);
        $spaces->write($user, 'Complex.md', $content);
        app(NoteVersionHistory::class)->record($user, 'Complex.md', $content);
        $version = NoteVersion::query()->sole();
        $share = SharedNote::query()->create(['user_id' => $user->id, 'path' => 'Complex.md', 'token' => 'Ab2Cd3']);
        $fallback = app(MarkdownRenderer::class)->render($content);

        $this->actingAs($user)->get(route('notes.show', ['path' => 'Complex.md']))
            ->assertOk()->assertViewHas('rendered', $fallback);
        $this->get(route('shares.show', ['token' => $share->token]))
            ->assertOk()->assertViewHas('rendered', $fallback);
        $this->getJson(route('versions.show', ['version' => $version]))
            ->assertOk()->assertJsonPath('rendered', $fallback);
        $this->assertSame($content, $spaces->read($user, 'Complex.md'));
        $trash = $spaces->trash($user, 'Complex.md');
        $this->getJson(route('trash.show', ['id' => $trash['id']]))
            ->assertOk()->assertJsonPath('rendered', $fallback);
        $this->assertSame($content, $spaces->trashedNote($user, $trash['id'])['content']);

        $anonymous = SharedNote::query()->create([
            'path' => 'Complex.md', 'token' => 'De4Fg5', 'content' => $content,
            'content_bytes' => strlen($content), 'expires_at' => now()->addDays(30),
        ]);
        $this->get(route('shares.show', ['token' => $anonymous->token]))
            ->assertOk()->assertViewHas('rendered', $fallback);
    }

    public function test_notes_shares_versions_and_trash_use_the_same_safe_renderer(): void
    {
        $user = User::factory()->create();
        $spaces = app(NoteSpace::class);
        $content = $this->htmlContent();
        $spaces->write($user, 'Table.md', $content);
        app(NoteVersionHistory::class)->record($user, 'Table.md', $content);
        $version = NoteVersion::query()->sole();
        $share = SharedNote::query()->create(['user_id' => $user->id, 'path' => 'Table.md', 'token' => 'Ab2Cd3']);
        $safe = app(MarkdownRenderer::class)->render($content);

        $this->actingAs($user)->get(route('notes.show', ['path' => 'Table.md']))
            ->assertOk()->assertViewHas('rendered', $safe);
        $this->get(route('shares.show', ['token' => $share->token]))
            ->assertOk()->assertViewHas('rendered', $safe);
        $this->getJson(route('versions.show', ['version' => $version]))
            ->assertOk()->assertJsonPath('rendered', $safe);
        $trash = $spaces->trash($user, 'Table.md');
        $this->getJson(route('trash.show', ['id' => $trash['id']]))
            ->assertOk()->assertJsonPath('rendered', $safe);
        $this->assertSame($content, $spaces->trashedNote($user, $trash['id'])['content']);
    }

    public function test_anonymous_notes_can_render_safe_tables_without_exposing_private_media(): void
    {
        $content = $this->htmlContent();
        $share = SharedNote::query()->create([
            'path' => 'Table.md', 'token' => 'Ab2Cd3', 'content' => $content,
            'content_bytes' => strlen($content), 'expires_at' => now()->addDays(30),
        ]);

        $this->get(route('shares.show', ['token' => $share->token]))
            ->assertOk()->assertViewHas('rendered', app(MarkdownRenderer::class)->render($content, $share));
        $this->get(route('shares.media', ['token' => $share->token, 'filename' => 'abcdefghijklmnopqrstuvwx.png']))
            ->assertNotFound();
    }

    public function test_html_images_and_file_links_are_rewritten_and_authorized_in_shared_tables(): void
    {
        $owner = User::factory()->create();
        $media = app(NoteMedia::class);
        $image = $media->store($owner, UploadedFile::fake()->image('Schedule.png', 32, 32));
        $unused = $media->store($owner, UploadedFile::fake()->image('Private.png', 32, 32));
        $file = $media->store($owner, UploadedFile::fake()->createWithContent('plan.txt', 'The plan'));
        $content = '<table><tr><td><img alt="Schedule" src="https://md.mateo.ovh/media/'.$image.'"></td>'
            .'<td><a href="/app/media/'.$file.'">Plan</a></td></tr></table>';
        app(NoteSpace::class)->write($owner, 'Images.md', $content);
        $share = SharedNote::query()->create(['user_id' => $owner->id, 'path' => 'Images.md', 'token' => 'Ab2Cd3']);

        $this->get(route('shares.show', ['token' => $share->token]))->assertOk()
            ->assertSee(route('shares.media', ['token' => $share->token, 'filename' => $image]), false)
            ->assertSee(route('shares.media', ['token' => $share->token, 'filename' => $file]), false);
        $this->get(route('shares.media', ['token' => $share->token, 'filename' => $image]))->assertOk();
        $this->get(route('shares.media', ['token' => $share->token, 'filename' => $file]))->assertOk()->assertDownload('plan.txt');
        $this->get(route('shares.media', ['token' => $share->token, 'filename' => $unused]))->assertNotFound();
        $this->assertSame($content, app(NoteSpace::class)->read($owner, 'Images.md'));

        $urls = app(MarkdownMediaUrls::class);
        $this->assertFalse($urls->isReferenced($unused, '<img src="https://other.test/media/'.$unused.'">'));
        $this->assertFalse($urls->isReferenced($unused, '<div src="/media/'.$unused.'"></div>'));
    }

    public function test_copying_a_shared_html_table_copies_and_relinks_its_attachment(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $media = app(NoteMedia::class);
        $image = $media->store($owner, UploadedFile::fake()->image('Schedule.png', 32, 32));
        app(NoteSpace::class)->write($owner, 'Images.md', '<table><tr><td><img src="/media/'.$this->encodeFilename($image).'"></td></tr></table>');
        $share = SharedNote::query()->create(['user_id' => $owner->id, 'path' => 'Images.md', 'token' => 'Ab2Cd3']);

        $response = $this->actingAs($recipient)->withSession(['_token' => 'html-copy'])
            ->post(route('shares.copy', ['token' => $share->token]), ['_token' => 'html-copy']);

        $response->assertRedirect(route('notes.show', ['path' => 'Images (copy).md']))->assertSessionHasNoErrors();
        $copy = app(NoteSpace::class)->read($recipient, 'Images (copy).md');
        $this->assertStringContainsString('<table>', $copy);
        $this->assertStringNotContainsString($image, $copy);
        $attachments = $media->attachmentList($recipient, $copy);
        $this->assertCount(1, $attachments);
        $this->assertSame(File::get($media->path($owner, $image)), File::get($media->path($recipient, $attachments[0]['filename'])));
        $this->assertStringContainsString(route('media.show', ['filename' => $attachments[0]['filename']]), $copy);
    }

    #[DataProvider('htmlMediaElements')]
    public function test_top_level_html_media_is_accessible_through_the_share(string $element): void
    {
        $owner = User::factory()->create();
        $media = app(NoteMedia::class);
        $image = $media->store($owner, UploadedFile::fake()->image('Schedule.png', 32, 32));
        $unused = $media->store($owner, UploadedFile::fake()->image('Private.png', 32, 32));
        $content = sprintf($element, $this->encodeFilename($image));
        app(NoteSpace::class)->write($owner, 'Image.md', $content);
        $share = SharedNote::query()->create(['user_id' => $owner->id, 'path' => 'Image.md', 'token' => 'Ab2Cd3']);
        $url = route('shares.media', ['token' => $share->token, 'filename' => $image]);

        $this->get(route('shares.show', ['token' => $share->token]))->assertOk()->assertSee($url, false);
        $this->get($url)->assertOk();
        $this->get(route('shares.media', ['token' => $share->token, 'filename' => $unused]))->assertNotFound();
        $this->assertCount(1, $media->attachmentList($owner, $content));
    }

    public static function htmlMediaElements(): array
    {
        return [
            'image' => ['<img src="/media/%s">'],
            'file' => ['<a href="/media/%s">Download</a>'],
            'nested image' => ['<p><img src="/media/%s"></p>'],
        ];
    }

    #[DataProvider('attachmentLocations')]
    public function test_cleanup_keeps_entity_encoded_attachments_in_notes_versions_and_trash(string $location): void
    {
        $owner = User::factory()->create();
        $media = app(NoteMedia::class);
        $spaces = app(NoteSpace::class);
        $image = $media->store($owner, UploadedFile::fake()->image('Schedule.png', 32, 32));
        $imagePath = $media->path($owner, $image);
        $unused = $media->store($owner, UploadedFile::fake()->image('Unused.png', 32, 32));
        $unusedPath = $media->path($owner, $unused);
        $content = '<img src="/media/'.$this->encodeFilename($image).'">';
        $spaces->write($owner, 'Image.md', $content);
        if ($location === 'version') {
            app(NoteVersionHistory::class)->record($owner, 'Image.md', $content);
            $spaces->write($owner, 'Image.md', 'Image removed from the current note.');
        } elseif ($location === 'trash') {
            $spaces->trash($owner, 'Image.md');
        }
        $this->travel(2)->days();

        $this->assertSame(1, $media->pruneUnreferenced($owner));

        $this->assertFileExists($imagePath);
        $this->assertFileDoesNotExist($unusedPath);
        $summary = $media->attachmentSummary($owner, $content);
        $this->assertSame(1, $summary['attachments_count']);
        $this->assertSame(filesize($imagePath), $summary['attachments_bytes']);
    }

    public static function attachmentLocations(): array
    {
        return ['note' => ['note'], 'version only' => ['version'], 'trash only' => ['trash']];
    }

    private function encodeFilename(string $filename): string
    {
        return '&#'.ord($filename[0]).';'.str_replace('.', '&period;', substr($filename, 1));
    }

    public function test_html_media_rewriting_preserves_other_attributes_and_does_not_rewrite_foreign_urls(): void
    {
        $filename = 'abcdefghijklmnopqrstuvwx.png';
        $content = '<img alt="src=/media/'.$filename.'" src=\'/app/media/'.$filename.'?download=1&amp;a=2#image\'>'
            .'<img src=https://other.test/media/'.$filename.'>';
        $rewritten = app(MarkdownMediaUrls::class)->forAuthenticatedUser($content);

        $this->assertStringContainsString('alt="src=/media/'.$filename.'"', $rewritten);
        $this->assertStringContainsString('src="'.route('media.show', ['filename' => $filename]).'?download=1&amp;a=2#image"', $rewritten);
        $this->assertStringContainsString('<img src=https://other.test/media/'.$filename.'>', $rewritten);
    }

    private function htmlContent(): string
    {
        return <<<'MD'
# A table

<table>
<caption>Plan</caption>
<thead><tr><th scope="col" colspan="2">Tasks</th></tr></thead>
<tbody><tr><td rowspan="2">Documentation</td><td>H<sub>2</sub>O</td></tr><tr><td>x<sup>2</sup></td></tr></tbody>
</table>

<details open>
<summary>Más información</summary>

**Markdown dentro** con <kbd>Ctrl</kbd> + <kbd>C</kbd>.<br>Otra línea.

</details>

<script>alert(1)</script>
MD;
    }
}
