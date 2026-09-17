<?php

namespace App\Http\Controllers;

use App\Services\NoteMedia;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class ApiNoteController extends Controller
{
    private const MAX_CONTENT_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly NoteVersionHistory $history,
        private readonly NoteMedia $media,
    ) {
    }

    public function upload(Request $request, string $path): JsonResponse
    {
        if (! Str::endsWith(Str::lower($path), '.md')) {
            return response()->json([
                'message' => 'Only .md files can be uploaded through this API.',
            ], 422);
        }

        $content = $request->getContent();

        if (strlen($content) > self::MAX_CONTENT_BYTES) {
            return response()->json([
                'message' => 'The Markdown file may not exceed 5 MiB.',
            ], 422);
        }

        try {
            $created = $this->spaces->writeFromApi($request->user(), $path, $content);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->history->record($request->user(), $path, $content);
        $this->media->pruneUnreferenced($request->user());

        return response()->json([
            'path' => $path,
            'created' => $created,
            'url' => route('notes.show', ['path' => $path]),
        ], $created ? 201 : 200);
    }
}
