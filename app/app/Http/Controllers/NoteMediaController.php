<?php

namespace App\Http\Controllers;

use App\Services\NoteMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NoteMediaController extends Controller
{
    public function __construct(private readonly NoteMedia $media)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['nullable', 'file', 'max:10240', 'required_without:image'],
            'image' => ['nullable', 'file', 'max:10240', 'required_without:file'],
        ], [
            'file.max' => __('ui.attachment_too_large'),
            'image.max' => __('ui.attachment_too_large'),
        ]);

        $file = $data['file'] ?? $data['image'];

        try {
            $filename = $this->media->store($request->user(), $file);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $url = route('media.show', ['filename' => $filename]);
        $isImage = $this->media->isImagePath($this->media->path($request->user(), $filename));
        $label = $this->attachmentLabel($file->getClientOriginalName());

        return response()->json([
            'url' => $url,
            'isImage' => $isImage,
            'markdown' => $isImage ? '![]('.$url.')' : '['.$label.']('.$url.')',
        ]);
    }

    public function show(Request $request, string $filename): BinaryFileResponse
    {
        $path = $this->media->path($request->user(), $filename);
        $headers = [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $this->media->isImagePath($path)
            ? response()->file($path, $headers)
            : response()->download($path, basename($filename), $headers);
    }

    private function attachmentLabel(string $name): string
    {
        $label = trim((string) preg_replace('/[\[\]\(\)\r\n]+/', ' ', $name));

        return Str::limit($label !== '' ? $label : __('ui.attachment'), 120, '');
    }
}
