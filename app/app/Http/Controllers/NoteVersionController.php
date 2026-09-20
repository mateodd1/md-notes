<?php

namespace App\Http\Controllers;

use App\Models\NoteVersion;
use App\Services\MarkdownMediaUrls;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NoteVersionController extends Controller
{
    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly NoteVersionHistory $history,
        private readonly MarkdownMediaUrls $mediaUrls,
    ) {}

    public function index(Request $request, string $path): View|JsonResponse
    {
        $this->spaces->read($request->user(), $path);
        $versions = $this->history->forNote($request->user(), $path);

        if ($request->expectsJson()) {
            return response()->json([
                'path' => $path,
                'versions' => $versions->map(fn (NoteVersion $version): array => [
                    'id' => $version->id,
                    'saved_at' => $version->created_at->format('d/m/Y · H:i:s'),
                    'relative_time' => $version->created_at->diffForHumans(),
                    'characters' => mb_strlen($version->content),
                ])->values(),
            ]);
        }

        return view('versions.index', [
            'path' => $path,
            'versions' => $versions,
        ]);
    }

    public function show(Request $request, NoteVersion $version): View|JsonResponse
    {
        $version = $this->ownedVersion($request, $version);
        $title = Str::beforeLast(basename($version->path), '.');
        $rendered = Str::markdown($this->mediaUrls->forAuthenticatedUser($version->content), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'path' => $version->path,
                'title' => $title,
                'saved_at' => $version->created_at->format('d/m/Y · H:i:s'),
                'rendered' => $rendered,
            ]);
        }

        return view('versions.show', [
            'version' => $version,
            'title' => $title,
            'rendered' => $rendered,
        ]);
    }

    public function restore(Request $request, NoteVersion $version): RedirectResponse
    {
        $version = $this->ownedVersion($request, $version);
        $user = $request->user();
        try {
            $this->spaces->synchronized($user, fn () => $this->spaces->withNoteRollback($user, $version->path, function () use ($user, $version): void {
                $currentContent = $this->spaces->read($user, $version->path);
                $this->history->record($user, $version->path, $currentContent);
                $this->spaces->write($user, $version->path, $version->content, snapshot: true);
                $this->history->record($user, $version->path, $version->content);
            }));
        } catch (\Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            return back()->withErrors(['version' => $exception instanceof RuntimeException && ! $exception instanceof QueryException
                ? $exception->getMessage() : __('ui.could_not_save')]);
        }

        return redirect()->route('notes.show', ['path' => $version->path])->with('status', __('ui.version_restored'));
    }

    public function download(Request $request, NoteVersion $version): StreamedResponse
    {
        $version = $this->ownedVersion($request, $version);
        $filename = Str::beforeLast(basename($version->path), '.').'-'.$version->created_at->format('Y-m-d-His').'.md';

        return response()->streamDownload(static function () use ($version): void {
            echo $version->content;
        }, $filename, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    private function ownedVersion(Request $request, NoteVersion $version): NoteVersion
    {
        abort_unless($version->user_id === $request->user()->getKey(), 404);

        return $version;
    }
}
