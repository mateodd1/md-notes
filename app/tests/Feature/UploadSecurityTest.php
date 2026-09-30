<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        $this->withSession(['_token' => 'upload-test'])->withHeader('X-CSRF-TOKEN', 'upload-test');
    }

    public static function forbiddenFiles(): array
    {
        return [
            'html' => ['page.html', '<html><script>alert(1)</script></html>'],
            'svg' => ['image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'javascript' => ['file.js', 'alert(1);'],
            'php' => ['file.php', '<?php echo 1;'],
            'executable' => ['file.exe', 'MZ'.str_repeat("\0", 100)],
            'misleading png' => ['image.png', '<html><script>alert(1)</script></html>'],
            'misleading pdf' => ['file.pdf', '<?php echo 1;'],
            'html text' => ['file.txt', '<html><script>alert(1)</script></html>'],
            'missing extension' => ['file', '# Notes'],
        ];
    }

    #[DataProvider('forbiddenFiles')]
    public function test_rejects_unsafe_or_mismatched_attachments_without_storing_them(string $name, string $content): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('media.store'), [
            'file' => UploadedFile::fake()->createWithContent($name, $content),
        ])->assertUnprocessable()->assertJsonPath('message', __('ui.attachment_type_not_allowed'));
        $this->assertDirectoryDoesNotExist(app(NoteSpace::class)->root($user).'/.md-notes-media');
    }

    public static function rasterTypes(): array
    {
        return [['jpg'], ['png'], ['gif'], ['webp']];
    }

    #[DataProvider('rasterTypes')]
    public function test_images_are_reencoded_without_appended_payloads_and_keep_their_download_names(string $extension): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('Photo.'.$extension, 30, 20);
        file_put_contents($file->getPathname(), '<?php malicious_payload(); ?>', FILE_APPEND);

        $response = $this->actingAs($user)->postJson(route('media.store'), ['file' => $file])
            ->assertOk()->assertJsonPath('isImage', true);
        $filename = basename($response->json('url'));
        $path = app(NoteMedia::class)->path($user, $filename);
        $this->assertSame([30, 20], array_slice(getimagesize($path), 0, 2));
        $this->assertStringNotContainsString('malicious_payload', file_get_contents($path));
        $this->assertSame('Photo.'.$extension, app(NoteMedia::class)->downloadName($user, $filename));
    }

    public function test_quota_is_checked_against_the_reencoded_size_and_cleans_up_on_failure(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['storage_quota_bytes' => 1])->save();

        $this->actingAs($user)->postJson(route('media.store'), ['file' => UploadedFile::fake()->image('photo.png')])
            ->assertUnprocessable();
        $this->assertDirectoryDoesNotExist(app(NoteSpace::class)->root($user).'/.md-notes-media');
    }

    public function test_pdf_and_zip_and_text_documents_remain_downloadable(): void
    {
        $user = User::factory()->create();
        $zipPath = tempnam(sys_get_temp_dir(), 'md-notes-test-zip-');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('file.txt', 'A document');
        $zip->close();
        $zipContent = file_get_contents($zipPath);
        unlink($zipPath);

        foreach (['file.pdf' => '%PDF-1.4 document', 'file.zip' => $zipContent, 'file.txt' => 'Text notes', 'file.md' => '# Notes', 'file.csv' => "name,value\ntest,1"] as $name => $content) {
            $response = $this->actingAs($user)->postJson(route('media.store'), ['file' => UploadedFile::fake()->createWithContent($name, $content)])
                ->assertOk()->assertJsonPath('isImage', false);
            $download = $this->get($response->json('url'))->assertDownload($name);
            $this->assertSame($content, file_get_contents($download->baseResponse->getFile()->getPathname()));
        }
    }

    public function test_oversized_image_dimensions_are_rejected_before_decoding(): void
    {
        $user = User::factory()->create();
        $image = UploadedFile::fake()->image('large.png');
        $content = file_get_contents($image->getPathname());
        $content = substr_replace($content, pack('NN', 65535, 65535), 16, 8);

        $this->actingAs($user)->postJson(route('media.store'), ['image' => UploadedFile::fake()->createWithContent('large.png', $content)])
            ->assertUnprocessable()->assertJsonPath('message', __('ui.attachment_image_dimensions'));
    }

    public function test_animated_gif_is_rejected_instead_of_silently_losing_frames(): void
    {
        $user = User::factory()->create();
        $image = UploadedFile::fake()->image('animation.gif');
        $content = file_get_contents($image->getPathname());
        $offset = strpos($content, "\x2c");
        $content = substr($content, 0, -1).substr($content, $offset);

        $this->actingAs($user)->postJson(route('media.store'), ['file' => UploadedFile::fake()->createWithContent('animation.gif', $content)])
            ->assertUnprocessable()->assertJsonPath('message', __('ui.attachment_animation_not_supported'));
    }

    public function test_both_upload_fields_reject_more_than_ten_megabytes(): void
    {
        $user = User::factory()->create();
        foreach (['file', 'image'] as $field) {
            $this->actingAs($user)->postJson(route('media.store'), [
                $field => UploadedFile::fake()->createWithContent('large.txt', str_repeat('x', 10 * 1024 * 1024 + 1)),
            ])->assertUnprocessable()->assertJsonValidationErrors([$field]);
        }
        $this->assertDirectoryDoesNotExist(app(NoteSpace::class)->root($user).'/.md-notes-media');
    }

    public function test_truncated_image_is_rejected_without_a_server_error(): void
    {
        $user = User::factory()->create();
        $image = UploadedFile::fake()->image('broken.png');
        $content = substr(file_get_contents($image->getPathname()), 0, 40);

        $this->actingAs($user)->postJson(route('media.store'), ['file' => UploadedFile::fake()->createWithContent('broken.png', $content)])
            ->assertUnprocessable()->assertJsonPath('message', __('ui.attachment_invalid'));
    }
}
