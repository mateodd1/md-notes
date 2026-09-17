@foreach ($nodes as $node)
    @if ($node['type'] === 'folder')
        <details class="tree-folder" open data-context-type="folder" data-context-path="{{ $node['path'] }}" data-context-name="{{ $node['name'] }}">
            <summary draggable="true" data-drag-path="{{ $node['path'] }}" data-drag-type="folder" data-drop-path="{{ $node['path'] }}" data-folder-sort-target>▾ <svg class="folder-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.8A2.3 2.3 0 0 1 5.8 4.5h4l1.8 2H18a2.5 2.5 0 0 1 2.5 2.5v7.2a2.3 2.3 0 0 1-2.3 2.3H5.8a2.3 2.3 0 0 1-2.3-2.3z"/></svg><span>{{ $node['name'] }}</span></summary>
            <div class="tree-children">@include('notes._tree', ['nodes' => $node['children'], 'path' => $path])</div>
        </details>
    @else
        <a class="tree-note {{ $path === $node['path'] ? 'active' : '' }}" href="{{ route('notes.show', ['path' => $node['path']]) }}" draggable="true" data-drag-path="{{ $node['path'] }}" data-drag-type="note" data-context-type="note" data-context-path="{{ $node['path'] }}" data-context-name="{{ $node['name'] }}">▤ <span>{{ $node['name'] }}</span></a>
    @endif
@endforeach
