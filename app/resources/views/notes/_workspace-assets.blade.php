@php
    $workspaceConfig = [
        'activePath' => $path,
        'translations' => $workspaceTranslations,
        'urls' => [
            'quota' => route('quota.show'),
            'pin' => route('items.pin'),
            'notes' => url('/app'),
            'history' => url('/app/history'),
            'versions' => url('/app/versions'),
            'move' => route('notes.move'),
            'reorderNotes' => route('notes.reorder'),
            'reorderFolders' => route('folders.reorder'),
            'media' => route('media.store'),
            'properties' => url('/app/properties'),
        ],
    ];
@endphp
<script>
    window.mdNotesWorkspace = @json($workspaceConfig);
</script>
<script defer src="{{ asset('assets/md-notes-workspace.js') }}"></script>
