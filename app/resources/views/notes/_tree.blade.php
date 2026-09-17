@foreach ($nodes as $node)
    @if ($node['type'] === 'folder')
        <details class="tree-folder" open data-context-type="folder" data-context-path="{{ $node['path'] }}" data-context-name="{{ $node['name'] }}">
            <summary draggable="true" data-drag-path="{{ $node['path'] }}" data-drag-type="folder" data-drop-path="{{ $node['path'] }}" data-folder-sort-target>▾ <span>📁 {{ $node['name'] }}</span></summary>
            <div class="tree-children">@include('notes._tree', ['nodes' => $node['children'], 'path' => $path])</div>
        </details>
    @else
        <a class="tree-note {{ $path === $node['path'] ? 'active' : '' }}" href="{{ route('notes.show', ['path' => $node['path']]) }}" draggable="true" data-drag-path="{{ $node['path'] }}" data-drag-type="note" data-context-type="note" data-context-path="{{ $node['path'] }}" data-context-name="{{ $node['name'] }}">▤ <span>{{ $node['name'] }}</span></a>
    @endif
@endforeach
