<?php

namespace Tests\Feature;

use App\Models\SharedNote;
use App\Models\User;
use App\Services\NoteSpace;
use App\Services\ShareTokens;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AnonymousApiNoteTest extends TestCase
{
    private string $spacePath;

    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        $this->spacePath = sys_get_temp_dir().'/md-notes-anonymous-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->spacePath);
        parent::tearDown();
    }

    public function test_raw_markdown_without_token_returns_a_public_url_that_expires_in_thirty_days(): void
    {
        $response = $this->rawUpload('Clase/apuntes.md', "# Apuntes\n\nPrimera línea\nSegunda línea");

        $response->assertCreated()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $share = SharedNote::query()->sole();
        $url = app(ShareTokens::class)->publicUrl($share->token);
        $this->assertSame($url."\n", $response->getContent());
        $this->assertSame($url, $response->headers->get('Location'));
        $this->assertNull($share->user_id);
        $this->assertSame("# Apuntes\n\nPrimera línea\nSegunda línea", $share->content);
        $this->assertSame(strlen($share->content), $share->content_bytes);
        $this->assertGreaterThan(29.99, now()->diffInDays($share->expires_at));
        $this->assertSame(0, User::query()->count());

        $this->get(route('shares.show', ['token' => $share->token]))
            ->assertOk()
            ->assertSee("Primera línea<br>\nSegunda línea", false)
            ->assertDontSee('Clase/apuntes.md');
    }

    public function test_json_upload_can_request_a_json_response(): void
    {
        $response = $this->withHeader('Accept', 'application/json')
            ->putJson('/api/notes/test.md', ['content' => '# Test']);

        $response->assertCreated()->assertJsonPath('url', app(ShareTokens::class)->publicUrl(SharedNote::query()->sole()->token));
        $this->assertNotEmpty($response->json('expires_at'));
    }

    public function test_invalid_authorization_never_falls_back_to_anonymous_upload(): void
    {
        $this->rawUpload('test.md', '# Test', 'Bearer mdn_invalid')->assertUnauthorized();
        $this->assertDatabaseCount('shared_notes', 0);
    }

    public function test_anonymous_upload_rejects_invalid_file_names_and_non_utf8_content(): void
    {
        $this->rawUpload('test.txt', '# Test')->assertUnprocessable();
        $this->rawUpload('folder/../test.md', '# Test')->assertUnprocessable();
        $this->rawUpload('test.md', "\xff")->assertUnprocessable();
        $this->assertDatabaseCount('shared_notes', 0);
    }

    public function test_expired_anonymous_notes_are_unavailable_and_pruned(): void
    {
        $this->rawUpload('test.md', '# Test')->assertCreated();
        $token = SharedNote::query()->sole()->token;
        $this->travel(31)->days();

        $this->get(route('shares.show', ['token' => $token]))->assertNotFound();
        $this->assertDatabaseCount('shared_notes', 0);

        $this->rawUpload('another.md', '# Another')->assertCreated();
        $this->travel(31)->days();
        $this->artisan('shares:prune-anonymous')->assertSuccessful();
        $this->assertDatabaseCount('shared_notes', 0);
    }

    public function test_anonymous_links_strip_embedded_html_and_cannot_serve_private_media(): void
    {
        $this->rawUpload('test.md', "# Safe\n\n<script>alert(1)</script>")->assertCreated();
        $token = SharedNote::query()->sole()->token;

        $this->get(route('shares.show', ['token' => $token]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get(route('shares.media', [
            'token' => $token,
            'filename' => 'abcdefghijklmnopqrstuvwx.png',
        ]))->assertNotFound();
    }

    public function test_anonymous_upload_is_rate_limited_and_storage_capped(): void
    {
        config(['md-notes.anonymous_notes_max_bytes' => 7]);
        $this->rawUpload('one.md', '1234')->assertCreated();
        $this->rawUpload('two.md', '1234')->assertStatus(507);
        $this->assertDatabaseCount('shared_notes', 1);

        config(['md-notes.anonymous_notes_max_bytes' => 100]);
        $this->rawUpload('two.md', '1234')->assertCreated();
        $this->rawUpload('three.md', '1234')->assertTooManyRequests();
    }

    public function test_a_signed_in_user_can_copy_an_anonymous_note(): void
    {
        $this->rawUpload('apuntes.md', '# Apuntes')->assertCreated();
        $share = SharedNote::query()->sole();
        $user = User::query()->create([
            'name' => 'Mateo', 'email' => 'mateo@example.test', 'password' => 'password123',
        ]);
        $user->markEmailAsVerified();
        $this->app->instance(NoteSpace::class, new NoteSpace($this->spacePath));

        $this->withSession(['_token' => 'test-token'])->actingAs($user)
            ->post(route('shares.copy', ['token' => $share->token]), ['_token' => 'test-token'])
            ->assertRedirect(route('notes.show', ['path' => 'apuntes (copy).md']));
        $this->assertSame('# Apuntes', $this->app->make(NoteSpace::class)->read($user, 'apuntes (copy).md'));
    }

    private function rawUpload(string $path, string $content, ?string $authorization = null): TestResponse
    {
        $headers = ['CONTENT_TYPE' => 'text/markdown'];
        if ($authorization !== null) {
            $headers['HTTP_AUTHORIZATION'] = $authorization;
        }

        return $this->call('PUT', '/api/notes/'.$path, [], [], [], $headers, $content);
    }
}
