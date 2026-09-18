<?php

namespace App\Services;

use App\Models\NoteVersion;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class NoteSpace
{
    private const NOTE_ORDER_FILE = '.md-notes-order.json';

    private const FOLDER_ORDER_FILE = '.md-notes-folder-order.json';

    private const FOLDER_COLORS_FILE = '.md-notes-folder-colors.json';

    private const FOLDER_COLLAPSED_FILE = '.md-notes-folder-collapsed.json';

    private const PINNED_FILE = '.md-notes-pinned.json';

    private const FOLDER_COLORS = ['#0284c7', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#64748b'];

    private const TRASH_DIRECTORY = '.md-notes-trash';

    private const TRASH_METADATA_FILE = 'metadata.json';

    public function __construct(private readonly ?string $basePath = null)
    {
    }

    public function root(User $user): string
    {
        $root = rtrim($this->basePath ?? storage_path('app/private/spaces'), '/').'/'.$user->getKey();

        if (! is_dir($root) && ! mkdir($root, 0770, true) && ! is_dir($root)) {
            throw new RuntimeException(__('ui.cannot_create_notes_space'));
        }

        return $root;
    }

    /** @return array<int, array<string, mixed>> */
    public function tree(User $user): array
    {
        $root = $this->root($user);

        return $this->scan($root, '', $this->readNoteOrder($root), $this->readFolderOrder($root), $this->readFolderColors($root), $this->readFolderCollapsed($root), $this->readPinned($root));
    }

    public function read(User $user, string $path): string
    {
        $file = $this->filePath($user, $path);

        if (! is_file($file)) {
            abort(404);
        }

        return (string) file_get_contents($file);
    }

    /** @return array{created_at: string, markdown_bytes: int} */
    public function noteProperties(User $user, string $path): array
    {
        $file = $this->filePath($user, $path);
        $firstVersion = NoteVersion::query()
            ->where('user_id', $user->getKey())
            ->where('path', $this->normalize($path))
            ->oldest('created_at')
            ->first(['created_at']);

        return [
            'created_at' => $firstVersion?->created_at?->toIso8601String() ?? date(DATE_ATOM, (int) filemtime($file)),
            'markdown_bytes' => (int) filesize($file),
        ];
    }

    public function write(User $user, string $path, string $content, bool $snapshot = false): void
    {
        $file = $this->filePath($user, $path, mustExist: false);
        $directory = dirname($file);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException(__('ui.destination_folder_unavailable'));
        }

        $this->quota()->ensureCanSaveNote($user, $this->root($user), $file, $path, $content, $snapshot);

        file_put_contents($file, $content, LOCK_EX);
    }

    public function writeFromApi(User $user, string $path, string $content, bool $snapshot = true): bool
    {
        $normalized = $this->normalize($path);
        if (! Str::endsWith(Str::lower($normalized), '.md')) {
            abort(404);
        }

        $root = $this->root($user);
        $segments = explode('/', $normalized);
        $filename = array_pop($segments);

        // Create missing parents one at a time, checking each resolved path so
        // a symlink inside the user's space cannot escape the space root.
        $directory = $root;
        foreach ($segments as $segment) {
            $directory .= '/'.$segment;
            if (is_link($directory)) {
                abort(404);
            }

            if (! is_dir($directory) && ! mkdir($directory, 0770) && ! is_dir($directory)) {
                throw new RuntimeException(__('ui.cannot_create_folder'));
            }

            $this->assertContained($root, $directory, true);
        }

        $file = $directory.'/'.$filename;
        $this->assertContained($root, $file, false);

        if (! is_writable($directory)) {
            throw new RuntimeException(__('ui.destination_folder_unavailable'));
        }

        $created = ! is_file($file);
        $this->quota()->ensureCanSaveNote($user, $this->root($user), $file, $path, $content, $snapshot);
        if (file_put_contents($file, $content, LOCK_EX) === false) {
            throw new RuntimeException(__('ui.could_not_save'));
        }

        return $created;
    }

    public function deleteSpace(User $user): void
    {
        $root = $this->root($user);

        if (! File::deleteDirectory($root)) {
            throw new RuntimeException(__('ui.cannot_delete_notes_space'));
        }
    }

    public function createFolder(User $user, string $parent, string $name): void
    {
        $directory = $this->directoryPath($user, $this->join($parent, $name));

        if (file_exists($directory)) {
            throw new RuntimeException(__('ui.folder_already_exists'));
        }

        if (! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException(__('ui.cannot_create_folder'));
        }
    }

    public function createNote(User $user, string $parent, string $name): string
    {
        $relativePath = $this->join($parent, $name).'.md';
        $file = $this->filePath($user, $relativePath, mustExist: false);

        if (file_exists($file)) {
            throw new RuntimeException(__('ui.note_already_exists'));
        }

        $content = '# '.$name."\n\n";
        $this->quota()->ensureCanReplace($user, $this->root($user), $file, strlen($content));
        file_put_contents($file, $content, LOCK_EX);

        return $relativePath;
    }

    /** @return array{id: string, original_path: string, history_path: string, type: string, deleted_at: string} */
    public function delete(User $user, string $path): array
    {
        return $this->trash($user, $path);
    }

    public function rename(User $user, string $path, string $name, ?string $folderColor = null, ?bool $folderCollapsed = null): string
    {
        $root = $this->root($user);
        $sourceRelative = $this->normalize($path);
        $source = $root.'/'.$sourceRelative;
        $this->assertContained($root, $source, true);

        $isNote = is_file($source) && Str::endsWith(Str::lower($source), '.md');
        if (! is_dir($source) && ! $isNote) {
            abort(404);
        }

        $name = trim($name);
        if ($isNote && Str::endsWith(Str::lower($name), '.md')) {
            $name = Str::beforeLast($name, '.');
        }

        $target = dirname($source).'/'.$this->validName($name).($isNote ? '.md' : '');
        $this->assertContained($root, $target, false);

        if ($target === $source) {
            if (! $isNote && $folderColor !== null) {
                $this->setFolderColor($user, $sourceRelative, $folderColor);
            }
            if (! $isNote && $folderCollapsed !== null) {
                $this->setFolderCollapsed($user, $sourceRelative, $folderCollapsed);
            }

            return $sourceRelative;
        }

        if (file_exists($target)) {
            throw new RuntimeException(__('ui.item_already_exists'));
        }

        if (! rename($source, $target)) {
            throw new RuntimeException(__('ui.cannot_rename_item'));
        }

        $targetRelative = ltrim(substr($target, strlen($root)), '/');
        $this->relocateNoteOrder($user, $sourceRelative, $targetRelative);
        $this->relocateFolderOrder($user, $sourceRelative, $targetRelative);
        $this->relocateFolderColors($user, $sourceRelative, $targetRelative);
        $this->relocateFolderCollapsed($user, $sourceRelative, $targetRelative);
        $this->relocatePinned($user, $sourceRelative, $targetRelative);
        if (! $isNote && $folderColor !== null) {
            $this->setFolderColor($user, $targetRelative, $folderColor);
        }
        if (! $isNote && $folderCollapsed !== null) {
            $this->setFolderCollapsed($user, $targetRelative, $folderCollapsed);
        }

        return $targetRelative;
    }

    /** @return array{id: string, original_path: string, history_path: string, type: string, deleted_at: string} */
    public function deleteItem(User $user, string $path): array
    {
        return $this->trash($user, $path);
    }

    public function setPinned(User $user, string $path, bool $pinned): void
    {
        $root = $this->root($user);
        $relativePath = $this->normalize($path);
        $target = $root.'/'.$relativePath;
        $this->assertContained($root, $target, true);

        if (! is_dir($target) && (! is_file($target) || ! Str::endsWith(Str::lower($target), '.md'))) {
            abort(404);
        }

        $items = $this->readPinned($root);
        if ($pinned) {
            $items[$relativePath] = true;
        } else {
            unset($items[$relativePath]);
        }

        $this->writePinned($root, $items);
    }

    /** @return array{id: string, original_path: string, history_path: string, type: string, deleted_at: string} */
    public function trash(User $user, string $path): array
    {
        $root = $this->root($user);
        $relativePath = $this->normalize($path);
        $source = $root.'/'.$relativePath;
        $this->assertContained($root, $source, true);

        $type = is_dir($source) ? 'folder' : (is_file($source) && Str::endsWith(Str::lower($source), '.md') ? 'note' : null);
        if ($type === null) {
            abort(404);
        }

        $id = now()->format('YmdHis').'-'.bin2hex(random_bytes(8));
        $container = $this->trashDirectory($user).'/'.$id;
        $destination = $container.'/content/'.$relativePath;
        $entry = [
            'id' => $id,
            'original_path' => $relativePath,
            'history_path' => self::TRASH_DIRECTORY.'/'.$id.'/content/'.$relativePath,
            'type' => $type,
            'deleted_at' => now()->toIso8601String(),
        ];
        if ($type === 'folder' && ($folderColor = $this->folderColor($user, $relativePath)) !== null) {
            $entry['folder_color'] = $folderColor;
        }
        if ($type === 'folder' && $this->folderCollapsed($user, $relativePath)) {
            $entry['folder_collapsed'] = true;
        }
        if ($this->isPinned($user, $relativePath)) {
            $entry['pinned'] = true;
        }

        if (! mkdir(dirname($destination), 0770, true) && ! is_dir(dirname($destination))) {
            throw new RuntimeException(__('ui.cannot_move_to_trash'));
        }

        if (file_put_contents($container.'/'.self::TRASH_METADATA_FILE, json_encode($entry, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException(__('ui.cannot_move_to_trash'));
        }

        if (! rename($source, $destination)) {
            File::deleteDirectory($container);
            throw new RuntimeException(__('ui.cannot_move_to_trash'));
        }

        $this->removeFromNoteOrder($user, $relativePath);
        $this->removeFromFolderOrder($user, $relativePath);
        $this->removeFromFolderColors($user, $relativePath);
        $this->removeFromFolderCollapsed($user, $relativePath);
        $this->removePinned($user, $relativePath);

        return $entry;
    }

    /** @return array<int, array{id: string, original_path: string, history_path: string, type: string, deleted_at: string}> */
    public function trashItems(User $user): array
    {
        $directory = $this->trashDirectory($user, create: false);
        if (! is_dir($directory)) {
            return [];
        }

        $entries = [];
        foreach (scandir($directory) ?: [] as $id) {
            if ($id === '.' || $id === '..') {
                continue;
            }

            try {
                $entries[] = $this->trashEntry($user, $id);
            } catch (\Throwable) {
                // Ignore incomplete trash records instead of exposing internal files.
            }
        }

        usort($entries, fn (array $a, array $b): int => strcmp($b['deleted_at'], $a['deleted_at']));

        return $entries;
    }

    /** @return array{id: string, original_path: string, history_path: string, type: string, deleted_at: string} */
    public function restoreTrash(User $user, string $id): array
    {
        $entry = $this->trashEntry($user, $id);
        $root = $this->root($user);
        $source = $this->trashDirectory($user).'/'.$entry['id'].'/content/'.$entry['original_path'];
        $destination = $root.'/'.$entry['original_path'];

        if (file_exists($destination)) {
            throw new RuntimeException(__('ui.cannot_restore_trashed_item'));
        }

        if (! is_dir(dirname($destination)) && ! mkdir(dirname($destination), 0770, true) && ! is_dir(dirname($destination))) {
            throw new RuntimeException(__('ui.cannot_restore_trashed_item'));
        }

        $this->assertContained($root, $destination, false);
        if (! rename($source, $destination)) {
            throw new RuntimeException(__('ui.cannot_restore_trashed_item'));
        }

        File::deleteDirectory($this->trashDirectory($user).'/'.$entry['id']);
        if ($entry['type'] === 'folder' && is_string($entry['folder_color'] ?? null)) {
            $this->setFolderColor($user, $entry['original_path'], $entry['folder_color']);
        }
        if ($entry['type'] === 'folder' && ($entry['folder_collapsed'] ?? false) === true) {
            $this->setFolderCollapsed($user, $entry['original_path'], true);
        }
        if (($entry['pinned'] ?? false) === true) {
            $this->setPinned($user, $entry['original_path'], true);
        }

        return $entry;
    }

    /** @return array{id: string, original_path: string, history_path: string, type: string, deleted_at: string} */
    public function deleteTrash(User $user, string $id): array
    {
        $entry = $this->trashEntry($user, $id);
        if (! File::deleteDirectory($this->trashDirectory($user).'/'.$entry['id'])) {
            throw new RuntimeException(__('ui.cannot_delete_trashed_item'));
        }

        return $entry;
    }

    public function move(User $user, string $sourcePath, string $destinationPath): string
    {
        $root = $this->root($user);
        $sourceRelative = $this->normalize($sourcePath);
        $source = $root.'/'.$sourceRelative;
        $this->assertContained($root, $source, true);

        if (! file_exists($source) || (! is_dir($source) && ! Str::endsWith(Str::lower($source), '.md'))) {
            abort(404);
        }

        $destination = trim($destinationPath) === ''
            ? $root
            : $this->existingDirectoryPath($user, $destinationPath);

        if (is_dir($source) && ($destination === $source || Str::startsWith($destination.'/', $source.'/'))) {
            throw new RuntimeException(__('ui.cannot_move_folder_into_itself'));
        }

        $target = $destination.'/'.basename($source);
        if ($target === $source) {
            return $sourceRelative;
        }

        if (file_exists($target)) {
            throw new RuntimeException(__('ui.item_exists_in_destination'));
        }

        if (! rename($source, $target)) {
            throw new RuntimeException(__('ui.cannot_move_item'));
        }

        $targetRelative = ltrim(substr($target, strlen($root)), '/');
        $this->relocateNoteOrder($user, $sourceRelative, $targetRelative);
        $this->relocateFolderOrder($user, $sourceRelative, $targetRelative);
        $this->relocateFolderColors($user, $sourceRelative, $targetRelative);
        $this->relocateFolderCollapsed($user, $sourceRelative, $targetRelative);
        $this->relocatePinned($user, $sourceRelative, $targetRelative);

        return $targetRelative;
    }

    public function reorderNote(User $user, string $sourcePath, string $targetPath, string $position): string
    {
        $sourceRelative = $this->normalize($sourcePath);
        $targetRelative = $this->normalize($targetPath);
        $source = $this->filePath($user, $sourceRelative);
        $target = $this->filePath($user, $targetRelative);

        if (! is_file($source) || ! is_file($target) || $sourceRelative === $targetRelative) {
            abort(404);
        }

        $targetParent = $this->parentPath($targetRelative);
        if ($this->parentPath($sourceRelative) !== $targetParent) {
            $sourceRelative = $this->move($user, $sourceRelative, $targetParent);
        }

        $root = $this->root($user);
        $order = $this->readNoteOrder($root);
        $siblings = $this->sortNotePaths(
            $this->directNotePaths($root, $targetParent),
            $order[$targetParent] ?? [],
        );

        $siblings = array_values(array_filter($siblings, fn (string $path): bool => $path !== $sourceRelative));
        $targetIndex = array_search($targetRelative, $siblings, true);
        if ($targetIndex === false) {
            abort(404);
        }

        array_splice($siblings, $targetIndex + ($position === 'after' ? 1 : 0), 0, [$sourceRelative]);
        $order[$targetParent] = $siblings;
        $this->writeNoteOrder($root, $order);

        return $sourceRelative;
    }

    public function reorderFolder(User $user, string $sourcePath, string $targetPath, string $position): string
    {
        $sourceRelative = $this->normalize($sourcePath);
        $targetRelative = $this->normalize($targetPath);
        $root = $this->root($user);
        $source = $root.'/'.$sourceRelative;
        $target = $root.'/'.$targetRelative;
        $this->assertContained($root, $source, true);
        $this->assertContained($root, $target, true);

        if (! is_dir($source) || ! is_dir($target) || $sourceRelative === $targetRelative) {
            abort(404);
        }

        $targetParent = $this->parentPath($targetRelative);
        if ($this->parentPath($sourceRelative) !== $targetParent) {
            $sourceRelative = $this->move($user, $sourceRelative, $targetParent);
        }

        $order = $this->readFolderOrder($root);
        $siblings = $this->sortNodes(
            $this->folderNodes($this->directFolderPaths($root, $targetParent)),
            $order[$targetParent] ?? [],
        );
        $paths = array_column($siblings, 'path');

        $paths = array_values(array_filter($paths, fn (string $path): bool => $path !== $sourceRelative));
        $targetIndex = array_search($targetRelative, $paths, true);
        if ($targetIndex === false) {
            abort(404);
        }

        array_splice($paths, $targetIndex + ($position === 'after' ? 1 : 0), 0, [$sourceRelative]);
        $order[$targetParent] = $paths;
        $this->writeFolderOrder($root, $order);

        return $sourceRelative;
    }

    public function importLegacySpace(User $user): void
    {
        $destination = $this->root($user);

        if (count(scandir($destination) ?: []) > 2 || ! is_dir('/legacy-space')) {
            return;
        }

        $legacy = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator('/legacy-space', \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($legacy as $item) {
            if ($item->isLink()) {
                continue;
            }

            $relative = ltrim(str_replace('/legacy-space', '', $item->getPathname()), '/');
            if ($relative === '' || Str::startsWith($relative, ['_plug/', 'Library/', 'CONFIG.md', '.silverbullet'])) {
                continue;
            }

            $target = $destination.'/'.$relative;
            if ($item->isDir()) {
                is_dir($target) || mkdir($target, 0770, true);
            } elseif ($item->isFile()) {
                is_dir(dirname($target)) || mkdir(dirname($target), 0770, true);
                copy($item->getPathname(), $target);
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function scan(string $directory, string $prefix = '', array $noteOrder = [], array $folderOrder = [], array $folderColors = [], array $folderCollapsed = [], array $pinned = []): array
    {
        $folders = [];
        $files = [];

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, ['.md-notes-media', self::TRASH_DIRECTORY], true) || is_link($directory.'/'.$entry)) {
                continue;
            }

            $relative = $prefix === '' ? $entry : $prefix.'/'.$entry;
            if (is_dir($directory.'/'.$entry)) {
                $folders[] = ['type' => 'folder', 'name' => $entry, 'path' => $relative, 'color' => $folderColors[$relative] ?? null, 'collapsed' => isset($folderCollapsed[$relative]), 'pinned' => isset($pinned[$relative]), 'children' => $this->scan($directory.'/'.$entry, $relative, $noteOrder, $folderOrder, $folderColors, $folderCollapsed, $pinned)];
            } elseif (Str::endsWith(Str::lower($entry), '.md')) {
                $files[] = ['type' => 'note', 'name' => Str::beforeLast($entry, '.'), 'path' => $relative, 'pinned' => isset($pinned[$relative])];
            }
        }

        $folders = $this->sortNodes($folders, $folderOrder[$prefix] ?? []);
        $files = $this->sortNodes($files, $noteOrder[$prefix] ?? []);

        return [...$files, ...$folders];
    }

    /** @param array<int, array{type: string, name: string, path: string}> $nodes @param array<int, string> $order */
    private function sortNodes(array $nodes, array $order): array
    {
        $positions = array_flip($order);

        usort($nodes, function (array $a, array $b) use ($positions): int {
            if (($a['pinned'] ?? false) !== ($b['pinned'] ?? false)) {
                return ($a['pinned'] ?? false) ? -1 : 1;
            }

            $aPosition = $positions[$a['path']] ?? PHP_INT_MAX;
            $bPosition = $positions[$b['path']] ?? PHP_INT_MAX;

            return $aPosition === $bPosition
                ? strnatcasecmp($a['name'], $b['name'])
                : $aPosition <=> $bPosition;
        });

        return $nodes;
    }

    /** @return array<int, string> */
    private function sortNotePaths(array $paths, array $order): array
    {
        $nodes = array_map(fn (string $path): array => [
            'type' => 'note',
            'name' => basename($path),
            'path' => $path,
        ], $paths);

        return array_column($this->sortNodes($nodes, $order), 'path');
    }

    /** @param array<int, string> $paths @return array<int, array{type: string, name: string, path: string}> */
    private function folderNodes(array $paths): array
    {
        return array_map(fn (string $path): array => [
            'type' => 'folder',
            'name' => basename($path),
            'path' => $path,
        ], $paths);
    }

    /** @return array<int, string> */
    private function directNotePaths(string $root, string $parent): array
    {
        $directory = $parent === '' ? $root : $root.'/'.$parent;
        $paths = [];

        foreach (scandir($directory) ?: [] as $entry) {
            if (Str::endsWith(Str::lower($entry), '.md') && is_file($directory.'/'.$entry)) {
                $paths[] = $parent === '' ? $entry : $parent.'/'.$entry;
            }
        }

        return $paths;
    }

    /** @return array<int, string> */
    private function directFolderPaths(string $root, string $parent): array
    {
        $directory = $parent === '' ? $root : $root.'/'.$parent;
        $paths = [];

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..' && ! in_array($entry, ['.md-notes-media', self::TRASH_DIRECTORY], true) && is_dir($directory.'/'.$entry) && ! is_link($directory.'/'.$entry)) {
                $paths[] = $parent === '' ? $entry : $parent.'/'.$entry;
            }
        }

        return $paths;
    }

    private function parentPath(string $path): string
    {
        $parent = dirname($path);

        return $parent === '.' ? '' : $parent;
    }

    private function trashDirectory(User $user, bool $create = true): string
    {
        $directory = $this->root($user).'/'.self::TRASH_DIRECTORY;

        if ($create && ! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException(__('ui.cannot_move_to_trash'));
        }

        return $directory;
    }

    /** @return array{id: string, original_path: string, history_path: string, type: string, deleted_at: string} */
    private function trashEntry(User $user, string $id): array
    {
        if (! preg_match('/^\d{14}-[a-f0-9]{16}$/', $id)) {
            abort(404);
        }

        $container = $this->trashDirectory($user, create: false).'/'.$id;
        $metadata = @file_get_contents($container.'/'.self::TRASH_METADATA_FILE);
        $entry = is_string($metadata) ? json_decode($metadata, true) : null;

        if (! is_array($entry)
            || ($entry['id'] ?? null) !== $id
            || ! is_string($entry['original_path'] ?? null)
            || ! in_array($entry['type'] ?? null, ['note', 'folder'], true)
            || ! is_string($entry['deleted_at'] ?? null)) {
            abort(404);
        }

        $originalPath = $this->normalize($entry['original_path']);
        $content = $container.'/content/'.$originalPath;
        $this->assertContained($this->root($user), $content, true);

        if (($entry['type'] === 'note' && ! is_file($content)) || ($entry['type'] === 'folder' && ! is_dir($content))) {
            abort(404);
        }

        return [
            'id' => $id,
            'original_path' => $originalPath,
            'history_path' => self::TRASH_DIRECTORY.'/'.$id.'/content/'.$originalPath,
            'type' => $entry['type'],
            'deleted_at' => $entry['deleted_at'],
        ];
    }

    /** @return array<string, array<int, string>> */
    private function readNoteOrder(string $root): array
    {
        $content = @file_get_contents($root.'/'.self::NOTE_ORDER_FILE);
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        $order = [];
        foreach ($decoded as $parent => $paths) {
            if (! is_string($parent) || ! is_array($paths)) {
                continue;
            }

            $order[$parent] = array_values(array_unique(array_filter($paths, 'is_string')));
        }

        return $order;
    }

    /** @param array<string, array<int, string>> $order */
    private function writeNoteOrder(string $root, array $order): void
    {
        $cleanOrder = [];
        foreach ($order as $parent => $paths) {
            $paths = array_values(array_unique(array_filter($paths, 'is_string')));
            if ($paths !== []) {
                $cleanOrder[$parent] = $paths;
            }
        }

        $file = $root.'/'.self::NOTE_ORDER_FILE;
        if ($cleanOrder === []) {
            if (is_file($file) && ! unlink($file)) {
                throw new RuntimeException(__('ui.cannot_save_note_order'));
            }

            return;
        }

        $content = json_encode($cleanOrder, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($file, $content, LOCK_EX) === false) {
            throw new RuntimeException(__('ui.cannot_save_note_order'));
        }
    }

    private function relocateNoteOrder(User $user, string $sourcePath, string $destinationPath): void
    {
        $root = $this->root($user);
        $order = $this->readNoteOrder($root);
        $relocated = [];

        foreach ($order as $parent => $paths) {
            $newParent = $this->relocateOrderPath($parent, $sourcePath, $destinationPath);
            $relocated[$newParent] = array_merge($relocated[$newParent] ?? [], array_map(
                fn (string $path): string => $this->relocateOrderPath($path, $sourcePath, $destinationPath),
                $paths,
            ));
        }

        $this->writeNoteOrder($root, $relocated);
    }

    private function removeFromNoteOrder(User $user, string $path): void
    {
        $root = $this->root($user);
        $order = $this->readNoteOrder($root);

        foreach ($order as $parent => $paths) {
            if ($parent === $path || Str::startsWith($parent, $path.'/')) {
                unset($order[$parent]);
                continue;
            }

            $order[$parent] = array_values(array_filter($paths, fn (string $item): bool => $item !== $path && ! Str::startsWith($item, $path.'/')));
        }

        $this->writeNoteOrder($root, $order);
    }

    private function relocateOrderPath(string $path, string $sourcePath, string $destinationPath): string
    {
        if ($path === $sourcePath) {
            return $destinationPath;
        }

        return Str::startsWith($path, $sourcePath.'/')
            ? $destinationPath.Str::after($path, $sourcePath)
            : $path;
    }

    /** @return array<string, array<int, string>> */
    private function readFolderOrder(string $root): array
    {
        return $this->readOrderFile($root.'/'.self::FOLDER_ORDER_FILE);
    }

    /** @param array<string, array<int, string>> $order */
    private function writeFolderOrder(string $root, array $order): void
    {
        $this->writeOrderFile($root.'/'.self::FOLDER_ORDER_FILE, $order);
    }

    private function relocateFolderOrder(User $user, string $sourcePath, string $destinationPath): void
    {
        $root = $this->root($user);
        $this->writeFolderOrder($root, $this->relocatedOrder(
            $this->readFolderOrder($root),
            $sourcePath,
            $destinationPath,
        ));
    }

    private function removeFromFolderOrder(User $user, string $path): void
    {
        $root = $this->root($user);
        $this->writeFolderOrder($root, $this->orderWithoutPath($this->readFolderOrder($root), $path));
    }

    /** @return array<string, string> */
    private function readFolderColors(string $root): array
    {
        $content = @file_get_contents($root.'/'.self::FOLDER_COLORS_FILE);
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        $colors = [];
        foreach ($decoded as $path => $color) {
            if (is_string($path) && is_string($color) && in_array($color, self::FOLDER_COLORS, true)) {
                $colors[$path] = $color;
            }
        }

        return $colors;
    }

    /** @param array<string, string> $colors */
    private function writeFolderColors(string $root, array $colors): void
    {
        $file = $root.'/'.self::FOLDER_COLORS_FILE;
        if ($colors === []) {
            if (is_file($file) && ! unlink($file)) {
                throw new RuntimeException(__('ui.cannot_save_folder_color'));
            }

            return;
        }

        if (file_put_contents($file, json_encode($colors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException(__('ui.cannot_save_folder_color'));
        }
    }

    private function folderColor(User $user, string $path): ?string
    {
        return $this->readFolderColors($this->root($user))[$path] ?? null;
    }

    private function setFolderColor(User $user, string $path, string $color): void
    {
        $root = $this->root($user);
        $colors = $this->readFolderColors($root);
        $path = $this->normalize($path);

        if ($color === '') {
            unset($colors[$path]);
        } elseif (in_array($color, self::FOLDER_COLORS, true)) {
            $colors[$path] = $color;
        } else {
            throw new RuntimeException(__('ui.invalid_folder_color'));
        }

        $this->writeFolderColors($root, $colors);
    }

    private function relocateFolderColors(User $user, string $sourcePath, string $destinationPath): void
    {
        $root = $this->root($user);
        $colors = [];
        foreach ($this->readFolderColors($root) as $path => $color) {
            $colors[$this->relocateOrderPath($path, $sourcePath, $destinationPath)] = $color;
        }

        $this->writeFolderColors($root, $colors);
    }

    private function removeFromFolderColors(User $user, string $path): void
    {
        $root = $this->root($user);
        $colors = array_filter(
            $this->readFolderColors($root),
            fn (string $color, string $folderPath): bool => $folderPath !== $path && ! Str::startsWith($folderPath, $path.'/'),
            ARRAY_FILTER_USE_BOTH,
        );

        $this->writeFolderColors($root, $colors);
    }

    /** @return array<string, true> */
    private function readFolderCollapsed(string $root): array
    {
        $content = @file_get_contents($root.'/'.self::FOLDER_COLLAPSED_FILE);
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        $collapsed = [];
        foreach ($decoded as $path => $value) {
            if (is_string($path) && $value === true) {
                $collapsed[$path] = true;
            }
        }

        return $collapsed;
    }

    /** @param array<string, true> $collapsed */
    private function writeFolderCollapsed(string $root, array $collapsed): void
    {
        $file = $root.'/'.self::FOLDER_COLLAPSED_FILE;
        if ($collapsed === []) {
            if (is_file($file) && ! unlink($file)) {
                throw new RuntimeException(__('ui.cannot_save_folder_collapsed'));
            }

            return;
        }

        if (file_put_contents($file, json_encode($collapsed, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException(__('ui.cannot_save_folder_collapsed'));
        }
    }

    private function folderCollapsed(User $user, string $path): bool
    {
        return isset($this->readFolderCollapsed($this->root($user))[$path]);
    }

    private function setFolderCollapsed(User $user, string $path, bool $collapsed): void
    {
        $root = $this->root($user);
        $folders = $this->readFolderCollapsed($root);
        $path = $this->normalize($path);

        if ($collapsed) {
            $folders[$path] = true;
        } else {
            unset($folders[$path]);
        }

        $this->writeFolderCollapsed($root, $folders);
    }

    private function relocateFolderCollapsed(User $user, string $sourcePath, string $destinationPath): void
    {
        $root = $this->root($user);
        $collapsed = [];
        foreach ($this->readFolderCollapsed($root) as $path => $value) {
            $collapsed[$this->relocateOrderPath($path, $sourcePath, $destinationPath)] = $value;
        }

        $this->writeFolderCollapsed($root, $collapsed);
    }

    private function removeFromFolderCollapsed(User $user, string $path): void
    {
        $root = $this->root($user);
        $collapsed = array_filter(
            $this->readFolderCollapsed($root),
            fn (bool $value, string $folderPath): bool => $folderPath !== $path && ! Str::startsWith($folderPath, $path.'/'),
            ARRAY_FILTER_USE_BOTH,
        );

        $this->writeFolderCollapsed($root, $collapsed);
    }

    /** @return array<string, true> */
    private function readPinned(string $root): array
    {
        $content = @file_get_contents($root.'/'.self::PINNED_FILE);
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        $pinned = [];
        foreach ($decoded as $path => $value) {
            if (is_string($path) && $value === true) {
                $pinned[$path] = true;
            }
        }

        return $pinned;
    }

    /** @param array<string, true> $pinned */
    private function writePinned(string $root, array $pinned): void
    {
        $file = $root.'/'.self::PINNED_FILE;
        if ($pinned === []) {
            if (is_file($file) && ! unlink($file)) {
                throw new RuntimeException(__('ui.cannot_save_pinned_items'));
            }

            return;
        }

        if (file_put_contents($file, json_encode($pinned, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException(__('ui.cannot_save_pinned_items'));
        }
    }

    private function isPinned(User $user, string $path): bool
    {
        return isset($this->readPinned($this->root($user))[$path]);
    }

    private function relocatePinned(User $user, string $sourcePath, string $destinationPath): void
    {
        $root = $this->root($user);
        $pinned = [];
        foreach ($this->readPinned($root) as $path => $value) {
            $pinned[$this->relocateOrderPath($path, $sourcePath, $destinationPath)] = $value;
        }

        $this->writePinned($root, $pinned);
    }

    private function removePinned(User $user, string $path): void
    {
        $root = $this->root($user);
        $pinned = array_filter(
            $this->readPinned($root),
            fn (bool $value, string $itemPath): bool => $itemPath !== $path && ! Str::startsWith($itemPath, $path.'/'),
            ARRAY_FILTER_USE_BOTH,
        );

        $this->writePinned($root, $pinned);
    }

    /** @return array<string, array<int, string>> */
    private function readOrderFile(string $file): array
    {
        $content = @file_get_contents($file);
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        $order = [];
        foreach ($decoded as $parent => $paths) {
            if (is_string($parent) && is_array($paths)) {
                $order[$parent] = array_values(array_unique(array_filter($paths, 'is_string')));
            }
        }

        return $order;
    }

    /** @param array<string, array<int, string>> $order */
    private function writeOrderFile(string $file, array $order): void
    {
        $cleanOrder = [];
        foreach ($order as $parent => $paths) {
            $paths = array_values(array_unique(array_filter($paths, 'is_string')));
            if ($paths !== []) {
                $cleanOrder[$parent] = $paths;
            }
        }

        if ($cleanOrder === []) {
            if (is_file($file) && ! unlink($file)) {
                throw new RuntimeException(__('ui.cannot_save_note_order'));
            }

            return;
        }

        if (file_put_contents($file, json_encode($cleanOrder, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException(__('ui.cannot_save_note_order'));
        }
    }

    /** @param array<string, array<int, string>> $order @return array<string, array<int, string>> */
    private function relocatedOrder(array $order, string $sourcePath, string $destinationPath): array
    {
        $relocated = [];
        foreach ($order as $parent => $paths) {
            $newParent = $this->relocateOrderPath($parent, $sourcePath, $destinationPath);
            $relocated[$newParent] = array_merge($relocated[$newParent] ?? [], array_map(
                fn (string $path): string => $this->relocateOrderPath($path, $sourcePath, $destinationPath),
                $paths,
            ));
        }

        return $relocated;
    }

    /** @param array<string, array<int, string>> $order @return array<string, array<int, string>> */
    private function orderWithoutPath(array $order, string $path): array
    {
        foreach ($order as $parent => $paths) {
            if ($parent === $path || Str::startsWith($parent, $path.'/')) {
                unset($order[$parent]);
                continue;
            }

            $order[$parent] = array_values(array_filter($paths, fn (string $item): bool => $item !== $path && ! Str::startsWith($item, $path.'/')));
        }

        return $order;
    }

    private function filePath(User $user, string $path, bool $mustExist = true): string
    {
        $normalized = $this->normalize($path);

        if (! Str::endsWith(Str::lower($normalized), '.md')) {
            abort(404);
        }

        $file = $this->root($user).'/'.$normalized;
        $this->assertContained($this->root($user), $file, $mustExist);

        return $file;
    }

    private function directoryPath(User $user, string $path): string
    {
        $directory = $this->root($user).'/'.$this->normalize($path);
        $this->assertContained($this->root($user), $directory, false);

        return $directory;
    }

    private function existingDirectoryPath(User $user, string $path): string
    {
        $directory = $this->root($user).'/'.$this->normalize($path);
        $this->assertContained($this->root($user), $directory, true);

        if (! is_dir($directory)) {
            abort(404);
        }

        return $directory;
    }

    private function join(string $parent, string $name): string
    {
        return trim($parent) === '' ? $this->validName($name) : $this->normalize($parent).'/'.$this->validName($name);
    }

    private function validName(string $name): string
    {
        $name = trim($name);
        if (! preg_match('/^[\\pL\\pN][\\pL\\pN _().,!&-]{0,79}$/u', $name)) {
            throw new RuntimeException('El nombre solo puede tener letras, números, espacios y signos básicos.');
        }

        return $name;
    }

    private function normalize(string $path): string
    {
        $segments = array_filter(explode('/', str_replace('\\\\', '/', trim($path))), fn (string $part): bool => $part !== '');

        if ($segments === [] || in_array('..', $segments, true) || in_array('.', $segments, true)) {
            abort(404);
        }

        foreach ($segments as $segment) {
            // This directory can remain in spaces imported before legacy
            // SilverBullet plug folders were excluded from imports.
            if ($segment !== '_plug' && ! preg_match('/^[\\pL\\pN][\\pL\\pN _().,!&-]{0,79}(?:\\.md)?$/u', $segment)) {
                abort(404);
            }
        }

        return implode('/', $segments);
    }

    private function assertContained(string $root, string $path, bool $mustExist): void
    {
        $candidate = $mustExist ? realpath($path) : realpath(dirname($path));
        $realRoot = realpath($root);

        if ($candidate === false || $realRoot === false || (! Str::startsWith($candidate, $realRoot.'/') && $candidate !== $realRoot)) {
            abort(404);
        }
    }

    private function quota(): StorageQuota
    {
        return app(StorageQuota::class);
    }
}
