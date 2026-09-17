@foreach ($nodes as $node)
    @if ($node['type'] === 'folder')
        <details class="tree-folder" @if (!($node['collapsed'] ?? false) || (!empty($path) && str_starts_with($path, $node['path'].'/'))) open @endif data-context-type="folder" data-context-path="{{ $node['path'] }}" data-context-name="{{ $node['name'] }}" data-context-color="{{ $node['color'] ?? '' }}" data-context-collapsed="{{ ($node['collapsed'] ?? false) ? '1' : '0' }}" data-context-pinned="{{ ($node['pinned'] ?? false) ? '1' : '0' }}" @if (!empty($node['color'])) style="--folder-color:{{ $node['color'] }}" @endif>
            <summary draggable="true" data-drag-path="{{ $node['path'] }}" data-drag-type="folder" data-drop-path="{{ $node['path'] }}" data-folder-sort-target>▾ <svg class="folder-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.8A2.3 2.3 0 0 1 5.8 4.5h4l1.8 2H18a2.5 2.5 0 0 1 2.5 2.5v7.2a2.3 2.3 0 0 1-2.3 2.3H5.8a2.3 2.3 0 0 1-2.3-2.3z"/></svg><span class="tree-item-name">{{ $node['name'] }}</span>@if ($node['pinned'] ?? false)<span class="pin-indicator" title="{{ __('ui.pin') }}" aria-label="{{ __('ui.pin') }}">📌</span>@endif</summary>
            <div class="tree-children">@include('notes._tree', ['nodes' => $node['children'], 'path' => $path])</div>
        </details>
    @else
        <a class="tree-note {{ $path === $node['path'] ? 'active' : '' }}" href="{{ route('notes.show', ['path' => $node['path']]) }}" draggable="true" data-drag-path="{{ $node['path'] }}" data-drag-type="note" data-context-type="note" data-context-path="{{ $node['path'] }}" data-context-name="{{ $node['name'] }}" data-context-pinned="{{ ($node['pinned'] ?? false) ? '1' : '0' }}">▤ <span class="tree-item-name">{{ $node['name'] }}</span>@if ($node['pinned'] ?? false)<span class="pin-indicator" title="{{ __('ui.pin') }}" aria-label="{{ __('ui.pin') }}">📌</span>@endif</a>
    @endif
@endforeach
