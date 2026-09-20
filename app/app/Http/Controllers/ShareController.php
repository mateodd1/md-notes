<?php

namespace App\Http\Controllers;

use App\Models\SharedNote;
use App\Services\MarkdownMediaUrls;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShareController extends Controller
{
    private const TOKEN_ALPHABET = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const TOKEN_LENGTH = 5;

    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly NoteMedia $media,
        private readonly MarkdownMediaUrls $mediaUrls,
        private readonly NoteVersionHistory $history,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'duration' => ['required', 'in:1h,24h,7d,forever'],
        ]);

        $this->spaces->read($request->user(), $data['path']);
        $expiresAt = $this->expiryFor($data['duration']);

        $share = null;
        for ($attempt = 0; $attempt < 10; $attempt++) {
            try {
                $share = SharedNote::query()->create([
                    'user_id' => $request->user()->getKey(),
                    'path' => $data['path'],
                    'token' => $this->generateToken(),
                    'expires_at' => $expiresAt,
                ]);

                break;
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'shared_notes.token')) {
                    throw $exception;
                }
            }
        }

        if (! $share) {
            throw new RuntimeException(__('ui.share_generation_failed'));
        }

        return back()->with([
            'share_url' => route('shares.show', ['token' => $share->token]),
            'share_expiration' => $expiresAt
                ? __('ui.share_expires_at', ['date' => $expiresAt->isoFormat('L'), 'time' => $expiresAt->isoFormat('LT')])
                : __('ui.share_does_not_expire'),
        ]);
    }

    public function index(Request $request): View
    {
        return view('shares.index', [
            'shares' => SharedNote::query()
                ->where('user_id', $request->user()->getKey())
                ->latest()
                ->get(),
        ]);
    }

    public function update(Request $request, SharedNote $share): RedirectResponse
    {
        $data = $request->validate([
            'duration' => ['required', 'in:1h,24h,7d,forever'],
        ]);

        $share = $this->ownedShare($request, $share);
        $share->update(['expires_at' => $this->expiryFor($data['duration'])]);

        return back()->with('status', __('ui.share_duration_updated'));
    }

    public function destroy(Request $request, SharedNote $share): RedirectResponse
    {
        $share = $this->ownedShare($request, $share);
        $share->delete();

        return back()->with('status', __('ui.share_revoked'));
    }

    public function show(string $token): View
    {
        $share = $this->activeShare($token);
        $content = $this->spaces->read($share->user, $share->path);

        return view('shares.show', [
            'share' => $share,
            'title' => Str::beforeLast(basename($share->path), '.'),
            'path' => $share->path,
            'rendered' => Str::markdown($this->mediaUrls->forShare($share, $content), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'renderer' => ['soft_break' => "<br>\n"],
            ]),
        ]);
    }

    public function media(string $token, string $filename): BinaryFileResponse
    {
        $share = $this->activeShare($token);
        $content = $this->spaces->read($share->user, $share->path);

        abort_unless($this->mediaUrls->isReferenced($filename, $content), 404);

        $path = $this->media->path($share->user, $filename);
        $headers = [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $this->media->isImagePath($path)
            ? response()->file($path, $headers)
            : response()->download($path, basename($filename), $headers);
    }

    public function copyToSpace(Request $request, string $token): RedirectResponse
    {
        $share = $this->activeShare($token);
        $content = $this->spaces->read($share->user, $share->path);
        $recipient = $request->user();
        $attachments = [];

        try {
            $destination = $this->spaces->synchronized($recipient, function () use ($recipient, $share, $content, &$attachments): string {
                $destination = $this->spaces->copyDestination($recipient, $share->path);
                $this->spaces->withNoteRollback($recipient, $destination, function () use ($recipient, $share, $content, $destination, &$attachments): void {
                    $attachments = $this->media->copyReferenced($share->user, $recipient, $content);
                    $content = $this->mediaUrls->forAuthenticatedUser($content, $attachments);
                    $this->spaces->write($recipient, $destination, $content, snapshot: true);
                    $this->history->record($recipient, $destination, $content);
                });

                return $destination;
            });
        } catch (\Throwable $exception) {
            if (! $share->user->is($recipient)) {
                $this->media->removeCopied($recipient, array_values($attachments));
            }

            report($exception);

            return back()->withErrors(['copy' => $exception instanceof RuntimeException && ! $exception instanceof QueryException
                ? $exception->getMessage() : __('ui.could_not_copy_shared_note')]);
        }

        return redirect()->route('notes.show', ['path' => $destination])
            ->with('status', __('ui.shared_note_copied'));
    }

    private function generateToken(): string
    {
        $token = '';
        $lastIndex = strlen(self::TOKEN_ALPHABET) - 1;

        for ($index = 0; $index < self::TOKEN_LENGTH; $index++) {
            $token .= self::TOKEN_ALPHABET[random_int(0, $lastIndex)];
        }

        return $token;
    }

    private function expiryFor(string $duration): mixed
    {
        return match ($duration) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addWeek(),
            default => null,
        };
    }

    private function ownedShare(Request $request, SharedNote $share): SharedNote
    {
        abort_unless($share->user_id === $request->user()->getKey(), 404);

        return $share;
    }

    private function activeShare(string $token): SharedNote
    {
        $share = SharedNote::query()->with('user')->where('token', $token)->firstOrFail();

        if ($share->expires_at?->isPast()) {
            $share->delete();
            abort(404);
        }

        return $share;
    }
}
