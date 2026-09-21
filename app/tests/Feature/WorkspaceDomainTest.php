<?php

namespace Tests\Feature;

use App\Models\SharedNote;
use App\Models\User;
use App\Services\NoteSpace;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WorkspaceDomainTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        config([
            'md-notes.canonical_url' => 'https://mdnotes.net',
            'md-notes.workspace_url' => 'https://app.mdnotes.net',
            'md-notes.legacy_hosts' => ['md.mateo.ovh'],
        ]);
        Route::setRoutes(new RouteCollection);
        Route::middleware('web')->group(base_path('routes/web.php'));
        Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));
        Route::getRoutes()->refreshNameLookups();
        $this->app['url']->setRoutes(Route::getRoutes());
    }

    public function test_landing_stays_on_public_domain_and_workspace_uses_the_subdomain_root(): void
    {
        $this->withHeader('Accept-Language', 'es')->get('https://mdnotes.net/')->assertOk()
            ->assertSee('https://app.mdnotes.net/login', false)
            ->assertSee('https://app.mdnotes.net/signup', false)
            ->assertSee('https://app.mdnotes.net/session-status', false)
            ->assertHeader('Content-Security-Policy', "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self' https://app.mdnotes.net; font-src 'self' data:");
        $this->get('https://mdnotes.net/documentation.md')->assertOk();
        $this->get('https://app.mdnotes.net/')->assertRedirect('https://app.mdnotes.net/login');
        $this->get('https://app.mdnotes.net/login')->assertOk();
        $this->get('https://app.mdnotes.net/signup')->assertOk();
        $this->assertSame('https://app.mdnotes.net', rtrim(route('notes.index'), '/'));
        $this->assertSame('https://app.mdnotes.net/settings', route('profile.edit'));
        $this->assertSame('https://mdnotes.net/api/notes/Test.md', route('api.notes.upload', ['path' => 'Test.md']));
        $this->assertSame('https://mdnotes.net', rtrim(route('home'), '/'));
        $this->assertSame('https://mdnotes.net/share/A2BCD', route('shares.short', ['token' => 'A2BCD']));
        $this->assertSame('https://app.mdnotes.net/share/A2BCD', route('shares.show', ['token' => 'A2BCD']));
        $this->assertSame('https://app.mdnotes.net/share/A2BCD/media/abcdefghijklmnopqrstuvwx.png', route('shares.media', ['token' => 'A2BCD', 'filename' => 'abcdefghijklmnopqrstuvwx.png']));
        $this->assertSame('https://app.mdnotes.net/share/A2BCD/copy', route('shares.copy', ['token' => 'A2BCD']));
        $this->assertSame('https://app.mdnotes.net/reset-password/reset-test', route('password.reset', ['token' => 'reset-test']));
    }

    public function test_landing_can_detect_a_workspace_session_without_sharing_the_cookie_domain(): void
    {
        $guestResponse = $this->withHeader('Origin', 'https://mdnotes.net')
            ->getJson('https://app.mdnotes.net/session-status')
            ->assertOk()
            ->assertExactJson(['authenticated' => false])
            ->assertHeader('Access-Control-Allow-Origin', 'https://mdnotes.net')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $this->assertTrue($guestResponse->headers->contains('Vary', 'Origin'));
        $this->assertStringContainsString('no-store', (string) $guestResponse->headers->get('Cache-Control'));

        $user = User::factory()->create();
        $this->actingAs($user)
            ->withHeader('Origin', 'https://mdnotes.net')
            ->getJson('https://app.mdnotes.net/session-status')
            ->assertOk()
            ->assertExactJson(['authenticated' => true]);

        $untrustedResponse = $this->withHeader('Origin', 'https://example.com')
            ->getJson('https://app.mdnotes.net/session-status')
            ->assertOk();

        $this->assertFalse($untrustedResponse->headers->has('Access-Control-Allow-Origin'));
    }

    public function test_old_note_share_media_and_account_links_preserve_paths_and_queries(): void
    {
        $links = [
            'https://mdnotes.net/app' => 'https://app.mdnotes.net/',
            'https://mdnotes.net/app/?page=2' => 'https://app.mdnotes.net/?page=2',
            'https://mdnotes.net/app/Clase/Una%20nota.md?mode=read' => 'https://app.mdnotes.net/Clase/Una%20nota.md?mode=read',
            'https://mdnotes.net/app/app/Clase/Apuntes.md' => 'https://app.mdnotes.net/app/Clase/Apuntes.md',
            'https://mdnotes.net/share/A2BCD?source=link' => 'https://app.mdnotes.net/share/A2BCD?source=link',
            'https://md.mateo.ovh/share/A2BCD' => 'https://mdnotes.net/share/A2BCD',
            'https://mdnotes.net/share/A2BCD/media/abcdefghijklmnopqrstuvwx.png' => 'https://app.mdnotes.net/share/A2BCD/media/abcdefghijklmnopqrstuvwx.png',
            'https://mdnotes.net/app/media/abcdefghijklmnopqrstuvwx.png' => 'https://app.mdnotes.net/media/abcdefghijklmnopqrstuvwx.png',
            'https://md.mateo.ovh/media/abcdefghijklmnopqrstuvwx.pdf' => 'https://app.mdnotes.net/media/abcdefghijklmnopqrstuvwx.pdf',
            'https://mdnotes.net/reset-password/test?email=user%40example.com' => 'https://app.mdnotes.net/reset-password/test?email=user%40example.com',
            'https://mdnotes.net/account-export/'.str_repeat('a', 64) => 'https://app.mdnotes.net/account-export/'.str_repeat('a', 64),
            'https://md.mateo.ovh/documentation.md' => 'https://mdnotes.net/documentation.md',
        ];
        foreach ($links as $old => $new) {
            $this->get($old)->assertStatus(308)->assertRedirect($new);
        }
    }

    public function test_authenticated_notes_and_ajax_urls_use_the_subdomain_without_app_prefix(): void
    {
        $user = User::factory()->create();
        app(NoteSpace::class)->writeFromApi($user, 'Class/Note.md', '# My note', snapshot: false);
        $this->actingAs($user)->get('https://app.mdnotes.net/Class/Note.md')->assertOk()
            ->assertSee('https://app.mdnotes.net/Class/Note.md', false)
            ->assertSee('https:\/\/app.mdnotes.net\/history', false)
            ->assertDontSee('https:\/\/app.mdnotes.net\/app', false);
        $this->getJson('https://app.mdnotes.net/search?q=note')->assertOk()->assertJsonPath('results.0.path', 'Class/Note.md');
    }

    public function test_new_and_old_api_urls_accept_tokens_without_redirecting_the_request(): void
    {
        $user = User::factory()->create();
        $token = 'mdn_workspace_migration_test';
        $user->apiTokens()->create(['name' => 'Test', 'token_hash' => hash('sha256', $token)]);
        foreach (['mdnotes.net', 'md.mateo.ovh'] as $index => $host) {
            $path = 'Class/Note'.$index.'.md';
            $this->call('PUT', 'https://'.$host.'/api/notes/'.$path, [], [], [], [
                'CONTENT_TYPE' => 'text/markdown',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ], '# Uploaded note')->assertCreated()->assertJsonPath('url', 'https://app.mdnotes.net/'.$path);
            $this->assertSame('# Uploaded note', app(NoteSpace::class)->read($user, $path));
        }
    }

    public function test_folders_named_app_or_api_remain_accessible_in_the_workspace(): void
    {
        $user = User::factory()->create();
        foreach (['app/Note.md', 'api/notes/Note.md'] as $path) {
            app(NoteSpace::class)->writeFromApi($user, $path, '# Folder note', snapshot: false);
            $this->actingAs($user)->get('https://app.mdnotes.net/'.$path)->assertOk()
                ->assertSee('Folder note');
        }
    }

    public function test_images_from_all_previous_domains_render_in_notes_and_public_shares(): void
    {
        $user = User::factory()->create();
        $filename = 'abcdefghijklmnopqrstuvwx.png';
        $mediaDirectory = app(NoteSpace::class)->root($user).'/.md-notes-media';
        File::ensureDirectoryExists($mediaDirectory);
        File::put($mediaDirectory.'/'.$filename, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jhyoAAAAASUVORK5CYII='));
        $content = implode("\n", array_map(fn ($base) => "![Image]({$base}/{$filename})", [
            'https://mdnotes.net/app/media',
            'https://mdnotes.net/media',
            'https://md.mateo.ovh/app/media',
            'https://app.mdnotes.net/media',
            '/app/media',
        ]));
        $content .= "\n\n![Titled](https://mdnotes.net/app/media/{$filename} \"A title\")";
        $content .= "\n\n![Reference][photo]\n\n[photo]: https://md.mateo.ovh/media/{$filename}";
        $content .= "\n\n![Brackets](<https://app.mdnotes.net/media/{$filename}>)";
        app(NoteSpace::class)->write($user, 'Images.md', $content);
        $share = SharedNote::query()->create(['user_id' => $user->id, 'path' => 'Images.md', 'token' => 'A2BCD']);

        $page = $this->get('https://app.mdnotes.net/share/'.$share->token)->assertOk();
        $imageUrl = 'https://app.mdnotes.net/share/A2BCD/media/'.$filename;
        $this->assertSame(8, substr_count($page->getContent(), 'src="'.$imageUrl.'"'));
        $this->get($imageUrl)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('https://mdnotes.net/share/'.$share->token)
            ->assertStatus(308)->assertRedirect('https://app.mdnotes.net/share/'.$share->token);
        $this->get('https://app.mdnotes.net/media/'.$filename)->assertRedirect('https://app.mdnotes.net/login');
        $this->actingAs($user)->get('https://app.mdnotes.net/Images.md')->assertOk()
            ->assertSee('src="https://app.mdnotes.net/media/'.$filename.'"', false);
    }

    public function test_expired_and_unrelated_shared_attachments_stay_private_after_migration(): void
    {
        $user = User::factory()->create();
        app(NoteSpace::class)->write($user, 'Note.md', '# No attachments');
        $share = SharedNote::query()->create(['user_id' => $user->id, 'path' => 'Note.md', 'token' => 'A2BCD']);
        $this->get('https://app.mdnotes.net/share/A2BCD/media/abcdefghijklmnopqrstuvwx.png')->assertNotFound();
        $share->update(['expires_at' => now()->subMinute()]);
        $this->get('https://app.mdnotes.net/share/A2BCD')->assertNotFound();
    }
}
