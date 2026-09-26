<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use App\Services\OfflineNotes;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class OfflineNotesTest extends TestCase
{
    private NoteSpace $spaces;

    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        $this->spaces = app(NoteSpace::class);
        $this->withSession(['_token' => 'offline-test']);
    }

    private function payload(User $user, string $content = 'Offline edit'): array
    {
        return ['_token' => 'offline-test', 'account' => OfflineNotes::accountKey($user), 'path' => 'Note.md',
            'content' => $content, 'revision' => hash('sha256', 'Original'), 'change_id' => (string) Str::uuid(), 'snapshot' => true];
    }

    public function test_sync_requires_a_verified_session_and_matching_account(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload($user);
        $this->postJson(route('offline.sync'), $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())->postJson(route('offline.sync'), $payload)->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson(route('offline.sync'), $payload)
            ->assertConflict()->assertJsonPath('code', 'account_changed');
        $this->assertDatabaseCount('note_versions', 0);
    }

    public function test_session_returns_current_csrf_without_caching(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('offline.session'))->assertOk()
            ->assertJsonPath('account', OfflineNotes::accountKey($user))->assertJsonPath('csrf', 'offline-test')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_sync_preserves_exact_markdown_and_records_explicit_snapshots(): void
    {
        $user = User::factory()->create();
        $this->spaces->write($user, 'Note.md', 'Original');
        $content = "  # Café\n\nFinal  \n";
        $payload = $this->payload($user, $content);
        $this->actingAs($user)->postJson(route('offline.sync'), $payload)->assertOk()
            ->assertJsonPath('conflict', false)->assertJsonPath('revision', hash('sha256', $content));
        $this->assertSame($content, $this->spaces->read($user, 'Note.md'));
        $this->assertDatabaseHas('note_versions', ['user_id' => $user->id, 'path' => 'Note.md', 'content' => $content]);
        $this->postJson(route('offline.sync'), $payload)->assertOk()->assertJsonPath('conflict', false);
        $this->assertDatabaseCount('note_versions', 1);
        $payload['revision'] = hash('sha256', $content);
        $payload['content'] = '';
        $payload['snapshot'] = false;
        $payload['change_id'] = (string) Str::uuid();
        $this->postJson(route('offline.sync'), $payload)->assertOk();
        $this->assertSame('', $this->spaces->read($user, 'Note.md'));
        $this->assertDatabaseCount('note_versions', 1);
    }

    public function test_remote_changes_are_preserved_and_conflict_retries_do_not_duplicate_copies(): void
    {
        $user = User::factory()->create();
        $this->spaces->write($user, 'Note.md', 'Another device');
        $payload = $this->payload($user);
        $response = $this->actingAs($user)->postJson(route('offline.sync'), $payload)->assertOk()->assertJsonPath('conflict', true);
        $copy = $response->json('path');
        $this->assertSame('Another device', $this->spaces->read($user, 'Note.md'));
        $this->assertSame('Offline edit', $this->spaces->read($user, $copy));
        $this->postJson(route('offline.sync'), $payload)->assertOk()->assertJsonPath('path', $copy);
        $this->assertDatabaseCount('note_versions', 1);
        $this->spaces->write($user, $copy, 'Edited recovery');
        $this->postJson(route('offline.sync'), $payload)->assertConflict();
        $this->assertSame('Edited recovery', $this->spaces->read($user, $copy));
    }

    public function test_deleted_or_moved_notes_recover_in_a_new_root_note(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload($user);
        $payload['path'] = 'Deleted folder/Note.md';
        $response = $this->actingAs($user)->postJson(route('offline.sync'), $payload)->assertOk()->assertJsonPath('conflict', true);
        $this->assertStringNotContainsString('/', $response->json('path'));
        $this->assertFileDoesNotExist($this->spaces->root($user).'/Deleted folder/Note.md');
        $this->assertSame('Offline edit', $this->spaces->read($user, $response->json('path')));
    }

    public function test_quota_and_history_failures_leave_the_original_untouched(): void
    {
        $user = User::factory()->create(['storage_quota_bytes' => 12]);
        $this->spaces->write($user, 'Note.md', 'Original');
        $this->actingAs($user)->postJson(route('offline.sync'), $this->payload($user))->assertUnprocessable();
        $this->assertSame('Original', $this->spaces->read($user, 'Note.md'));
        $user->forceFill(['storage_quota_bytes' => 104857600])->save();
        $history = Mockery::mock(NoteVersionHistory::class);
        $history->shouldReceive('record')->once()->andThrow(new \RuntimeException('History unavailable'));
        $this->app->instance(NoteVersionHistory::class, $history);
        $this->postJson(route('offline.sync'), $this->payload($user))->assertUnprocessable();
        $this->assertSame('Original', $this->spaces->read($user, 'Note.md'));
        $this->assertDatabaseCount('note_versions', 0);
    }

    public function test_sync_rejects_invalid_paths_and_oversized_utf8_content(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['../Note.md', '/Note.md', '.secret.md', 'Folder//Note.md', 'Note.txt'] as $path) {
            $this->postJson(route('offline.sync'), [...$this->payload($user), 'path' => $path])->assertUnprocessable();
        }
        $this->postJson(route('offline.sync'), $this->payload($user, str_repeat('é', 2621441)))->assertUnprocessable();
        $this->assertSame([], $this->spaces->tree($user));
    }

    public function test_public_shell_contains_no_account_or_csrf_and_workspace_has_revision(): void
    {
        $user = User::factory()->create();
        $this->spaces->write($user, 'Note.md', "# Cached note\n\nOriginal");
        $shell = $this->actingAs($user)->get(route('offline.shell'))->assertOk()
            ->assertDontSee($user->email)->assertDontSee(OfflineNotes::accountKey($user))->assertDontSee('csrf-token');
        $workspace = $this->get(route('notes.show', ['path' => 'Note.md']))->assertOk()
            ->assertSee('data-revision="'.hash('sha256', "# Cached note\n\nOriginal").'"', false);
        $this->get(route('offline.worker'))->assertOk()->assertHeader('Service-Worker-Allowed', '/')
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')->assertDontSee($user->email);
        // Real Blade markup used by the browser suite, with disposable factory data only.
        file_put_contents('/tmp/md-notes-offline-workspace.html', $workspace->getContent());
        file_put_contents('/tmp/md-notes-offline-shell.html', $shell->getContent());
    }

    public function test_logout_clears_browser_storage(): void
    {
        $this->actingAs(User::factory()->create())->post(route('logout'), ['_token' => 'offline-test'])
            ->assertRedirect(route('login'))->assertHeader('Clear-Site-Data', '"cache", "storage"');
    }
}
