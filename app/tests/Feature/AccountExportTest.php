<?php

namespace Tests\Feature;

use App\Mail\AccountExportReadyMail;
use App\Models\AccountExport;
use App\Models\User;
use App\Services\AccountExports;
use App\Services\NoteSpace;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use ZipArchive;

class AccountExportTest extends TestCase
{
    private string $notesPath;

    private string $exportsPath;

    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        User::created(fn (User $user) => $user->markEmailAsVerified());
        $suffix = bin2hex(random_bytes(8));
        $this->notesPath = sys_get_temp_dir().'/md-notes-export-notes-'.$suffix;
        $this->exportsPath = sys_get_temp_dir().'/md-notes-exports-'.$suffix;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->notesPath);
        File::deleteDirectory($this->exportsPath);

        parent::tearDown();
    }

    public function test_an_account_export_is_emailed_once_per_day_and_contains_private_data(): void
    {
        Mail::fake();
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
        ]);
        $spaces = new NoteSpace($this->notesPath);
        $note = $spaces->createNote($user, '', 'Apuntes');
        $spaces->write($user, $note, '# Mis apuntes');
        File::ensureDirectoryExists($spaces->root($user).'/.md-notes-media');
        File::put($spaces->root($user).'/.md-notes-media/abcdefghijklmnopqrstuvwx.png', 'image');
        $this->app->instance(NoteSpace::class, $spaces);
        $this->app->instance(AccountExports::class, new AccountExports($spaces, $this->exportsPath));

        $this->withHeader('referer', route('profile.edit'))->withSession(['_token' => 'test-token'])->actingAs($user)
            ->post(route('profile.account-exports.store'), ['_token' => 'test-token'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', __('ui.account_export_email_sent'));

        $downloadUrl = null;
        Mail::assertSent(AccountExportReadyMail::class, function (AccountExportReadyMail $mail) use (&$downloadUrl, $user): bool {
            $downloadUrl = $mail->downloadUrl;

            return $mail->user->is($user);
        });
        $export = AccountExport::query()->sole();
        $this->assertFileExists($export->archive_path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export->archive_path) === true);
        $this->assertNotFalse($zip->locateName('account.json'));
        $this->assertNotFalse($zip->locateName('notes/Apuntes.md'));
        $this->assertNotFalse($zip->locateName('notes/.md-notes-media/abcdefghijklmnopqrstuvwx.png'));
        $zip->close();

        $this->get($downloadUrl)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->withHeader('referer', route('profile.edit'))->withSession(['_token' => 'test-token'])->actingAs($user)
            ->post(route('profile.account-exports.store'), ['_token' => 'test-token'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', __('ui.account_export_daily_limit'));
    }
}
