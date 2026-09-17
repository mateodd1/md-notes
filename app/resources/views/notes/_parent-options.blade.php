@foreach ($nodes as $node)
    @if ($node['type'] === 'folder')
        <button type="button" class="parent-option" data-parent-option data-parent-value="{{ $node['path'] }}" data-parent-label="{{ $node['path'] }}" style="--parent-depth: {{ $depth }}">📁 {{ $node['name'] }}</button>
        @include('notes._parent-options', ['nodes' => $node['children'], 'depth' => $depth + 1])
    @endif
@endforeach
