<?php

namespace App\Http\Controllers;

use App\Models\SharedNote;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShareController extends Controller
{
    private const TOKEN_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly NoteMedia $media,
    ) {
    }

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
            'title' => Str::beforeLast(basename($share->path), '.'),
            'path' => $share->path,
            'rendered' => Str::markdown($this->withSharedMediaUrls($share, $content), ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ]);
    }

    public function media(string $token, string $filename): BinaryFileResponse
    {
        $share = $this->activeShare($token);
        $content = $this->spaces->read($share->user, $share->path);

        abort_unless($this->mediaIsReferencedByNote($filename, $content), 404);

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

    private function generateToken(): string
    {
        $token = '';
        $lastIndex = strlen(self::TOKEN_ALPHABET) - 1;

        for ($index = 0; $index < 5; $index++) {
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

    private function withSharedMediaUrls(SharedNote $share, string $content): string
    {
        $baseUrl = preg_quote(rtrim(url('/'), '/'), '/');
        $pattern = '/(!?\[[^\]]*\]\()\s*(?:'.$baseUrl.')?\/media\/([a-z0-9]{24}\.[a-z0-9]{1,10})(\))/i';

        return preg_replace_callback($pattern, function (array $matches) use ($share): string {
            return $matches[1].route('shares.media', [
                'token' => $share->token,
                'filename' => Str::lower($matches[2]),
            ]).$matches[3];
        }, $content) ?? $content;
    }

    private function mediaIsReferencedByNote(string $filename, string $content): bool
    {
        $baseUrl = preg_quote(rtrim(url('/'), '/'), '/');
        $filename = preg_quote($filename, '/');

        return preg_match('/!?\[[^\]]*\]\(\s*(?:'.$baseUrl.')?\/media\/'.$filename.'\)/i', $content) === 1;
    }
}
