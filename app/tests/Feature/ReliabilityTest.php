<?php

namespace Tests\Feature;

use App\Models\NoteVersion;
use App\Models\SharedNote;
use App\Models\User;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class ReliabilityTest extends TestCase
{
    private string $directory;

    private NoteSpace $spaces;

    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        $this->directory = sys_get_temp_dir().'/md-notes-reliability-'.bin2hex(random_bytes(8));
        $this->spaces = new NoteSpace($this->directory);
        $this->app->instance(NoteSpace::class, $this->spaces);
        $this->withSession(['_token' => 'regression']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_pending_upload_survives_cleanup_then_is_deleted_after_its_saved_reference_is_removed(): void
    {
        $user = User::factory()->create();
        $media = app(NoteMedia::class);
        $filename = $media->store($user, UploadedFile::fake()->create('attachment.pdf', 1, 'application/pdf'));
        $path = $media->path($user, $filename);

        $this->assertSame(0, $media->pruneUnreferenced($user));
        $this->assertFileExists($path);
        $this->spaces->write($user, 'Note.md', "[File](/app/media/{$filename})");
        $this->assertSame(0, $media->pruneUnreferenced($user));
        $this->spaces->write($user, 'Note.md', 'No attachments');
        $this->assertSame(1, $media->pruneUnreferenced($user));
        $this->assertFileDoesNotExist($path);
    }

    public function test_abandoned_pending_upload_is_eventually_cleaned_up(): void
    {
        $user = User::factory()->create();
        $media = app(NoteMedia::class);
        $filename = $media->store($user, UploadedFile::fake()->create('attachment.pdf', 1));
        $path = $media->path($user, $filename);
        $this->travel(25)->hours();
        $this->assertSame(1, $media->pruneUnreferenced($user));
        $this->assertFileDoesNotExist($path);
    }

    public function test_failed_quota_restore_preserves_current_content_and_history(): void
    {
        $user = User::factory()->create();
        $this->spaces->write($user, 'Note.md', '12345678');
        $version = NoteVersion::query()->create(['user_id' => $user->id, 'path' => 'Note.md', 'content' => '1234567890']);
        $user->forceFill(['storage_quota_bytes' => 28])->save();

        $this->actingAs($user)->from(route('notes.show', ['path' => 'Note.md']))
            ->patch(route('versions.restore', $version), ['_token' => 'regression'])
            ->assertRedirect()->assertSessionHasErrors('version');
        $this->assertSame('12345678', $this->spaces->read($user, 'Note.md'));
        $this->assertDatabaseCount('note_versions', 1);
        $this->assertDatabaseHas('note_versions', ['id' => $version->id, 'content' => '1234567890']);
    }

    public function test_a_successful_restore_keeps_the_previous_content_in_history(): void
    {
        $user = User::factory()->create();
        $this->spaces->write($user, 'Note.md', 'Current');
        $version = NoteVersion::query()->create(['user_id' => $user->id, 'path' => 'Note.md', 'content' => 'Original']);
        $this->actingAs($user)->patch(route('versions.restore', $version), ['_token' => 'regression'])
            ->assertRedirect(route('notes.show', ['path' => 'Note.md']))->assertSessionHasNoErrors();
        $this->assertSame('Original', $this->spaces->read($user, 'Note.md'));
        $this->assertDatabaseHas('note_versions', ['user_id' => $user->id, 'content' => 'Current']);
    }

    public function test_history_failure_rolls_back_the_file_on_restore(): void
    {
        $user = User::factory()->create();
        $this->spaces->write($user, 'Note.md', 'Current');
        $version = NoteVersion::query()->create(['user_id' => $user->id, 'path' => 'Note.md', 'content' => 'Original']);
        $history = Mockery::mock(NoteVersionHistory::class);
        $history->shouldReceive('record')->withArgs(fn ($u, $p, $c) => $c === 'Current')->once();
        $history->shouldReceive('record')->withArgs(fn ($u, $p, $c) => $c === 'Original')->once()->andThrow(new \RuntimeException('Storage unavailable'));
        $this->app->instance(NoteVersionHistory::class, $history);
        $this->actingAs($user)->patch(route('versions.restore', $version), ['_token' => 'regression'])->assertSessionHasErrors('version');
        $this->assertSame('Current', $this->spaces->read($user, 'Note.md'));
    }

    public function test_failed_shared_copy_removes_the_partial_note_and_attachments_and_displays_error(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $media = app(NoteMedia::class);
        $filename = $media->store($owner, UploadedFile::fake()->create('file.pdf', 1));
        $this->spaces->write($owner, 'Note.md', "[File](/app/media/{$filename})");
        $share = SharedNote::query()->create(['user_id' => $owner->id, 'path' => 'Note.md', 'token' => 'A2BCD']);
        $history = Mockery::mock(NoteVersionHistory::class);
        $history->shouldReceive('record')->once()->andThrow(new \RuntimeException('Unable to save the copy'));
        $this->app->instance(NoteVersionHistory::class, $history);

        $this->actingAs($recipient)->from(route('shares.show', $share->token))
            ->post(route('shares.copy', $share->token), ['_token' => 'regression'])
            ->assertRedirect(route('shares.show', $share->token))->assertSessionHasErrors(['copy' => 'Unable to save the copy']);
        $this->assertFileDoesNotExist($this->spaces->root($recipient).'/Note (copy).md');
        $this->assertSame([], File::allFiles($this->spaces->root($recipient).'/.md-notes-media', hidden: true));
        $this->assertDatabaseCount('note_versions', 0);
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('shares.show', $share->token))->assertSee('Unable to save the copy');
        $this->assertFileExists($media->path($owner, $filename));
    }

    public function test_search_matches_titles_and_contents_but_never_other_accounts_trash_or_symlinks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->spaces->writeFromApi($user, 'Class/NETWORKS.md', 'Overview', snapshot: false);
        $this->spaces->write($user, 'Lesson.md', 'Networking and subnets');
        $this->spaces->write($user, 'Deleted.md', 'Network secret');
        $this->spaces->trash($user, 'Deleted.md');
        $this->spaces->write($other, 'Private.md', 'Network private');
        symlink($this->spaces->root($other).'/Private.md', $this->spaces->root($user).'/Linked.md');
        symlink($this->spaces->root($other), $this->spaces->root($user).'/LinkedFolder');
        NoteVersion::query()->create(['user_id' => $user->id, 'path' => 'History.md', 'content' => 'Network history']);

        $response = $this->actingAs($user)->getJson(route('notes.search', ['q' => 'network']));
        $response->assertOk()->assertJsonCount(2, 'results')->assertJsonPath('truncated', false);
        $this->assertEqualsCanonicalizing(['Class/NETWORKS.md', 'Lesson.md'], array_column($response->json('results'), 'path'));
        $response->assertDontSee('secret')->assertDontSee('private')->assertDontSee('history');
        $this->getJson(route('notes.search', ['q' => ' ']))->assertUnprocessable();
        $this->getJson(route('notes.search', ['q' => str_repeat('x', 101)]))->assertUnprocessable();
    }

    public function test_search_requires_login_and_caps_results(): void
    {
        $this->getJson(route('notes.search', ['q' => 'Note']))->assertUnauthorized();
        $user = User::factory()->create();
        foreach (range(1, 51) as $number) {
            $this->spaces->write($user, "Note {$number}.md", 'Content');
        }
        $this->actingAs($user)->getJson(route('notes.search', ['q' => 'Note']))
            ->assertOk()->assertJsonCount(50, 'results')->assertJsonPath('truncated', true);
    }

    public function test_rate_limits_are_independent_for_shared_pages_images_and_registration(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/share/A2BCD')->assertNotFound();
            $this->get('/share/A2BCD/media/abcdefghijklmnopqrstuvwx.png')->assertNotFound();
        }
        $this->postJson(route('register.store'), ['_token' => 'regression'])->assertUnprocessable();
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('login.store'), ['_token' => 'regression'])->assertUnprocessable();
        }
        $this->withHeader('Accept-Language', 'es')->postJson(route('login.store'), ['_token' => 'regression'])
            ->assertTooManyRequests()->assertHeader('Retry-After')->assertSee('demasiadas solicitudes');
        $this->postJson(route('password.email'), ['_token' => 'regression'])->assertUnprocessable();
    }

    public function test_quota_polling_does_not_block_password_code_requests(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->getJson(route('quota.show'))->assertOk();
        }
        // Invalid input exercises the route limiter without sending an email.
        $this->patchJson(route('profile.password'), ['_token' => 'regression'])->assertUnprocessable();
    }
}
