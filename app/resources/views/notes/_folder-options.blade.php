@foreach ($nodes as $node)
    @if ($node['type'] === 'folder')
        <option value="{{ $node['path'] }}">{{ str_repeat('— ', $depth) }}{{ $node['name'] }}</option>
        @include('notes._folder-options', ['nodes' => $node['children'], 'depth' => $depth + 1])
    @endif
@endforeach
