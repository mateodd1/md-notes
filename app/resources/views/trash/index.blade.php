@extends('layouts.app')

@php($title = __('ui.trash').' · md-notes')

@push('head')
<link rel="stylesheet" href="{{ asset('assets/md-notes-trash.css') }}?v={{ filemtime(public_path('assets/md-notes-trash.css')) }}">
<script src="{{ asset('assets/md-notes-trash.js') }}?v={{ filemtime(public_path('assets/md-notes-trash.js')) }}" defer></script>
@endpush

@section('body')
<main class="trash-shell">
    <header class="trash-header">
        <div><a class="trash-brand" href="{{ route('notes.index') }}"><x-icon name="spark" class="brand-icon" />md-notes</a><h1>{{ __('ui.trash') }}</h1><p>{{ __('ui.trash_help') }}</p></div>
        <a class="button secondary small back-to-notes" href="{{ route('notes.index') }}">{{ __('ui.back_to_notes') }}</a>
    </header>

    @if (session('status'))<div id="toast" class="toast" role="status"><span>{{ session('status') }}</span><button type="button" aria-label="{{ __('ui.close') }}"><x-icon name="close" class="close-icon" /></button></div>@endif
    @if ($errors->has('trash'))<div class="errors">{{ $errors->first('trash') }}</div>@endif

    @if ($items === [])
        <section class="empty-trash"><h2>{{ __('ui.trash_empty') }}</h2><p>{{ __('ui.trash_help') }}</p></section>
    @else
        <section class="trash-list" aria-label="{{ __('ui.trash') }}">
            @foreach ($items as $item)
                <article class="trash-card">
                    <div class="trash-card-info"><h2>{{ $item['original_path'] }}</h2><p><span class="trash-type">{{ $item['type'] === 'folder' ? __('ui.folder') : 'Markdown' }}</span>{{ __('ui.deleted_at', ['date' => \Illuminate\Support\Carbon::parse($item['deleted_at'])->isoFormat('L LT')]) }}</p></div>
                    <div class="trash-actions">@if ($item['type'] === 'note')<button type="button" class="button secondary small" data-trash-preview-url="{{ route('trash.show', ['id' => $item['id']]) }}" data-trash-preview-title="{{ $item['original_path'] }}" aria-haspopup="dialog" aria-controls="trash-preview">{{ __('ui.view') }}</button>@endif<form method="post" action="{{ route('trash.restore', ['id' => $item['id']]) }}">@csrf<button class="button secondary small" type="submit">{{ __('ui.restore_from_trash') }}</button></form><a class="button danger small" href="#delete-trash-{{ $item['id'] }}">{{ __('ui.delete_permanently') }}</a></div>
                </article>
                <div id="delete-trash-{{ $item['id'] }}" class="modal"><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="delete-trash-title-{{ $item['id'] }}"><h2 id="delete-trash-title-{{ $item['id'] }}">{{ __('ui.delete_permanently_question') }}</h2><p>{{ __('ui.delete_permanently_help') }}</p><div class="modal-footer"><a href="#">{{ __('ui.cancel') }}</a><form method="post" action="{{ route('trash.destroy', ['id' => $item['id']]) }}">@csrf @method('DELETE')<button class="button danger" type="submit">{{ __('ui.delete_permanently') }}</button></form></div></section></div>
            @endforeach
        </section>
    @endif
</main>
<dialog id="trash-preview" class="trash-preview" aria-labelledby="trash-preview-title" aria-describedby="trash-preview-path" data-loading="{{ __('ui.trash_preview_loading') }}" data-error="{{ __('ui.trash_preview_error') }}">
    <header class="trash-preview-header">
        <div><h2 id="trash-preview-title">{{ __('ui.trash_preview') }}</h2><p id="trash-preview-path"></p></div>
        <button type="button" class="button secondary small" data-trash-preview-close autofocus>{{ __('ui.close') }}</button>
    </header>
    <div class="trash-preview-body">
        <p class="trash-preview-status" role="status" aria-live="polite"></p>
        <article class="trash-preview-content markdown-body" hidden></article>
    </div>
</dialog>
<script>
    const toast = document.getElementById('toast');
    if (toast) { const dismiss = () => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 220); }; toast.querySelector('button').onclick = dismiss; setTimeout(dismiss, 3000); }
</script>
@endsection
