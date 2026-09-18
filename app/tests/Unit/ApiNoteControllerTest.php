<?php

namespace Tests\Unit;

use App\Http\Controllers\ApiNoteController;
use App\Models\User;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use Illuminate\Http\Request;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ApiNoteControllerTest extends TestCase
{
    public function test_api_accepts_raw_markdown_bodies(): void
    {
        $content = "# Apuntes\n\nTexto.";
        $response = $this->successfulController($content)->upload($this->request($content), 'Clase/apuntes.md');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['created']);
    }

    public function test_api_accepts_markdown_inside_a_json_content_field(): void
    {
        $content = "# Apuntes\n\nTexto.";
        $request = Request::create(
            '/api/notes/Clase/apuntes.md',
            'PUT',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['content' => $content], JSON_THROW_ON_ERROR),
        );
        $request->setUserResolver(fn (): User => $this->user());

        $response = $this->successfulController($content)->upload($request, 'Clase/apuntes.md');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['created']);
    }

    public function test_api_returns_a_clear_validation_error_when_json_has_no_content_field(): void
    {
        $request = Request::create('/api/notes/Clase/apuntes.md', 'PUT', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');

        $response = $this->controller()->upload($request, 'Clase/apuntes.md');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNotEmpty($response->getData(true)['message']);
        $this->assertNotEmpty($response->getData(true)['errors']['content'][0]);
    }

    public function test_api_never_returns_an_empty_error_for_a_rejected_upload(): void
    {
        $user = $this->user();
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('writeFromApi')->once()->andThrow(new RuntimeException(''));
        $request = $this->request('# Apuntes');
        $request->setUserResolver(fn (): User => $user);

        $response = (new ApiNoteController(
            $spaces,
            Mockery::mock(NoteVersionHistory::class),
            Mockery::mock(NoteMedia::class),
        ))->upload($request, 'Clase/apuntes.md');

        $payload = $response->getData(true);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertNotEmpty($payload['message']);
        $this->assertNotEmpty($payload['errors']['content'][0]);
    }

    public function test_api_rejects_paths_that_are_not_markdown_files(): void
    {
        $response = $this->controller()->upload($this->request('# Not a file'), 'Clase/apuntes.txt');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Only .md files can be uploaded through this API.', $response->getData(true)['message']);
    }

    public function test_api_rejects_markdown_larger_than_five_mebibytes(): void
    {
        $response = $this->controller()->upload($this->request(str_repeat('a', (5 * 1024 * 1024) + 1)), 'Clase/apuntes.md');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('The Markdown file may not exceed 5 MiB.', $response->getData(true)['message']);
    }

    private function controller(): ApiNoteController
    {
        return new ApiNoteController(
            Mockery::mock(NoteSpace::class),
            Mockery::mock(NoteVersionHistory::class),
            Mockery::mock(NoteMedia::class),
        );
    }

    private function successfulController(string $expectedContent): ApiNoteController
    {
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('writeFromApi')->once()->withArgs(fn (User $user, string $path, string $content, bool $snapshot): bool => $user->getKey() === 1 && $path === 'Clase/apuntes.md' && $content === $expectedContent && $snapshot)->andReturn(true);
        $history = Mockery::mock(NoteVersionHistory::class);
        $history->shouldReceive('record')->once()->withArgs(fn (User $user, string $path, string $content): bool => $user->getKey() === 1 && $path === 'Clase/apuntes.md' && $content === $expectedContent);
        $media = Mockery::mock(NoteMedia::class);
        $media->shouldReceive('pruneUnreferenced')->once()->withArgs(fn (User $user): bool => $user->getKey() === 1);

        return new ApiNoteController($spaces, $history, $media);
    }

    private function request(string $content): Request
    {
        $request = Request::create('/api/notes/Clase/apuntes.md', 'PUT', [], [], [], [], $content);
        $request->setUserResolver(fn (): User => $this->user());

        return $request;
    }

    private function user(): User
    {
        $user = new User;
        $user->forceFill(['id' => 1]);

        return $user;
    }
}
