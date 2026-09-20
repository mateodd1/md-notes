@php
    $workspaceBase = rtrim(route('notes.index'), '/');
    $workspaceConfig = [
        'activePath' => $path,
        'translations' => $workspaceTranslations,
        'searchTranslations' => collect(['hint', 'loading', 'empty', 'failed', 'more'])->mapWithKeys(fn ($key) => [$key => __('ui.search_'.$key)]),
        'urls' => [
            'quota' => route('quota.show'),
            'search' => route('notes.search'),
            'pin' => route('items.pin'),
            'notes' => $workspaceBase,
            'history' => $workspaceBase.'/history',
            'versions' => $workspaceBase.'/versions',
            'move' => route('notes.move'),
            'reorderNotes' => route('notes.reorder'),
            'reorderFolders' => route('folders.reorder'),
            'media' => route('media.store'),
            'properties' => $workspaceBase.'/properties',
        ],
    ];
@endphp
<script>
    window.mdNotesWorkspace = @json($workspaceConfig);
</script>
<script defer src="{{ asset('assets/md-notes-saver.js') }}?v={{ filemtime(public_path('assets/md-notes-saver.js')) }}"></script>
<script defer src="{{ asset('assets/md-notes-search.js') }}?v={{ filemtime(public_path('assets/md-notes-search.js')) }}"></script>
<script defer src="{{ asset('assets/md-notes-workspace.js') }}?v={{ filemtime(public_path('assets/md-notes-workspace.js')) }}"></script>
