<?php

namespace Tests\Unit;

use App\Http\Controllers\ApiNoteController;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class ApiNoteControllerTest extends TestCase
{
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

    private function request(string $content): Request
    {
        return Request::create('/api/notes/Clase/apuntes.md', 'PUT', [], [], [], [], $content);
    }
}
