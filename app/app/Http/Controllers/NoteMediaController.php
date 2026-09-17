<?php

namespace App\Http\Controllers;

use App\Services\NoteMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NoteMediaController extends Controller
{
    public function __construct(private readonly NoteMedia $media)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:5120'],
        ]);

        $filename = $this->media->store($request->user(), $data['image']);
        $url = route('media.show', ['filename' => $filename]);

        return response()->json([
            'url' => $url,
            'markdown' => '![]('.$url.')',
        ]);
    }

    public function show(Request $request, string $filename): BinaryFileResponse
    {
        $path = $this->media->path($request->user(), $filename);

        return response()->file($path, [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
