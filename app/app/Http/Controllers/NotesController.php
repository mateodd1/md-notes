<?php

namespace App\Http\Controllers;

use App\Models\SharedNote;
use App\Models\User;
use App\Services\MarkdownMediaUrls;
use App\Services\NoteMedia;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use App\Services\StorageQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotesController extends Controller
{
    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly NoteVersionHistory $history,
        private readonly NoteMedia $media,
        private readonly MarkdownMediaUrls $mediaUrls,
        private readonly StorageQuota $quota,
    ) {}

    public function index(Request $request): View
    {
        return $this->workspace($request);
    }

    public function quota(Request $request): JsonResponse
    {
        return response()->json($this->quota->summary($request->user()));
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return response()->json($this->spaces->search($request->user(), trim($data['q'])))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $path): View
    {
        $content = $this->spaces->read($request->user(), $path);

        return $this->workspace($request, $path, $content);
    }

    public function storeFolder(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'parent' => ['nullable', 'string', 'max:500'],
            'name' => ['required', 'string', 'max:80'],
            'active_path' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->spaces->createFolder($request->user(), $data['parent'] ?? '', $data['name']);
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => ['name' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['name' => $exception->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            $nodes = $this->spaces->tree($request->user());

            return response()->json([
                'tree' => view('notes._tree', [
                    'nodes' => $nodes,
                    'path' => $data['active_path'] ?? '',
                ])->render(),
                'parentOptions' => view('notes._parent-options', [
                    'nodes' => $nodes,
                    'depth' => 0,
                ])->render(),
                'message' => __('ui.folder_created'),
            ], 201);
        }

        return back()->with('status', __('ui.folder_created'));
    }

    public function storeNote(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'parent' => ['nullable', 'string', 'max:500'],
            'name' => ['required', 'string', 'max:80'],
        ]);

        try {
            $path = $this->spaces->createNote($request->user(), $data['parent'] ?? '', $data['name']);
            $this->history->record($request->user(), $path, $this->spaces->read($request->user(), $path));
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => ['name' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['name' => $exception->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'path' => $path,
                'url' => route('notes.show', ['path' => $path]),
                'tree' => view('notes._tree', [
                    'nodes' => $this->spaces->tree($request->user()),
                    'path' => $path,
                ])->render(),
                'message' => __('ui.note_created'),
            ], 201);
        }

        return redirect()->route('notes.show', ['path' => $path])->with('status', __('ui.note_created'));
    }

    public function update(Request $request, string $path): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'content' => ['present', 'string', 'max:5242880'],
            'snapshot' => ['nullable', 'boolean'],
        ]);
        try {
            $this->spaces->synchronized($request->user(), function () use ($request, $path, $data): void {
                $this->spaces->withNoteRollback($request->user(), $path, function () use ($request, $path, $data): void {
                    $this->spaces->write($request->user(), $path, $data['content'], $request->boolean('snapshot'));
                    if ($request->boolean('snapshot')) {
                        $this->history->record($request->user(), $path, $data['content']);
                    }
                });
                $this->media->pruneUnreferenced($request->user());
            });
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => ['content' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['content' => $exception->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json(['savedAt' => now()->format('H:i')]);
        }

        return back()->with('status', __('ui.changes_saved_server'));
    }

    public function download(Request $request, string $path): StreamedResponse
    {
        $content = $this->spaces->read($request->user(), $path);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, basename($path), ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    public function properties(Request $request, string $path): JsonResponse
    {
        $user = $request->user();
        $properties = $this->spaces->noteProperties($user, $path);
        $attachments = $this->media->attachmentSummary($user, $this->spaces->read($user, $path));

        return response()->json([
            ...$properties,
            ...$attachments,
            'total_bytes' => $properties['markdown_bytes'] + $attachments['attachments_bytes'],
        ]);
    }

    public function move(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:500'],
            'destination' => ['nullable', 'string', 'max:500'],
            'active_path' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $path = $this->spaces->move($request->user(), $data['source'], $data['destination'] ?? '');
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['move' => $exception->getMessage()]);
        }

        $this->relocateSharedNotes($request->user(), $data['source'], $path);
        $this->history->relocate($request->user(), $data['source'], $path);

        if ($request->expectsJson()) {
            $activePath = $data['active_path'] ?? '';
            if ($activePath === $data['source']) {
                $activePath = $path;
            } elseif (Str::startsWith($activePath, $data['source'].'/')) {
                $activePath = $path.Str::after($activePath, $data['source']);
            }

            return response()->json([
                'path' => $path,
                'activePath' => $activePath,
                'tree' => view('notes._tree', [
                    'nodes' => $this->spaces->tree($request->user()),
                    'path' => $activePath,
                ])->render(),
                'message' => __('ui.item_moved'),
            ]);
        }

        return redirect()->route('notes.show', ['path' => $path])->with('status', __('ui.item_moved'));
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:500'],
            'target' => ['required', 'string', 'max:500'],
            'position' => ['required', 'in:before,after'],
            'active_path' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $path = $this->spaces->reorderNote($request->user(), $data['source'], $data['target'], $data['position']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->relocateSharedNotes($request->user(), $data['source'], $path);
        $this->history->relocate($request->user(), $data['source'], $path);

        $activePath = $data['active_path'] ?? '';
        if ($activePath === $data['source']) {
            $activePath = $path;
        }

        return response()->json([
            'path' => $path,
            'activePath' => $activePath,
            'tree' => view('notes._tree', [
                'nodes' => $this->spaces->tree($request->user()),
                'path' => $activePath,
            ])->render(),
            'message' => __('ui.note_reordered'),
        ]);
    }

    public function reorderFolder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:500'],
            'target' => ['required', 'string', 'max:500'],
            'position' => ['required', 'in:before,after'],
            'active_path' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $path = $this->spaces->reorderFolder($request->user(), $data['source'], $data['target'], $data['position']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->relocateSharedNotes($request->user(), $data['source'], $path);
        $this->history->relocate($request->user(), $data['source'], $path);

        $activePath = $data['active_path'] ?? '';
        if ($activePath === $data['source']) {
            $activePath = $path;
        } elseif (Str::startsWith($activePath, $data['source'].'/')) {
            $activePath = $path.Str::after($activePath, $data['source']);
        }

        return response()->json([
            'path' => $path,
            'activePath' => $activePath,
            'tree' => view('notes._tree', [
                'nodes' => $this->spaces->tree($request->user()),
                'path' => $activePath,
            ])->render(),
            'message' => __('ui.folder_reordered'),
        ]);
    }

    public function rename(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'collapsed' => ['nullable', 'boolean'],
        ]);

        try {
            $path = $this->spaces->rename($request->user(), $data['path'], $data['name'], $request->has('color') ? ($data['color'] ?? '') : null, $request->has('collapsed') ? $request->boolean('collapsed') : null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['rename' => $exception->getMessage()]);
        }

        $this->relocateSharedNotes($request->user(), $data['path'], $path);
        $this->history->relocate($request->user(), $data['path'], $path);

        if (Str::endsWith(Str::lower($path), '.md')) {
            return redirect()->route('notes.show', ['path' => $path])->with('status', __('ui.item_renamed'));
        }

        return redirect()->route('notes.index')->with('status', __('ui.folder_renamed'));
    }

    public function pin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'pinned' => ['required', 'boolean'],
            'active_path' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->spaces->setPinned($request->user(), $data['path'], (bool) $data['pinned']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'tree' => view('notes._tree', [
                'nodes' => $this->spaces->tree($request->user()),
                'path' => $data['active_path'] ?? '',
            ])->render(),
            'message' => $data['pinned'] ? __('ui.item_pinned') : __('ui.item_unpinned'),
        ]);
    }

    public function destroyItem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        try {
            $entry = $this->spaces->deleteItem($request->user(), $data['path']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['delete' => $exception->getMessage()]);
        }

        $this->removeSharedNotes($request->user(), $data['path']);
        $this->history->relocate($request->user(), $data['path'], $entry['history_path']);

        return redirect()->route('notes.index')->with('status', __('ui.item_moved_to_trash'));
    }

    public function destroy(Request $request, string $path): RedirectResponse
    {
        try {
            $entry = $this->spaces->delete($request->user(), $path);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['note' => $exception->getMessage()]);
        }

        $this->removeSharedNotes($request->user(), $path);
        $this->history->relocate($request->user(), $path, $entry['history_path']);

        return redirect()->route('notes.index')->with('status', __('ui.note_moved_to_trash'));
    }

    public function trashIndex(Request $request): View
    {
        return view('trash.index', [
            'items' => $this->spaces->trashItems($request->user()),
        ]);
    }

    public function showTrash(Request $request, string $id): View|JsonResponse
    {
        $item = $this->spaces->trashedNote($request->user(), $id);
        $title = Str::beforeLast(basename($item['original_path']), '.');
        $rendered = $this->renderMarkdown($item['content']);

        if ($request->expectsJson()) {
            return response()->json([
                'title' => $title,
                'path' => $item['original_path'],
                'rendered' => $rendered,
            ])->header('Cache-Control', 'private, no-store');
        }

        return view('trash.show', [
            'item' => $item,
            'title' => $title,
            'rendered' => $rendered,
        ]);
    }

    public function restoreTrash(Request $request, string $id): RedirectResponse
    {
        try {
            $entry = $this->spaces->restoreTrash($request->user(), $id);
            $this->history->relocate($request->user(), $entry['history_path'], $entry['original_path']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['trash' => $exception->getMessage()]);
        }

        $route = $entry['type'] === 'note' ? 'notes.show' : 'notes.index';
        $parameters = $entry['type'] === 'note' ? ['path' => $entry['original_path']] : [];

        return redirect()->route($route, $parameters)->with('status', __('ui.trash_restored'));
    }

    public function destroyTrash(Request $request, string $id): RedirectResponse
    {
        try {
            $entry = $this->spaces->deleteTrash($request->user(), $id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['trash' => $exception->getMessage()]);
        }

        $this->history->remove($request->user(), $entry['history_path']);
        $this->media->pruneUnreferenced($request->user());

        return redirect()->route('trash.index')->with('status', __('ui.trash_deleted_permanently'));
    }

    private function relocateSharedNotes(User $user, string $sourcePath, string $destinationPath): void
    {
        SharedNote::query()->where('user_id', $user->getKey())->get()->each(function (SharedNote $share) use ($sourcePath, $destinationPath): void {
            if ($share->path === $sourcePath) {
                $share->update(['path' => $destinationPath]);
            } elseif (Str::startsWith($share->path, $sourcePath.'/')) {
                $share->update(['path' => $destinationPath.Str::after($share->path, $sourcePath)]);
            }
        });
    }

    private function removeSharedNotes(User $user, string $path): void
    {
        SharedNote::query()->where('user_id', $user->getKey())->where(function ($query) use ($path): void {
            $query->where('path', $path)->orWhere('path', 'like', $path.'/%');
        })->delete();
    }

    private function workspace(Request $request, ?string $path = null, string $content = ''): View
    {
        $attachments = $path === null
            ? []
            : array_map(static fn (array $attachment): array => [
                ...$attachment,
                'url' => route('media.show', ['filename' => $attachment['filename']]),
                'preview_url' => $attachment['is_pdf']
                    ? route('media.preview', ['filename' => $attachment['filename']])
                    : null,
            ], $this->media->attachmentList($request->user(), $content));

        return view('notes.workspace', [
            'tree' => $this->spaces->tree($request->user()),
            'path' => $path,
            'content' => $content,
            'title' => $path === null ? __('ui.your_notes') : Str::beforeLast(basename($path), '.'),
            'rendered' => $path === null ? '' : $this->renderMarkdown($content),
            'attachments' => $attachments,
            'quota' => $this->quota->summary($request->user()),
        ]);
    }

    private function renderMarkdown(string $content): string
    {
        return Str::markdown($this->mediaUrls->forAuthenticatedUser($content), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
    }
}
