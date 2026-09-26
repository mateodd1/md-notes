<?php

namespace App\Http\Controllers;

use App\Models\SharedNote;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use App\Services\ShareTokens;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ApiNoteController extends Controller
{
    private const MAX_CONTENT_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly NoteVersionHistory $history,
        private readonly NoteMedia $media,
    ) {}

    public function upload(Request $request, string $path): Response
    {
        $content = $request->getContent();

        if ($request->isJson()) {
            $payload = $request->json()->all();
            if (! array_key_exists('content', $payload) || ! is_string($payload['content'])) {
                return response()->json([
                    'message' => __('ui.api_content_required'),
                    'errors' => ['content' => [__('ui.api_content_required')]],
                ], 422);
            }

            $content = $payload['content'];
        } elseif ($request->request->has('content')) {
            $content = (string) $request->request->get('content');
        }

        return $this->saveContent($request, $path, $content);
    }

    public function uploadFile(Request $request): Response
    {
        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return response()->json(['message' => 'Provide a valid Markdown file in the "file" field.'], 422);
        }

        $path = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        if (! Str::endsWith(Str::lower($path), '.md')) {
            return response()->json(['message' => 'Only .md files can be uploaded through this API.'], 422);
        }

        if (($file->getSize() ?? 0) > self::MAX_CONTENT_BYTES) {
            return response()->json([
                'message' => 'The Markdown file may not exceed 5 MiB.',
            ], 422);
        }

        $content = file_get_contents($file->getPathname());
        if (! is_string($content)) {
            return response()->json(['message' => 'The Markdown file could not be read.'], 422);
        }

        return $this->saveContent($request, $path, $content);
    }

    private function saveContent(Request $request, string $path, string $content): Response
    {
        if (! Str::endsWith(Str::lower($path), '.md')) {
            return response()->json([
                'message' => 'Only .md files can be uploaded through this API.',
            ], 422);
        }

        if (strlen($content) > self::MAX_CONTENT_BYTES) {
            return response()->json([
                'message' => 'The Markdown file may not exceed 5 MiB.',
            ], 422);
        }

        if (! $request->user()) {
            return $this->uploadAnonymous($request, $path, $content);
        }

        try {
            $created = $this->spaces->synchronized($request->user(), function () use ($request, $path, $content): bool {
                $created = $this->spaces->writeFromApi($request->user(), $path, $content, snapshot: true);
                $this->history->record($request->user(), $path, $content);
                $this->media->pruneUnreferenced($request->user());

                return $created;
            });
        } catch (RuntimeException $exception) {
            $message = trim($exception->getMessage());
            if ($message === '') {
                Log::warning('Markdown API upload was rejected without an error message.', [
                    'user_id' => $request->user()?->getKey(),
                    'path' => $path,
                ]);
                $message = __('ui.api_upload_failed');
            }

            return response()->json([
                'message' => $message,
                'errors' => ['content' => [$message]],
            ], 422);
        }

        return response()->json([
            'path' => $path,
            'created' => $created,
            'url' => route('notes.show', ['path' => $path]),
        ], $created ? 201 : 200);
    }

    private function uploadAnonymous(Request $request, string $path, string $content): Response
    {
        $name = basename($path);
        if (strlen($path) > 500 || $name === '' || Str::lower($name) === '.md'
            || str_contains($path, '\\') || str_contains($path, '//')
            || preg_match('/(?:^|\/)(?:\.|\.\.)(?:\/|$)|[\x00-\x1f\x7f]/', $path)
            || ! mb_check_encoding($path, 'UTF-8') || ! mb_check_encoding($content, 'UTF-8')) {
            return response()->json(['message' => 'The Markdown filename or content is invalid.'], 422);
        }

        try {
            $share = Cache::lock('anonymous-markdown-uploads', 10)->block(5, function () use ($path, $content): ?SharedNote {
                $used = (int) SharedNote::query()->whereNull('user_id')
                    ->where('expires_at', '>', now())->sum('content_bytes');
                if ($used + strlen($content) > (int) config('md-notes.anonymous_notes_max_bytes')) {
                    return null;
                }

                $tokens = app(ShareTokens::class);
                for ($attempt = 0; $attempt < 10; $attempt++) {
                    try {
                        return SharedNote::query()->create([
                            'user_id' => null,
                            'path' => $path,
                            'token' => $tokens->generate(),
                            'content' => $content,
                            'content_bytes' => strlen($content),
                            'expires_at' => now()->addDays(30),
                        ]);
                    } catch (UniqueConstraintViolationException) {
                        // The token is shared with account-owned links; retry rare collisions.
                    }
                }

                throw new RuntimeException('Could not generate a unique share link.');
            });
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'The upload service is busy. Please try again.'], 503);
        }

        if (! $share) {
            return response()->json(['message' => 'Temporary note storage is full. Please try again later.'], 507);
        }

        $url = app(ShareTokens::class)->publicUrl($share->token);
        $headers = ['Location' => $url, 'Cache-Control' => 'no-store'];

        return $request->wantsJson()
            ? response()->json(['url' => $url, 'expires_at' => $share->expires_at->toIso8601String()], 201, $headers)
            : response($url."\n", 201, [...$headers, 'Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
