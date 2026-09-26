<?php

namespace App\Http\Controllers;

use App\Models\SharedNote;
use App\Services\MarkdownMediaUrls;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use App\Services\ShareTokens;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ShareController extends Controller
{
    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly NoteMedia $media,
        private readonly MarkdownMediaUrls $mediaUrls,
        private readonly NoteVersionHistory $history,
        private readonly ShareTokens $tokens,
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
                    'token' => $this->tokens->generate(),
                    'expires_at' => $expiresAt,
                ]);

                break;
            } catch (UniqueConstraintViolationException) {
                // Retry token collisions on both MySQL and SQLite.
            }
        }

        if (! $share) {
            throw new RuntimeException(__('ui.share_generation_failed'));
        }

        return back()->with([
            'share_url' => $this->tokens->publicUrl($share->token),
            'share_expiration' => $expiresAt
                ? __('ui.share_expires_at', ['date' => $expiresAt->isoFormat('L'), 'time' => $expiresAt->isoFormat('LT')])
                : __('ui.share_does_not_expire'),
        ]);
    }

    public function index(Request $request): View
    {
        $shares = SharedNote::query()
            ->where('user_id', $request->user()->getKey())
            ->latest()
            ->get();

        return view('shares.index', [
            'shares' => $shares,
            'activeShares' => $shares->reject(fn (SharedNote $share): bool => $share->expires_at?->isPast() ?? false),
            'expiredShares' => $shares->filter(fn (SharedNote $share): bool => $share->expires_at?->isPast() ?? false),
            'shareUrls' => $shares->mapWithKeys(fn (SharedNote $share): array => [
                $share->getKey() => $this->tokens->publicUrl($share->token),
            ]),
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
        $content = $this->contentFor($share);
        $title = Str::beforeLast(basename($share->path), '.');
        $rendered = Str::markdown($share->user_id === null ? $content : $this->mediaUrls->forShare($share, $content), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);

        return view('shares.show', [
            'share' => $share,
            'title' => $title,
            'canonicalUrl' => $this->tokens->publicUrl($share->token),
            'rendered' => $rendered,
            'showFileTitle' => preg_match('/<h1(?:\s[^>]*)?>/i', $rendered) !== 1,
            'expirationLabel' => $share->user_id === null ? $this->anonymousExpirationLabel($share) : null,
        ]);
    }

    public function redirectShareToWorkspace(Request $request, string $token): RedirectResponse
    {
        return $this->permanentRedirectWithQuery($request, route('shares.show', ['token' => $token]));
    }

    public function redirectMediaToWorkspace(Request $request, string $token, string $filename): RedirectResponse
    {
        return $this->permanentRedirectWithQuery($request, route('shares.media', [
            'token' => $token,
            'filename' => $filename,
        ]));
    }

    public function media(string $token, string $filename): BinaryFileResponse
    {
        $share = $this->activeShare($token);
        abort_if($share->user_id === null, 404);
        $content = $this->contentFor($share);

        abort_unless($this->mediaUrls->isReferenced($filename, $content), 404);

        $path = $this->media->path($share->user, $filename);
        $downloadName = $this->media->downloadName($share->user, $filename);
        $headers = [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $this->media->isImagePath($path)
            ? response()->file($path, [...$headers, 'Content-Disposition' => HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $downloadName)])
            : response()->download($path, $downloadName, $headers);
    }

    public function copyToSpace(Request $request, string $token): RedirectResponse
    {
        $share = $this->activeShare($token);
        $content = $this->contentFor($share);
        $recipient = $request->user();
        $attachments = [];

        try {
            $destination = $this->spaces->synchronized($recipient, function () use ($recipient, $share, $content, &$attachments): string {
                $destination = $this->spaces->copyDestination($recipient, $share->path);
                $this->spaces->withNoteRollback($recipient, $destination, function () use ($recipient, $share, $content, $destination, &$attachments): void {
                    if ($share->user_id !== null) {
                        $attachments = $this->media->copyReferenced($share->user, $recipient, $content);
                        $content = $this->mediaUrls->forAuthenticatedUser($content, $attachments);
                    }
                    $this->spaces->write($recipient, $destination, $content, snapshot: true);
                    $this->history->record($recipient, $destination, $content);
                });

                return $destination;
            });
        } catch (\Throwable $exception) {
            if (! $share->user?->is($recipient)) {
                $this->media->removeCopied($recipient, array_values($attachments));
            }

            report($exception);

            return back()->withErrors(['copy' => $exception instanceof RuntimeException && ! $exception instanceof QueryException
                ? $exception->getMessage() : __('ui.could_not_copy_shared_note')]);
        }

        return redirect()->route('notes.show', ['path' => $destination])
            ->with('status', __('ui.shared_note_copied'));
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

    private function contentFor(SharedNote $share): string
    {
        return $share->user_id === null
            ? (string) $share->content
            : $this->spaces->read($share->user, $share->path);
    }

    private function anonymousExpirationLabel(SharedNote $share): ?string
    {
        if (! $share->expires_at) {
            return null;
        }

        $seconds = max(0, $share->expires_at->getTimestamp() - now()->getTimestamp());
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);

        if ($days > 0 && $hours > 0) {
            return __('ui.shared_note_expires_days_hours', [
                'days' => $days,
                'day_unit' => trans_choice('ui.shared_day_unit', $days),
                'hours' => $hours,
                'hour_unit' => trans_choice('ui.shared_hour_unit', $hours),
            ]);
        }

        if ($days > 0) {
            return __('ui.shared_note_expires_days', [
                'days' => $days,
                'day_unit' => trans_choice('ui.shared_day_unit', $days),
            ]);
        }

        if ($hours > 0) {
            return __('ui.shared_note_expires_hours', [
                'hours' => $hours,
                'hour_unit' => trans_choice('ui.shared_hour_unit', $hours),
            ]);
        }

        return __('ui.shared_note_expires_less_than_hour');
    }

    private function permanentRedirectWithQuery(Request $request, string $url): RedirectResponse
    {
        $query = $request->getQueryString();

        return redirect()->away($url.($query === null ? '' : '?'.$query), 308);
    }
}
