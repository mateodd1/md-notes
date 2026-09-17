<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class NoteSpace
{
    private const NOTE_ORDER_FILE = '.md-notes-order.json';

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

        return $this->scan($root, '', $this->readNoteOrder($root));
    }

    public function read(User $user, string $path): string
    {
        $file = $this->filePath($user, $path);

        if (! is_file($file)) {
            abort(404);
        }

        return (string) file_get_contents($file);
    }

    public function write(User $user, string $path, string $content): void
    {
        $file = $this->filePath($user, $path, mustExist: false);
        $directory = dirname($file);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException(__('ui.destination_folder_unavailable'));
        }

        file_put_contents($file, $content, LOCK_EX);
    }

    public function writeFromApi(User $user, string $path, string $content): bool
    {
        $file = $this->filePath($user, $path, mustExist: false);
        $directory = dirname($file);

        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException(__('ui.cannot_create_folder'));
        }

        if (! is_writable($directory)) {
            throw new RuntimeException(__('ui.destination_folder_unavailable'));
        }

        $created = ! is_file($file);
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

        file_put_contents($file, '# '.$name."\n\n", LOCK_EX);

        return $relativePath;
    }

    public function delete(User $user, string $path): void
    {
        $file = $this->filePath($user, $path);

        if (! is_file($file) || ! unlink($file)) {
            throw new RuntimeException(__('ui.cannot_delete_note'));
        }

        $this->removeFromNoteOrder($user, $this->normalize($path));
    }

    public function rename(User $user, string $path, string $name): string
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

        return $targetRelative;
    }

    public function deleteItem(User $user, string $path): void
    {
        $root = $this->root($user);
        $relativePath = $this->normalize($path);
        $target = $root.'/'.$relativePath;
        $this->assertContained($root, $target, true);

        if (is_file($target)) {
            if (! Str::endsWith(Str::lower($target), '.md') || ! unlink($target)) {
                throw new RuntimeException(__('ui.cannot_delete_note'));
            }

            $this->removeFromNoteOrder($user, $relativePath);

            return;
        }

        if (! is_dir($target)) {
            abort(404);
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $itemPath = $item->getPathname();
            if ($item->isLink()) {
                $deleted = unlink($itemPath);
            } elseif ($item->isDir()) {
                $deleted = rmdir($itemPath);
            } else {
                $deleted = unlink($itemPath);
            }

            if (! $deleted) {
                throw new RuntimeException(__('ui.cannot_delete_folder_contents'));
            }
        }

        if (! rmdir($target)) {
            throw new RuntimeException(__('ui.cannot_delete_folder'));
        }

        $this->removeFromNoteOrder($user, $relativePath);
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
    private function scan(string $directory, string $prefix = '', array $noteOrder = []): array
    {
        $folders = [];
        $files = [];

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.md-notes-media' || is_link($directory.'/'.$entry)) {
                continue;
            }

            $relative = $prefix === '' ? $entry : $prefix.'/'.$entry;
            if (is_dir($directory.'/'.$entry)) {
                $folders[] = ['type' => 'folder', 'name' => $entry, 'path' => $relative, 'children' => $this->scan($directory.'/'.$entry, $relative, $noteOrder)];
            } elseif (Str::endsWith(Str::lower($entry), '.md')) {
                $files[] = ['type' => 'note', 'name' => Str::beforeLast($entry, '.'), 'path' => $relative];
            }
        }

        usort($folders, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        $files = $this->sortNodes($files, $noteOrder[$prefix] ?? []);

        return [...$files, ...$folders];
    }

    /** @param array<int, array{type: string, name: string, path: string}> $nodes @param array<int, string> $order */
    private function sortNodes(array $nodes, array $order): array
    {
        $positions = array_flip($order);

        usort($nodes, function (array $a, array $b) use ($positions): int {
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

    private function parentPath(string $path): string
    {
        $parent = dirname($path);

        return $parent === '.' ? '' : $parent;
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
            if (! preg_match('/^[\\pL\\pN][\\pL\\pN _().,!&-]{0,79}(?:\\.md)?$/u', $segment)) {
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
}
